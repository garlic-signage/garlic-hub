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

import {assertEquals, assertThrows}    from "@std/assert";
import {stub}                          from "@std/testing/mock";
import {createDom}                     from "@support/dom.js";
import {MemoryStorage, replaceGlobals} from "@support/globals.js";
import {Wunderbaum}                    from "@support/FakeWunderbaum.js";

// DirectoryView loads UploadDialogElements, which reads the document when it is loaded
let DirectoryView, UploadDialogElements;
{
	using _dom = createDom('<button id="openUploadDialog"></button>');
	({DirectoryView}        = await import("@js/mediapool/treeview/DirectoryView.js"));
	({UploadDialogElements} = await import("@js/mediapool/uploads/UploadDialogElements.js"));
}

// the tree and the menu entries of templates/mediapool/overview.mustache, the menu as it is shown
const PAGE = `
	<input id="treeViewFilter" type="search">
	<div id="mediapoolTree"><span id="lobbyTitle">Lobby</span></div>
	<ul class="context-menu">
		<li id="edit_node"></li>
		<li id="add_node"></li>
		<li id="share_node"></li>
		<li id="tag_node"></li>
		<li id="remove_node"></li>
	</ul>`;

const ALL_RIGHTS = {create: true, edit: true, delete: true, share_unit: true};

/**
 * A node of Wunderbaum: it records what the view does with it.
 */
function treeNode(key, title, data = {}, parent = null)
{
	return {
		key: key, title: title, parent: parent, calls: [],
		data: {rights: ALL_RIGHTS, ...data},
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
		update()                { this.calls.push(['update']); },
		addChildren(child)      { this.calls.push(['addChildren', child]); },
		moveTo(target, mode)    { this.calls.push(['moveTo', target.key, mode]); },
	};
}

/**
 * Answers with an empty list or fails with the given Error.
 */
class FakeMediaList
{
	calls = [];
	error = null;

	loadMediaListByNode(nodeId, rights)
	{
		this.calls.push(['loadMediaListByNode', nodeId, rights]);
		return this.error === null ? Promise.resolve() : Promise.reject(this.error);
	}

	moveMediaTo(mediaId, nodeId)
	{
		this.calls.push(['moveMediaTo', mediaId, nodeId]);
		return this.error === null ? Promise.resolve() : Promise.reject(this.error);
	}

	render(media)
	{
		this.calls.push(['render', media]);
	}
}

class FakeTreeViewService
{
	calls = [];
	error = null;

	async moveNodeTo(sourceKey, targetKey, mode)
	{
		this.calls.push([sourceKey, targetKey, mode]);
		if (this.error !== null)
			throw this.error;

		return {success: true};
	}
}

function createPage()
{
	const dom      = createDom(PAGE);
	const globals  = replaceGlobals({localStorage: new MemoryStorage()});
	const document = dom.document;
	const elements = {
		mediapoolTree:  document.getElementById('mediapoolTree'),
		currentPath:    {innerText: ''}, // linkedom cannot set innerText
		treeViewFilter: document.getElementById('treeViewFilter')
	};
	const mediaList = new FakeMediaList();
	const service   = new FakeTreeViewService();
	const errors    = [];
	const view      = new DirectoryView(elements, mediaList, service, {showError: (message) => errors.push(message)});
	const tree      = Wunderbaum.last;
	DirectoryView.workaroundShitForMediaIdBecauseOfChrome = '';

	return {
		document, elements, mediaList, service, errors, view, tree, options: tree.options,
		[Symbol.dispose]: () =>
		{
			Wunderbaum.nodes.clear();
			DirectoryView.workaroundShitForMediaIdBecauseOfChrome = '';
			globals[Symbol.dispose]();
			dom[Symbol.dispose]();
		}
	};
}

function wait()
{
	return new Promise(resolve => setTimeout(resolve, 0));
}

/**
 * Lobby with the sub folder Screens, Screens is activated.
 */
async function activateScreens(page, data = {})
{
	const lobby   = treeNode(1, 'Lobby');
	const screens = treeNode(2, 'Screens', data, lobby);
	page.options.activate({node: screens});
	await wait();
	return screens;
}

