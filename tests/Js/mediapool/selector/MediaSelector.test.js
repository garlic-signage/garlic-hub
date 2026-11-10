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

import {assertEquals, assertStrictEquals} from "@std/assert";
import {stub}                             from "@std/testing/mock";
import {assertSameElement, createDom}     from "@support/dom.js";
import {EventEmitter}                     from "@js/core/EventEmitter.js";
import {MediaSelector}                    from "@js/mediapool/selector/MediaSelector.js";

const SELECTOR = `
	<div id="tree"></div>
	<div id="mediaList">
		<article class="media-item" data-media-id="a" data-original-url="/o/a" data-file-name="a.jpg"><img></article>
		<article class="media-item" data-media-id="b" data-original-url="/o/b" data-file-name="b.png"><img></article>
		<article class="media-item" data-media-id="c" data-original-url="/o/c" data-file-name="c.jpg"><img></article>
	</div>`;

/**
 * The tree emits the chosen directory like the WunderbaumWrapper.
 */
class FakeTreeViewWrapper extends EventEmitter
{
	initialised = 0;

	initTree()
	{
		this.initialised++;
	}
}

class FakeSelectorView
{
	displayed  = [];
	mediaItems = ['item'];
	#document;

	constructor(document)
	{
		this.#document = document;
	}

	loadSelectorTemplate()
	{
		const template = this.#document.createElement('template');
		template.innerHTML = SELECTOR;
		return template.content.cloneNode(true);
	}

	getMediaListElement()
	{
		return this.#document.getElementById('mediaList');
	}

	displayMediaList(mediaList)
	{
		this.displayed.push(mediaList);
	}
}

class FakeMediaService
{
	loads  = [];
	result = [{media_id: 'a'}];

	loadFilteredMediaByNodeId(nodeId, filter)
	{
		this.loads.push([nodeId, filter]);
		return this.result instanceof Error ? Promise.reject(this.result) : Promise.resolve(this.result);
	}
}

function createSelector()
{
	const dom          = createDom('<div id="dialog"><p>old</p></div>');
	const tree         = new FakeTreeViewWrapper();
	const view         = new FakeSelectorView(dom.document);
	const mediaService = new FakeMediaService();

	return {
		document: dom.document, tree, view, mediaService,
		selector: new MediaSelector(tree, mediaService, view),
		[Symbol.dispose]: () => dom[Symbol.dispose]()
	};
}

async function showSelector(page)
{
	await page.selector.showSelector(page.document.getElementById('dialog'));
}

function click(page, selector)
{
	page.document.querySelector(selector).dispatchEvent(new Event('click', {bubbles: true}));
}

function selectedIds(page)
{
	return page.selector.getSelectedMedia().map(media => media.id);
}

/**
 * The tree reports a chosen directory, the selector loads its media.
 */
async function chooseDirectory(page, nodeId)
{
	page.tree.emit('treeview:loadMediaInDirectory', {node_id: nodeId});
	await new Promise(resolve => setTimeout(resolve, 0));
}

Deno.test('the selector replaces the content of the element and starts the tree', async () =>
{
	using page = createSelector();

	await showSelector(page);

	const dialog = page.document.getElementById('dialog');
	assertEquals(dialog.querySelector('p'), null);
	assertEquals(dialog.querySelectorAll('.media-item').length, 3);
	assertEquals(page.tree.initialised, 1);
});

Deno.test('a click selects one medium, another click moves the selection', async () =>
{
	using page = createSelector();
	await showSelector(page);

	click(page, '[data-media-id="a"] img');
	assertEquals(selectedIds(page), ['a']);

	click(page, '[data-media-id="b"]');
	assertEquals(selectedIds(page), ['b']);

	// one medium stays selected
	click(page, '[data-media-id="b"]');
	assertEquals(selectedIds(page), ['b']);
});

Deno.test('a click beside the media changes nothing', async () =>
{
	using page = createSelector();
	await showSelector(page);
	click(page, '[data-media-id="a"]');

	click(page, '#mediaList');

	assertEquals(selectedIds(page), ['a']);
});

Deno.test('with multiselect several media are selected', async () =>
{
	using page = createSelector();
	page.selector.enableMultiselect();
	await showSelector(page);

	click(page, '[data-media-id="c"]');
	click(page, '[data-media-id="a"]');
	click(page, '[data-media-id="c"]');
	click(page, '[data-media-id="b"]');

	assertEquals(selectedIds(page), ['a', 'b']);

	page.selector.disableMultiselect();
	click(page, '[data-media-id="c"]');
	assertEquals(selectedIds(page), ['c']);
});

Deno.test('the selected media come with their original and file name', async () =>
{
	using page = createSelector();
	page.selector.enableMultiSelect();
	await showSelector(page);

	click(page, '[data-media-id="a"]');
	click(page, '[data-media-id="b"]');

	assertEquals(page.selector.getSelectedMedia(), [
		{id: 'a', src: '/o/a', fileName: 'a.jpg'},
		{id: 'b', src: '/o/b', fileName: 'b.png'},
	]);
});

Deno.test('a directory of the tree shows its media with the filter and reports it', async () =>
{
	using page   = createSelector();
	const loaded = [];
	page.selector.on('mediapool:selector:loaded', (args) => loaded.push(args));
	page.selector.filter = 'images';

	await chooseDirectory(page, 12);

	assertEquals(page.mediaService.loads, [[12, 'images']]);
	assertEquals(page.view.displayed, [[{media_id: 'a'}]]);
	assertEquals(loaded, [{nodeId: 12}]);
});

Deno.test('a directory that cannot be loaded shows an empty list and is reported', async () =>
{
	using page      = createSelector();
	using errorStub = stub(console, 'error');
	const loaded    = [];
	page.selector.on('mediapool:selector:loaded', (args) => loaded.push(args));
	page.mediaService.result = new Error('Not allowed');

	await chooseDirectory(page, 12);

	assertEquals(errorStub.calls[0].args, ['Error loading media:', 'Not allowed']);
	assertEquals(page.view.displayed, [[]]);
	assertEquals(loaded, [{nodeId: 12}]);
});

Deno.test('a listener can be removed', async () =>
{
	using page   = createSelector();
	const loaded = [];
	const listener = (args) => loaded.push(args);
	page.selector.on('mediapool:selector:loaded', listener);
	page.selector.off('mediapool:selector:loaded', listener);

	await chooseDirectory(page, 12);

	assertEquals(loaded, []);
});

Deno.test('the list and the items come from the view', async () =>
{
	using page = createSelector();
	await showSelector(page);

	assertSameElement(page.selector.getMediaItemsContainer(), page.document.getElementById('mediaList'));
	assertStrictEquals(page.selector.getMediaItems(), page.view.mediaItems);
});
