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

import {assertEquals}                 from "@std/assert";
import {assertSameElement, createDom} from "@support/dom.js";
import {TreeViewElements}             from "@js/mediapool/treeview/TreeViewElements.js";

// the tree of templates/mediapool/overview.mustache
const TREE = `
	<input id="treeViewFilter" type="search">
	<div id="mediapoolTree"></div>
	<span id="currentPath"></span>
	<dialog id="editFolderDialog"><button id="closeEditDialog"></button></dialog>
	<template id="treeViewContextMenuTemplate"></template>`;

const IDS = {
	mediapoolTree: 'mediapoolTree', currentPath: 'currentPath', treeViewFilter: 'treeViewFilter',
	editFolderDialog: 'editFolderDialog', closeEditDialog: 'closeEditDialog', menuTemplate: 'treeViewContextMenuTemplate'
};

function assertElementsOfPage(elements, document)
{
	for (const [getter, id] of Object.entries(IDS))
		assertSameElement(elements[getter], document.getElementById(id), getter);
}

Deno.test('the elements of the tree in the mediapool are read when it is created', () =>
{
	using dom = createDom(TREE);

	assertElementsOfPage(new TreeViewElements(), dom.document);
});

Deno.test('a selector reads its tree again after it was put into the page', () =>
{
	using dom      = createDom('<div id="dialog"></div>');
	const elements = new TreeViewElements();
	assertEquals(Object.keys(IDS).map(getter => elements[getter]), [null, null, null, null, null, null]);

	dom.document.getElementById('dialog').innerHTML = TREE;
	elements.initialize();

	assertElementsOfPage(elements, dom.document);
});

Deno.test('a selector without the parts of the mediapool has only the tree and the filter', () =>
{
	using dom = createDom('<input id="treeViewFilter"><div id="mediapoolTree"></div>');

	const elements = new TreeViewElements();
	elements.initialize();

	assertSameElement(elements.mediapoolTree, dom.document.getElementById('mediapoolTree'));
	assertSameElement(elements.treeViewFilter, dom.document.getElementById('treeViewFilter'));
	assertEquals([elements.currentPath, elements.editFolderDialog, elements.closeEditDialog, elements.menuTemplate],
		[null, null, null, null]);
});
