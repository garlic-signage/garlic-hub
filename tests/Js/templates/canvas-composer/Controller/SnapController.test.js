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
import {SnapController}          from "@js/templates/canvas-composer/Controller/SnapController.js";

function createController(gridEnabled = true)
{
	const view    = Object.assign(recorder(['drawGrid', 'getSnapToGridValue'], {getSnapToGridValue: () => view.gridValue}),
		{snapToGrid: element(), gridValue: 0});
	const service = Object.assign(recorder(['snapToGrid', 'enable', 'disable', 'getCanvasvaluesForGrid'],
		{getCanvasvaluesForGrid: {width: 800, height: 600, size: 20}}), {isGridEnabled: gridEnabled});
	new SnapController(view, service);
	return {view, service};
}

Deno.test('a moved object snaps to the grid only when the grid is on', () =>
{
	using bus = busOfTest();
	const on  = createController(true);
	const off = createController(false);
	const object = {left: 13};

	bus.send('snapToGrid', {target: object});

	assertEquals(on.service.calls, [['snapToGrid', object]]);
	assertEquals(off.service.calls, []);
});

Deno.test('the grid is drawn only when it is on', () =>
{
	using bus = busOfTest();
	const on  = createController(true);
	const off = createController(false);

	bus.send('drawGrid');

	assertEquals(on.view.calls, [['drawGrid', {width: 800, height: 600, size: 20}]]);
	assertEquals(off.view.calls, []);
});

Deno.test('a size of the grid switches it on, none switches it off', () =>
{
	using _bus = busOfTest();
	const page = createController();

	page.view.gridValue = 20;
	fire(page.view.snapToGrid, 'change');
	page.view.gridValue = 0;
	fire(page.view.snapToGrid, 'change');

	assertEquals(page.service.calls, [['enable', 20], ['disable']]);
});
