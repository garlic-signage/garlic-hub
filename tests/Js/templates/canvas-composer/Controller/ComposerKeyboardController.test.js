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

import {assertEquals}                               from "@std/assert";
import {element, fire, FakeFabricWrapper, recorder} from "@support/ComposerControllerFakes.js";
import {ComposerKeyboardController}                 from "@js/templates/canvas-composer/Controller/ComposerKeyboardController.js";

function createController()
{
	const view        = {canvasWrap: element()};
	const transform   = recorder(['moveActiveObject']);
	const contextMenu = recorder(['duplicate', 'copy', 'paste', 'remove']);
	const properties  = recorder(['deactivateAllProperties']);
	const wrapper     = new FakeFabricWrapper();
	new ComposerKeyboardController(view, transform, contextMenu, properties, wrapper);

	return {
		view, transform, contextMenu, properties, wrapper,
		press: (key, modifiers = {}) => fire(view.canvasWrap, 'keydown', {key, ctrlKey: false, shiftKey: false, ...modifiers})
	};
}

Deno.test('the arrows move the active object by one pixel, with Shift by 50', () =>
{
	const page = createController();

	for (const key of ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'])
	{
		page.press(key);
		page.press(key, {shiftKey: true});
	}

	assertEquals(page.transform.calls, [
		['moveActiveObject', 'ArrowLeft', 1], ['moveActiveObject', 'ArrowLeft', 50],
		['moveActiveObject', 'ArrowRight', 1], ['moveActiveObject', 'ArrowRight', 50],
		['moveActiveObject', 'ArrowUp', 1], ['moveActiveObject', 'ArrowUp', 50],
		['moveActiveObject', 'ArrowDown', 1], ['moveActiveObject', 'ArrowDown', 50],
	]);
});

Deno.test('Ctrl+Z undoes, Ctrl+Y and Ctrl+Shift+Z redo, the properties are closed', () =>
{
	const page = createController();

	page.press('z', {ctrlKey: true});
	page.press('Z', {ctrlKey: true});
	page.press('y', {ctrlKey: true});
	page.press('Z', {ctrlKey: true, shiftKey: true});

	assertEquals(page.wrapper.calls, [['undo'], ['undo'], ['redo'], ['redo']]);
	assertEquals(page.properties.calls.length, 4);
});

Deno.test('Ctrl+D duplicates instead of the bookmark of the browser', () =>
{
	const page = createController();

	const event = page.press('d', {ctrlKey: true});

	assertEquals(page.contextMenu.calls, [['duplicate']]);
	assertEquals(event.defaultPrevented, true);
});

Deno.test('Ctrl+C copies, Ctrl+V pastes and Delete removes the active object', () =>
{
	const page = createController();

	page.press('c', {ctrlKey: true});
	page.press('v', {ctrlKey: true});
	page.press('Delete');

	assertEquals(page.contextMenu.calls, [['copy'], ['paste'], ['remove']]);
});

Deno.test('other keys do nothing', () =>
{
	const page = createController();

	for (const key of ['a', 'Enter', 'Backspace', 'c', 'v', 'd'])
		page.press(key);

	assertEquals([page.transform.calls, page.contextMenu.calls, page.wrapper.calls], [[], [], []]);
});

Deno.test('while a text is edited the keys belong to the text', () =>
{
	const page = createController();
	page.wrapper.activeObject = {type: 'i-text', isEditing: true};

	page.press('Delete');
	page.press('ArrowLeft');
	page.press('z', {ctrlKey: true});
	page.press('c', {ctrlKey: true});

	assertEquals([page.transform.calls, page.contextMenu.calls, page.wrapper.calls], [[], [], []]);
});

Deno.test('a selected text which is not edited is removed by Delete', () =>
{
	const page = createController();
	page.wrapper.activeObject = {type: 'i-text', isEditing: false};

	page.press('Delete');

	assertEquals(page.contextMenu.calls, [['remove']]);
});
