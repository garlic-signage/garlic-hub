/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
 This file is part of the garlic-hub source code

 This program is free software: you can redistribute it and/or modify
 it under the terms of the GNU Affero General Public License, version 3,
 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

import {assertEquals}                  from "@std/assert";
import {assertSameElement, createDom}  from "@support/dom.js";
import {MemoryStorage, replaceGlobals} from "@support/globals.js";
import {Wunderbaum}                    from "@support/FakeWunderbaum.js";
import {TreeViewElements}              from "@js/mediapool/treeview/TreeViewElements.js";
import {WunderbaumWrapper}             from "@js/mediapool/treeview/WunderbaumWrapper.js";

// the tree of templates/generic/media_selector.mustache, the path only in the mediapool
const SELECTOR = '<input id="treeViewFilter" type="search"><div id="mediapoolTree"><span id="lobbyTitle"></span></div>';

/**
 * A node of Wunderbaum: it records what the wrapper does with it.
 */
function treeNode(key, title, data = {}, parent = null)
{
	return {
		key: key, title: title, parent: parent, data: data, calls: [],
		getPath(includeSelf, part, separator)
		{
			return this.getParentList(false, includeSelf).map(node => node[part]).join(separator);
		},
		getParentList(includeRoot, includeSelf)
		{
			const list = includeSelf ? [this] : [];
			for (let node = this.parent; node !== null; node = node.parent)
				list.unshift(node);
			return list;
		},
		async setExpanded(flag) { this.calls.push(['setExpanded', flag]); },
		async setActive(flag)   { this.calls.push(['setActive', flag]); },
		addChildren(child)      { this.calls.push(['addChildren', child]); },
	};
}

/**
 * A wrapper with its tree; the selector puts the tree into the page before initTree().
 */
function createPage(body = SELECTOR)
{
	const dom     = createDom('<div id="dialog"></div>');
	const globals = replaceGlobals({localStorage: new MemoryStorage()});
	Wunderbaum.last = null;

	const wrapper = new WunderbaumWrapper(new TreeViewElements());
	dom.document.getElementById('dialog').innerHTML = body;
	wrapper.initTree();

	const events = [];
	for (const name of ['treeview:loadMediaInDirectory', 'treeview:moveMediaTo', 'treeview:moveNodeTo'])
		wrapper.on(name, (args) => events.push([name, args]));

	return {
		document: dom.document, wrapper, events, tree: Wunderbaum.last, options: Wunderbaum.last?.options,
		[Symbol.dispose]: () =>
		{
			Wunderbaum.nodes.clear();
			WunderbaumWrapper.workaroundShitForMediaIdBecauseOfChrome = '';
			globals[Symbol.dispose]();
			dom[Symbol.dispose]();
		}
	};
}

Deno.test('the tree is created in the element of the selector and loads the root folders', () =>
{
	using page = createPage();

	assertSameElement(page.wrapper.tree, page.tree);
	assertSameElement(page.options.element, page.document.getElementById('mediapoolTree'));
	assertEquals(page.options.source, {url: '/async/mediapool/node/0'});
	assertEquals(page.options.selectMode, 'single');
	assertEquals(page.options.lazyLoad({node: {key: 7}}), {url: '/async/mediapool/node/7', params: {parentKey: 7}});
	assertEquals('dnd' in page.options, false);
});

Deno.test('without the tree in the page there is no tree', () =>
{
	using page = createPage('');

	assertEquals(page.wrapper.tree, null);
	assertEquals(page.tree, null);
});

Deno.test('the last opened folder is opened again', async () =>
{
	using page   = createPage();
	const lobby  = treeNode(1, 'Lobby');
	const shared = treeNode('shared', 'Shared with me');
	page.tree.keys.set(1, lobby).set('shared', shared);
	localStorage.setItem('parent_list', JSON.stringify([1, 'shared']));

	await page.options.init({tree: page.tree});

	assertEquals(lobby.calls, [['setExpanded', true]]);
	assertEquals(shared.calls, [['setExpanded', true], ['setActive', undefined]]);
});

Deno.test('a folder that is gone, an old or no saved path opens nothing', async () =>
{
	for (const saved of [JSON.stringify([1, 99]), '1,2', JSON.stringify([]), JSON.stringify({key: 1}), null])
	{
		using page  = createPage();
		const lobby = treeNode(1, 'Lobby');
		page.tree.keys.set(1, lobby);
		if (saved !== null)
			localStorage.setItem('parent_list', saved);

		await page.options.init({tree: page.tree});

		assertEquals(lobby.calls.filter(call => call[0] === 'setActive'), [], String(saved));
	}
});

Deno.test('an activated folder is saved and its media are asked for', () =>
{
	using page = createPage();

	page.options.activate({node: treeNode(2, 'Screens', {}, treeNode(1, 'Lobby'))});

	assertEquals(localStorage.getItem('parent_list'), '[1,2]');
	assertEquals(page.events, [['treeview:loadMediaInDirectory', {node_id: 2}]]);
});

