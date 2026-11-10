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
import {ObjectPanelView}              from "@js/templates/canvas-composer/Views/ObjectPanelView.js";

// the panel of the objects in templates/templates/canvas.mustache
const PAGE = `
	<aside id="objectPanel">
		<button id="toggleObjectPanel" type="button" class="bi bi-layout-sidebar"></button>
		<fieldset id="objectGeometry" disabled>
			<input type="number" id="objectLeft" step="1">
			<input type="number" id="objectTop" step="1">
			<input type="number" id="objectWidth" min="1" step="1">
			<input type="number" id="objectHeight" min="1" step="1">
			<input type="number" id="objectAngle" min="-360" max="360" step="1">
		</fieldset>
		<fieldset id="objectsContainer"><ul id="objectList"></ul></fieldset>
	</aside>`;

function entry(changes = {})
{
	return {index: 0, shape: 'rect', kind: 'rect', label: 'Rectangle', isLocked: false, isSelectable: true, isActive: false, ...changes};
}

function createPanel()
{
	const dom = createDom(PAGE);
	return {
		document: dom.document, view: new ObjectPanelView(),
		list: dom.document.getElementById('objectList'),
		[Symbol.dispose]: () => dom[Symbol.dispose]()
	};
}

function shownEntries(page)
{
	return [...page.list.children].map(item => ({
		index: item.dataset.index, classes: item.className, icon: item.querySelector('i').className,
		label: item.querySelector('span')?.textContent, locked: item.querySelector('.bi-lock-fill') !== null
	}));
}

Deno.test('the toggle, the list and the fields are read from the page', () =>
{
	using page = createPanel();

	assertSameElement(page.view.toggle, page.document.getElementById('toggleObjectPanel'));
	assertSameElement(page.view.list, page.list);
	const ids = {left: 'objectLeft', top: 'objectTop', width: 'objectWidth', height: 'objectHeight', angle: 'objectAngle'};
	for (const [name, id] of Object.entries(ids))
		assertSameElement(page.view.fields[name], page.document.getElementById(id), name);
});

Deno.test('the panel is collapsed and opened again', () =>
{
	using page  = createPanel();
	const panel = page.document.getElementById('objectPanel');

	page.view.toggleCollapsed();
	assertEquals(panel.classList.contains('collapsed'), true);

	page.view.toggleCollapsed();
	assertEquals(panel.classList.contains('collapsed'), false);
});

Deno.test('every object is an entry with its icon and label, the active one is marked', () =>
{
	using page = createPanel();

	page.view.renderList([
		entry({index: 0, kind: 'text', shape: 'textbox', label: 'Welcome', isActive: true}),
		entry({index: 1, kind: 'image', shape: 'image', label: 'lobby.jpg'})
	], 'No objects');

	assertEquals(shownEntries(page), [
		{index: '0', classes: 'active', icon: 'bi bi-fonts', label: 'Welcome', locked: false},
		{index: '1', classes: '', icon: 'bi bi-image', label: 'lobby.jpg', locked: false}
	]);
	assertEquals(page.list.querySelector('span').title, 'Welcome');
});

Deno.test('the icon comes from the kind, else from the shape, else it is a square', () =>
{
	using page = createPanel();

	page.view.renderList([
		entry({kind: 'group', shape: 'group'}), entry({kind: 'shape', shape: 'circle'}), entry({kind: 'shape', shape: 'triangle'}),
		entry({kind: 'shape', shape: 'polygon'}), entry({kind: 'shape', shape: 'ellipse'})
	], 'No objects');

	assertEquals(shownEntries(page).map(item => item.icon),
		['bi bi-collection', 'bi bi-circle', 'bi bi-triangle', 'bi bi-pentagon', 'bi bi-square']);
});

Deno.test('a locked object has a lock, one which cannot be selected is marked', () =>
{
	using page = createPanel();

	page.view.renderList([entry({isLocked: true}), entry({index: 1, isSelectable: false})], 'No objects');

	const [locked, unselectable] = shownEntries(page);
	assertEquals(locked.locked, true);
	assertEquals(unselectable.classes, 'unselectable');
});

Deno.test('an empty template says so', () =>
{
	using page = createPanel();
	page.view.renderList([entry()], 'No objects');

	page.view.renderList([], 'No objects');

	assertEquals(page.list.children.length, 1);
	assertEquals([page.list.firstElementChild.className, page.list.firstElementChild.textContent], ['object-list-empty', 'No objects']);
});

Deno.test('a click on an entry or inside it gives the index of its object', () =>
{
	using page = createPanel();
	page.view.renderList([entry({index: 0}), entry({index: 3, isLocked: true})], 'No objects');
	const second = page.list.children[1];

	assertEquals(page.view.getIndexFromEvent({target: second}), 3);
	assertEquals(page.view.getIndexFromEvent({target: second.querySelector('span')}), 3);
	assertEquals(page.view.getIndexFromEvent({target: second.querySelector('.bi-lock-fill')}), 3);
});

Deno.test('a click on an object which cannot be selected or beside the entries gives nothing', () =>
{
	using page = createPanel();
	page.view.renderList([entry({isSelectable: false})], 'No objects');

	assertEquals(page.view.getIndexFromEvent({target: page.list.firstElementChild}), null);
	assertEquals(page.view.getIndexFromEvent({target: page.list}), null);
});

Deno.test('the geometry of the selected object fills the fields, only the editable ones can be changed', () =>
{
	using page = createPanel();

	page.view.showGeometry({left: 10, top: 20, width: 300, height: 200, angle: 45},
		{left: true, top: true, width: false, height: false, angle: true});

	const fields = page.view.fields;
	assertEquals(page.document.getElementById('objectGeometry').disabled, false);
	assertEquals(Object.values(fields).map(field => String(field.value)), ['10', '20', '300', '200', '45']);
	assertEquals(Object.values(fields).map(field => field.disabled), [false, false, true, true, false]);
});

Deno.test('without a selected object the fields are empty and locked', () =>
{
	using page = createPanel();
	page.view.showGeometry({left: 10, top: 20, width: 300, height: 200, angle: 45},
		{left: true, top: true, width: true, height: true, angle: true});

	page.view.showGeometry(null, {left: false, top: false, width: false, height: false, angle: false});

	assertEquals(page.document.getElementById('objectGeometry').disabled, true);
	assertEquals(Object.values(page.view.fields).map(field => field.value), ['', '', '', '', '']);
	assertEquals(Object.values(page.view.fields).map(field => field.disabled), [true, true, true, true, true]);
});
