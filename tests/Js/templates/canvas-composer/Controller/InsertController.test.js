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

import {assertEquals}            from "@std/assert";
import {element, fire, recorder} from "@support/ComposerControllerFakes.js";
import {busOfTest}               from "@support/MultizoneEvents.js";
import {InsertController}        from "@js/templates/canvas-composer/Controller/InsertController.js";

const BUTTONS = ['insertImage', 'insertText', 'insertCircle', 'insertTriangle', 'insertRectangle', 'insertPolygon',
	'insertHexagon', 'insertOctagon'];

function createController()
{
	const view    = Object.fromEntries(BUTTONS.map(name => [name, element()]));
	const service = recorder(['insertText', 'insertCircle', 'insertTriangle', 'insertRectangle', 'insertRegularPolygon']);
	new InsertController(view, service);
	return {view, service};
}

Deno.test('the image opens the media dialog to insert', () =>
{
	using bus  = busOfTest('openMediaDialogForInsert');
	const page = createController();

	fire(page.view.insertImage, 'click');

	assertEquals(bus.events.map(([type]) => type), ['openMediaDialogForInsert']);
	assertEquals(page.service.calls, []);
});

Deno.test('the text and the shapes are inserted', () =>
{
	using _bus = busOfTest();
	const page = createController();

	for (const name of BUTTONS.slice(1))
		fire(page.view[name], 'click');

	assertEquals(page.service.calls, [
		['insertText'], ['insertCircle'], ['insertTriangle'], ['insertRectangle'],
		['insertRegularPolygon', 5], ['insertRegularPolygon', 6], ['insertRegularPolygon', 8]
	]);
});