Deno.test('the tree loads the root folders and the sub folders when they are opened', () =>
{
	using page = createPage();

	assertEquals(page.options.element, page.elements.mediapoolTree);
	assertEquals(page.options.source, {url: '/async/mediapool/node/0'});
	assertEquals(page.options.selectMode, 'single');
	assertEquals(page.options.lazyLoad({node: {key: 7}}), {url: '/async/mediapool/node/7', params: {parentKey: 7}});
});

Deno.test('the last opened folder is opened again', async () =>
{
	using page = createPage();
	const lobby   = treeNode(1, 'Lobby');
	const shared  = treeNode('shared', 'Shared with me');
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
		using page = createPage();
		const lobby = treeNode(1, 'Lobby');
		page.tree.keys.set(1, lobby);
		if (saved !== null)
			localStorage.setItem('parent_list', saved);

		await page.options.init({tree: page.tree});

		assertEquals(lobby.calls.filter(call => call[0] === 'setActive'), [], String(saved));
	}
});

Deno.test('an activated folder shows its path, is saved and loads its media', async () =>
{
	using page = createPage();
	UploadDialogElements.openUploadDialog.disabled = true;

	const screens = await activateScreens(page);

	assertEquals(page.elements.currentPath.innerText, ' / Lobby / Screens');
	assertEquals(localStorage.getItem('parent_list'), '[1,2]');
	assertEquals(page.mediaList.calls, [['loadMediaListByNode', 2, ALL_RIGHTS]]);
	assertEquals(UploadDialogElements.openUploadDialog.disabled, false);
	assertEquals(page.view.getActiveNodeId(), 2);
	assertEquals(page.view.getActiveNodeTitle(), 'Screens');
	assertEquals(page.view.getActiveNodeRights(), ALL_RIGHTS);
	assertEquals(page.view.isActiveNode(screens), true);
	assertEquals(page.view.isActiveNode(screens.parent), false);
});

Deno.test('without the right to create the upload is disabled', async () =>
{
	using page = createPage();
	UploadDialogElements.openUploadDialog.disabled = false;

	await activateScreens(page, {rights: {create: false, edit: true, delete: true}});

	assertEquals(UploadDialogElements.openUploadDialog.disabled, true);
});

Deno.test('the root "shared with me" has no media and takes no uploads', async () =>
{
	using page = createPage();
	UploadDialogElements.openUploadDialog.disabled = false;

	page.options.activate({node: treeNode('shared', 'Shared with me', {virtual: true})});
	await wait();

	assertEquals(page.mediaList.calls, [['render', []]]);
	assertEquals(UploadDialogElements.openUploadDialog.disabled, true);
});

Deno.test('media that could not be loaded show the error', async () =>
{
	using page = createPage();
	page.mediaList.error = new Error('Access denied');

	await activateScreens(page);

	assertEquals(page.errors, ['Access denied']);
});

Deno.test('without an active folder there is nothing to read or change', () =>
{
	using page = createPage();

	assertEquals(page.view.getActiveNodeTitle(), '');
	assertEquals(page.view.getActiveNodeId(), 0);
	assertEquals(page.view.getActiveNodeRights(), 0);
	assertEquals(page.view.getActiveNodeVisibility(), 0);
	assertEquals(page.view.isActiveNode(treeNode(1, 'Lobby')), false);
	assertThrows(() => page.view.setActiveTitle('Hall'), Error, 'No active node');
	assertThrows(() => page.view.setActiveNodeVisibility(1), Error, 'No active node');

	page.view.reloadCurrentNode();
	assertEquals(page.mediaList.calls, []);
});

Deno.test('the active folder gets a new title and visibility', async () =>
{
	using page    = createPage();
	const screens = await activateScreens(page);

	page.view.setActiveTitle('Hall');
	page.view.setActiveNodeVisibility(2);

	assertEquals(screens.title, 'Hall');
	assertEquals(page.view.getActiveNodeVisibility(), 2);
	assertEquals(screens.calls, [['update'], ['update']]);
});

Deno.test('the active folder loads its media again', async () =>
{
	using page = createPage();
	await activateScreens(page);

	page.view.reloadCurrentNode();
	await wait();

	assertEquals(page.mediaList.calls.length, 2);
	assertEquals(page.mediaList.calls[1], ['loadMediaListByNode', 2, ALL_RIGHTS]);
});

