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

import {assertEquals, assertNotStrictEquals} from "@std/assert";
import {assertSameElement, createDom}        from "@support/dom.js";
import {replaceGlobals}                      from "@support/globals.js";
import {ContextMenuTreeViewFactory}          from "@js/mediapool/treeview/ContextMenuTreeViewFactory.js";

// the template of templates/mediapool/overview.mustache
const PAGE = `
	<template id="treeViewContextMenuTemplate">
		<ul class="context-menu">
			<li id="edit_node"></li>
			<li id="add_node"></li>
			<li id="share_node"></li>
			<li id="tag_node"></li>
			<li id="remove_node"></li>
		</ul>
	</template>`;

const LANG = {edit_folder: 'Edit folder'};

class FakeTreeDialog
{
	calls = [];

	prepareShow(action, lang)
	{
		this.calls.push(['prepareShow', action, lang]);
	}

	show(directoryView)
	{
		this.calls.push(['show', directoryView]);
	}
}

class FakeTreeViewService
{
	deleted = [];
	error   = null;

	async deleteNode(nodeId)
	{
		this.deleted.push(nodeId);
		if (this.error !== null)
			throw this.error;

		return {success: true};
	}
}

class FakeShareDialog
{
	attached = [];

	attachTo(menuItem, unitId, canShare)
	{
		this.attached.push([menuItem, unitId, canShare]);
	}
}

/**
 * Answers with the number of tagged media or fails with the given Error.
 */
class FakeFolderTagging
{
	runs   = [];
	tagged = 2;

	async run(nodeId)
	{
		this.runs.push(nodeId);
		if (this.tagged instanceof Error)
			throw this.tagged;

		return this.tagged;
	}
}

class FakeDirectoryView
{
	activeKey = 7;
	reloads   = 0;

	isActiveNode(node)
	{
		return node.key === this.activeKey;
	}

	reloadCurrentNode()
	{
		this.reloads++;
	}
}

/**
 * A node of Wunderbaum with its rights.
 */
function treeNode(rights = {}, key = 7)
{
	return {
		key: key, removed: false,
		data: {rights: {delete: true, share_unit: true, ...rights}},
		remove() { this.removed = true; }
	};
}

function createPage(folderTagging = new FakeFolderTagging())
{
	const dom           = createDom(PAGE);
	// the AdjustMenus keep the menu inside the window
	const globals       = replaceGlobals({window: {innerWidth: 1000, innerHeight: 800}});
	const document      = dom.document;
	const treeDialog    = new FakeTreeDialog();
	const service       = new FakeTreeViewService();
	const shareDialog   = new FakeShareDialog();
	const directoryView = new FakeDirectoryView();
	const errors        = [];
	const elements      = {menuTemplate: document.getElementById('treeViewContextMenuTemplate')};
	const factory       = new ContextMenuTreeViewFactory(elements, treeDialog, service,
		{showError: (message) => errors.push(message)}, LANG, shareDialog, folderTagging);

	return {
		document, treeDialog, service, shareDialog, folderTagging, directoryView, errors, factory,
		[Symbol.dispose]: () => { globals[Symbol.dispose](); dom[Symbol.dispose](); }
	};
}

/**
 * A menu shown at 120/80, its entries are in the page then.
 */
function showMenu(page)
{
	const menu = page.factory.create();
	menu.show({pageX: 120, pageY: 80});
	return menu;
}

function entry(page, id)
{
	return page.document.querySelector(`body > .context-menu #${id}`);
}

async function click(page, id)
{
	entry(page, id).dispatchEvent(new Event('click'));
	await new Promise(resolve => setTimeout(resolve, 0));
}

function menus(page)
{
	return [...page.document.querySelectorAll('body > .context-menu')];
}

Deno.test('every menu is a new copy of the template', () =>
{
	using page = createPage();

	showMenu(page);
	const first = menus(page)[0];
	showMenu(page);
	const second = menus(page)[0];

	assertNotStrictEquals(first, second);
	assertEquals(second.querySelectorAll('li').length, 5);
});

Deno.test('the menu is shown where the mouse is and replaces the one before', () =>
{
	using page = createPage();

	showMenu(page);
	showMenu(page);

	const shown = menus(page);
	assertEquals(shown.length, 1);
	assertEquals([shown[0].style.left, shown[0].style.top], ['120px', '80px']);
});

