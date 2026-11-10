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

import {assertEquals}                                 from "@std/assert";
import {callNames, composerWrapper, FakeFabricObject} from "@support/ComposerServicesFakes.js";
import {TransformService}                             from "@js/templates/canvas-composer/Services/TransformService.js";

function createService(active)
{
	const wrapper = composerWrapper({getActiveObject: () => active});
	return {wrapper, service: new TransformService(wrapper)};
}

Deno.test('the arrow keys move the selected object by 50 pixels', () =>
{
	const object = new FakeFabricObject({left: 100, top: 100});
	const page   = createService(object);

	page.service.moveActiveObject('ArrowLeft');
	assertEquals([object.left, object.top], [50, 100]);
	page.service.moveActiveObject('ArrowRight');
	page.service.moveActiveObject('ArrowRight');
	assertEquals([object.left, object.top], [150, 100]);
	page.service.moveActiveObject('ArrowUp');
	assertEquals([object.left, object.top], [150, 50]);
	page.service.moveActiveObject('ArrowDown');
	page.service.moveActiveObject('ArrowDown');
	assertEquals([object.left, object.top], [150, 150]);
});

Deno.test('a move is one step of the history', () =>
{
	const object = new FakeFabricObject({left: 0, top: 0});
	const page   = createService(object);

	page.service.moveActiveObject('ArrowDown', 1);

	assertEquals(object.top, 1);
	assertEquals(callNames(page.wrapper), ['getActiveObject', 'fireObjectModified', 'renderAll']);
	assertEquals(page.wrapper.calls[1][1] === object, true);
});

Deno.test('another key does not move the object', () =>
{
	const object = new FakeFabricObject({left: 10, top: 20});
	const page   = createService(object);

	page.service.moveActiveObject('Enter');

	assertEquals([object.left, object.top], [10, 20]);
});

Deno.test('without a selection the arrow keys do nothing', () =>
{
	for (const active of [null, undefined])
	{
		const page = createService(active);

		page.service.moveActiveObject('ArrowLeft');

		assertEquals(callNames(page.wrapper), ['getActiveObject']);
	}
});