Deno.test('a root folder gets all rights', () =>
{
	using page = createPage();

	page.view.addRootChild(9, 'Hall');

	assertEquals(page.tree.calls, [['addChildren', {key: 9, title: 'Hall', isFolder: true, rights: ALL_RIGHTS}]]);
});

Deno.test('a sub folder gets all rights, sharing only when the parent may be shared', async () =>
{
	for (const shareUnit of [true, false, undefined])
	{
		using page    = createPage();
		const screens = await activateScreens(page, {rights: {create: true, share_unit: shareUnit}});
		page.tree.activeNode = screens;

		page.view.addSubChild(9, 'Hall');

		assertEquals(screens.calls, [['addChildren', {
			key: 9, title: 'Hall', isFolder: true,
			rights: {create: true, edit: true, delete: true, share_unit: shareUnit === true}
		}]], String(shareUnit));
	}
});

Deno.test('a sub folder without an active folder is not added', () =>
{
	using page      = createPage();
	using errorStub = stub(console, 'error');

	page.view.addSubChild(9, 'Hall');

	assertEquals(errorStub.calls[0].args, ['No active node']);
	assertEquals(page.tree.calls, []);
});

Deno.test('the filter field filters the tree and takes no drop', () =>
{
	using page   = createPage();
	const filter = page.elements.treeViewFilter;

	filter.value = 'lob';
	filter.dispatchEvent(new Event('input'));

	assertEquals(page.tree.calls, [['filterNodes', 'lob', {mode: 'hide'}]]);
	for (const type of ['dragover', 'drop'])
	{
		const event = new Event(type, {cancelable: true});
		filter.dispatchEvent(event);
		assertEquals(event.defaultPrevented, true, type);
	}
});

Deno.test('every folder but "shared with me" can be dragged', () =>
{
	using page = createPage();
	const dataTransfer = {effectAllowed: 'none'};

	assertEquals(page.options.dnd.dragStart({node: treeNode('shared', 'Shared', {virtual: true}), event: {dataTransfer}}), false);
	assertEquals(dataTransfer.effectAllowed, 'none');

	assertEquals(page.options.dnd.dragStart({node: treeNode(1, 'Lobby'), event: {dataTransfer}}), true);
	assertEquals(dataTransfer.effectAllowed, 'all');
});

Deno.test('media go only into a folder, folders also before and after it', () =>
{
	using page   = createPage();
	const enter  = page.options.dnd.dragEnter;
	const shared = treeNode('shared', 'Shared with me', {virtual: true});
	const lobby  = treeNode(1, 'Lobby');
	const folder = treeNode(5, 'From a colleague', {}, shared);

	assertEquals(enter({node: shared, sourceNode: lobby}), false);
	DirectoryView.workaroundShitForMediaIdBecauseOfChrome = 'abc';
	assertEquals(enter({node: lobby, sourceNode: null}), ['appendChild']);
	assertEquals(enter({node: folder, sourceNode: lobby}), ['appendChild']);
	assertEquals(enter({node: lobby, sourceNode: treeNode(2, 'Screens')}), ['before', 'after', 'appendChild']);
});

Deno.test('a file of the desktop or a text finds no place in the tree and moves nothing', async () =>
{
	using page = createPage();
	const lobby = treeNode(1, 'Lobby');

	assertEquals(page.options.dnd.dragEnter({node: lobby, sourceNode: null}), false);

	page.options.dnd.drop({node: lobby, sourceNode: null});
	await wait();

	assertEquals(page.mediaList.calls, []);
	assertEquals(page.errors, []);
});

Deno.test('a dropped medium moves into the folder', async () =>
{
	using page = createPage();
	DirectoryView.workaroundShitForMediaIdBecauseOfChrome = 'abc';

	page.options.dnd.drop({node: treeNode(1, 'Lobby'), sourceNode: null});
	await wait();

	assertEquals(page.mediaList.calls, [['moveMediaTo', 'abc', 1]]);
	assertEquals(DirectoryView.workaroundShitForMediaIdBecauseOfChrome, '');
	assertEquals(page.errors, []);
});

Deno.test('a medium that could not be moved shows the error', async () =>
{
	using page = createPage();
	page.mediaList.error = new Error('Not allowed');
	DirectoryView.workaroundShitForMediaIdBecauseOfChrome = 'abc';

	page.options.dnd.drop({node: treeNode(1, 'Lobby'), sourceNode: null});
	await wait();

	assertEquals(page.errors, ['Not allowed']);
});