Deno.test('the next click on the page closes the menu', () =>
{
	using page = createPage();
	showMenu(page);

	page.document.dispatchEvent(new Event('click'));

	assertEquals(menus(page), []);
});

Deno.test('edit and add open the folder dialog in their mode', async () =>
{
	using page = createPage();
	const menu = showMenu(page);
	menu.addEditEvent(entry(page, 'edit_node'), page.directoryView);
	menu.addAddEvent(entry(page, 'add_node'), page.directoryView);

	await click(page, 'edit_node');
	await click(page, 'add_node');

	assertEquals(page.treeDialog.calls, [
		['prepareShow', 'edit_folder', LANG], ['show', page.directoryView],
		['prepareShow', 'add_sub_folder', LANG], ['show', page.directoryView]
	]);
});

Deno.test('the share dialog gets the folder and whether it may be shared', () =>
{
	for (const shareUnit of [true, false, undefined])
	{
		using page = createPage();
		const menu = showMenu(page);

		menu.addShareEvent(entry(page, 'share_node'), treeNode({share_unit: shareUnit}));

		const [[menuItem, unitId, canShare]] = page.shareDialog.attached;
		assertSameElement(menuItem, entry(page, 'share_node'));
		assertEquals([unitId, canShare], [7, shareUnit === true], String(shareUnit));
	}
});

Deno.test('folders can be tagged only with a folder tagging', () =>
{
	{
		using page = createPage();
		assertEquals(page.factory.create().canTagFolders, true);
	}
	{
		using page = createPage(null);
		assertEquals(page.factory.create().canTagFolders, false);
	}
});

Deno.test('tagging the active folder reloads it to show the tags', async () =>
{
	using page = createPage();
	showMenu(page).addTagEvent(entry(page, 'tag_node'), treeNode(), page.directoryView);

	await click(page, 'tag_node');

	assertEquals(page.folderTagging.runs, [7]);
	assertEquals(page.directoryView.reloads, 1);
});

Deno.test('no reload when nothing was tagged or another folder is shown', async () =>
{
	for (const [tagged, key] of [[0, 7], [2, 8]])
	{
		using page = createPage();
		page.folderTagging.tagged = tagged;
		showMenu(page).addTagEvent(entry(page, 'tag_node'), treeNode({}, key), page.directoryView);

		await click(page, 'tag_node');

		assertEquals(page.folderTagging.runs, [key]);
		assertEquals(page.directoryView.reloads, 0, `${tagged} / ${key}`);
	}
});

Deno.test('a failed tagging shows the error', async () =>
{
	using page = createPage();
	page.folderTagging.tagged = new Error('The AI did not answer');
	showMenu(page).addTagEvent(entry(page, 'tag_node'), treeNode(), page.directoryView);

	await click(page, 'tag_node');

	assertEquals(page.errors, ['The AI did not answer']);
	assertEquals(page.directoryView.reloads, 0);
});

Deno.test('a removed folder leaves the tree', async () =>
{
	using page = createPage();
	const node = treeNode();
	showMenu(page).addRemoveEvent(entry(page, 'remove_node'), node);

	await click(page, 'remove_node');

	assertEquals(page.service.deleted, [7]);
	assertEquals(node.removed, true);
	assertEquals(page.errors, []);
});

Deno.test('a folder that could not be removed stays and the error is shown', async () =>
{
	using page = createPage();
	page.service.error = new Error('The folder is not empty');
	const node = treeNode();
	showMenu(page).addRemoveEvent(entry(page, 'remove_node'), node);

	await click(page, 'remove_node');

	assertEquals(page.errors, ['The folder is not empty']);
	assertEquals(node.removed, false);
});

Deno.test('without the right to delete nothing is sent', async () =>
{
	using page = createPage();
	const node = treeNode({delete: false});
	showMenu(page).addRemoveEvent(entry(page, 'remove_node'), node);

	await click(page, 'remove_node');

	assertEquals(page.service.deleted, []);
	assertEquals(node.removed, false);
	assertEquals(page.errors, ['Missing delete right for this node.']);
});