Deno.test('the root "shared with me" asks for no media', () =>
{
	using page = createPage();

	page.options.activate({node: treeNode('shared', 'Shared with me', {virtual: true})});

	assertEquals(localStorage.getItem('parent_list'), '["shared"]');
	assertEquals(page.events, []);
});

Deno.test('with the path element the path of the folder is shown', () =>
{
	using page  = createPage(SELECTOR + '<span id="currentPath"></span>');
	// linkedom cannot set innerText
	const path  = page.document.getElementById('currentPath');
	Object.defineProperty(path, 'innerText', {value: '', writable: true});

	page.options.activate({node: treeNode(2, 'Screens', {}, treeNode(1, 'Lobby'))});

	assertEquals(path.innerText, ' / Lobby / Screens');
});

Deno.test('a listener can be removed', () =>
{
	using page   = createPage();
	const called = [];
	const listener = (args) => called.push(args);
	page.wrapper.on('treeview:loadMediaInDirectory', listener);

	page.wrapper.off('treeview:loadMediaInDirectory', listener);
	page.options.activate({node: treeNode(2, 'Screens')});

	assertEquals(called, []);
	assertEquals(page.events.length, 1);
});

Deno.test('the filter field filters the tree', () =>
{
	using page   = createPage();
	const filter = page.document.getElementById('treeViewFilter');

	filter.value = 'lob';
	filter.dispatchEvent(new Event('input'));

	assertEquals(page.tree.calls, [['filterNodes', 'lob', {mode: 'hide'}]]);
});

Deno.test('a clicked element activates its folder', () =>
{
	using page  = createPage();
	const title = page.document.getElementById('lobbyTitle');
	const lobby = treeNode(1, 'Lobby');
	Wunderbaum.nodes.set(title, lobby);

	assertEquals(page.wrapper.activateNodeByTarget(title), lobby);
	assertEquals(lobby.calls, [['setActive', true]]);
});

Deno.test('a root folder is added to the tree', () =>
{
	using page = createPage();

	page.wrapper.addRootFolder(9, 'Hall');

	assertEquals(page.tree.calls, [['addChildren', {key: 9, title: 'Hall', isFolder: true}]]);
});

Deno.test('a sub folder is added to the active folder', () =>
{
	using page    = createPage();
	const screens = treeNode(2, 'Screens');
	page.options.activate({node: screens});

	page.wrapper.addSubFolder(9, 'Hall');

	assertEquals(screens.calls, [['addChildren', {key: 9, title: 'Hall', isFolder: true}]]);
});

Deno.test('the drag and drop is added to the tree, the filter takes no drop', () =>
{
	using page = createPage();

	page.wrapper.addDragNDrop();

	assertEquals(page.tree.calls, [['setOption', 'dnd']]);
	for (const type of ['dragover', 'drop'])
	{
		const event = new Event(type, {cancelable: true});
		page.document.getElementById('treeViewFilter').dispatchEvent(event);
		assertEquals(event.defaultPrevented, true, type);
	}
});

Deno.test('every folder but "shared with me" can be dragged', () =>
{
	using page = createPage();
	page.wrapper.addDragNDrop();
	const dataTransfer = {effectAllowed: 'none'};

	assertEquals(page.options.dnd.dragStart({node: treeNode('shared', 'Shared', {virtual: true}), event: {dataTransfer}}), false);
	assertEquals(dataTransfer.effectAllowed, 'none');

	assertEquals(page.options.dnd.dragStart({node: treeNode(1, 'Lobby'), event: {dataTransfer}}), true);
	assertEquals(dataTransfer.effectAllowed, 'all');
});

Deno.test('media go only into a folder, folders also before and after it', () =>
{
	using page = createPage();
	page.wrapper.addDragNDrop();
	const enter  = page.options.dnd.dragEnter;
	const shared = treeNode('shared', 'Shared with me', {virtual: true});
	const lobby  = treeNode(1, 'Lobby');
	const folder = treeNode(5, 'From a colleague', {}, shared);

	assertEquals(enter({node: shared, sourceNode: lobby}), false);
	assertEquals(enter({node: lobby, sourceNode: null}), ['appendChild']);
	assertEquals(enter({node: folder, sourceNode: lobby}), ['appendChild']);
	assertEquals(enter({node: lobby, sourceNode: treeNode(2, 'Screens')}), ['before', 'after', 'appendChild']);
});

Deno.test('a dropped medium or folder is passed on to the listeners', () =>
{
	using page = createPage();
	page.wrapper.addDragNDrop();
	const lobby = treeNode(1, 'Lobby');
	WunderbaumWrapper.workaroundShitForMediaIdBecauseOfChrome = 'abc';

	page.options.dnd.drop({node: lobby, sourceNode: null});
	const folderDrop = {node: lobby, sourceNode: treeNode(2, 'Screens'), suggestedDropMode: 'before'};
	page.options.dnd.drop(folderDrop);

	assertEquals(page.events[0], ['treeview:moveMediaTo', {media_id: 'abc', node_id: 1}]);
	assertEquals(page.events[1][0], 'treeview:moveNodeTo');
	assertEquals(page.events[1][1].event === folderDrop, true);
	assertEquals(WunderbaumWrapper.workaroundShitForMediaIdBecauseOfChrome, '');
});