Deno.test('a dropped folder moves in the tree after the backend moved it', async () =>
{
	using page   = createPage();
	const source = treeNode(2, 'Screens');

	page.options.dnd.drop({node: treeNode(1, 'Lobby'), sourceNode: source, suggestedDropMode: 'before'});
	await wait();

	assertEquals(page.service.calls, [[2, 1, 'before']]);
	assertEquals(source.calls, [['moveTo', 1, 'before']]);
});

Deno.test('a folder that could not be moved stays and the error is shown', async () =>
{
	using page   = createPage();
	page.service.error = new Error('Not allowed');
	const source = treeNode(2, 'Screens');

	page.options.dnd.drop({node: treeNode(1, 'Lobby'), sourceNode: source, suggestedDropMode: 'appendChild'});
	await wait();

	assertEquals(page.errors, ['Not allowed']);
	assertEquals(source.calls, []);
});

/**
 * The menu of the tree records which entries got their events.
 */
class FakeContextMenu
{
	calls = [];
	canTagFolders = true;

	show(event)                          { this.calls.push('show'); }
	addEditEvent(element, view)          { this.calls.push('edit'); }
	addAddEvent(element, view)           { this.calls.push('add'); }
	addShareEvent(element, node)         { this.calls.push('share'); }
	addTagEvent(element, node, view)     { this.calls.push('tag'); }
	addRemoveEvent(element, node)        { this.calls.push('remove'); }
}

function openContextMenu(page, data, canTagFolders = true)
{
	const node = treeNode(1, 'Lobby', data);
	const menu = new FakeContextMenu();
	menu.canTagFolders = canTagFolders;
	const title = page.document.getElementById('lobbyTitle');
	Wunderbaum.nodes.set(title, node);
	page.view.addContextMenu({create: () => menu});

	const event = new Event('contextmenu', {bubbles: true, cancelable: true});
	title.dispatchEvent(event);

	const entries = [...page.document.querySelectorAll('.context-menu li')].map(item => item.id);
	return {node, menu, event, entries};
}

Deno.test('the context menu activates the folder and offers all entries for all rights', () =>
{
	using page = createPage();

	const {node, menu, event, entries} = openContextMenu(page, {});

	assertEquals(event.defaultPrevented, true);
	assertEquals(node.calls, [['setActive', true]]);
	assertEquals(menu.calls, ['show', 'edit', 'add', 'share', 'tag', 'remove']);
	assertEquals(entries, ['edit_node', 'add_node', 'share_node', 'tag_node', 'remove_node']);
});

Deno.test('without any right there is no context menu', () =>
{
	using page = createPage();

	const {menu, event} = openContextMenu(page, {rights: {create: false, edit: false, delete: false, share_unit: false}});

	assertEquals(event.defaultPrevented, true);
	assertEquals(menu.calls, []);
});

Deno.test('the entries without their right leave the menu', () =>
{
	using page = createPage();

	const {menu, entries} = openContextMenu(page, {rights: {create: false, edit: false, delete: false, share_unit: true}});

	assertEquals(menu.calls, ['show', 'share']);
	assertEquals(entries, ['share_node']);
});

Deno.test('the tagging needs a folder tagging and is not offered for "shared with me"', () =>
{
	{
		using page = createPage();
		const {menu, entries} = openContextMenu(page, {}, false);
		assertEquals(menu.calls.includes('tag'), false);
		assertEquals(entries.includes('tag_node'), false);
	}
	{
		using page = createPage();
		const {menu, entries} = openContextMenu(page, {virtual: true});
		assertEquals(menu.calls.includes('tag'), false);
		assertEquals(entries.includes('tag_node'), false);
	}
});

Deno.test('without a model for the tagging the menu has no tag entry and still works', () =>
{
	using page = createPage();
	page.document.getElementById('tag_node').remove();

	const {menu, entries} = openContextMenu(page, {});

	assertEquals(menu.calls, ['show', 'edit', 'add', 'share', 'remove']);
	assertEquals(entries, ['edit_node', 'add_node', 'share_node', 'remove_node']);
});
