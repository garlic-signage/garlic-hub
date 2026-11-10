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

import {assertEquals, assertThrows}                   from "@std/assert";
import {FakeFabricWrapper, FakeObject, useFakeFabric} from "@support/ComposerPropertiesFakes.js";
import {GlobalPropertiesService}                      from "@js/templates/canvas-composer/Services/Properties/GlobalPropertiesService.js";

/**
 * A selected rectangle of 200 x 100 with its centre at 300/200 on a template of 1000 x 500.
 */
function createService(options = {})
{
	const wrapper = new FakeFabricWrapper();
	const service = new GlobalPropertiesService(wrapper);
	service.setCanvasDimension(1000, 500);
	const object  = new FakeObject({
		type: 'rect', opacity: 1, stroke: '#000000', strokeWidth: 2, scaleX: 1, scaleY: 1, moved: null,
		getBoundingRect: () => ({width: 200, height: 100}),
		getCenterPoint: () => ({x: 300, y: 200}),
		setPositionByOrigin(point, originX, originY) { this.moved = [point.x, point.y, originX, originY]; },
		...options
	});
	wrapper.active = object;
	return {wrapper, service, object};
}

Deno.test('the opacity is shown in percent and set from percent', () =>
{
	const {wrapper, service, object} = createService({opacity: 0.5});

	assertEquals(service.getOpacity(), 50);

	service.setOpacity('80');
	assertEquals(object.opacity, 0.8);
	assertEquals(wrapper.modified.length, 1);
	assertEquals(wrapper.renders, 1);
});

Deno.test('the outline takes its colour and width', () =>
{
	const {wrapper, service, object} = createService();

	assertEquals([service.getStrokeColor(), service.getStrokeWidth()], ['#000000', 2]);

	service.setStrokeColor('#ff0000');
	service.setStrokeWidth('7');

	assertEquals([object.stroke, object.strokeWidth], ['#ff0000', 7]);
	assertEquals(wrapper.renders, 2);
});

Deno.test('the scale is read and set as number', () =>
{
	const {service, object} = createService({scaleX: 2, scaleY: 0.5});

	assertEquals([service.getScaleX(), service.getScaleY()], [2, 0.5]);

	service.setScaleX('1.5');
	service.setScaleY('3');

	assertEquals([object.scaleX, object.scaleY], [1.5, 3]);
});

Deno.test('the object is moved to the edges and the middle of the template', () =>
{
	using _fabric = useFakeFabric();
	const expected = {
		top: [300, 50], middle: [300, 250], bottom: [300, 450],
		left: [100, 200], center: [500, 200], right: [900, 200]
	};

	for (const [position, [x, y]] of Object.entries(expected))
	{
		const {wrapper, service, object} = createService();

		service.setPosition(position);

		assertEquals(object.moved, [x, y, 'center', 'center'], position);
		assertEquals(wrapper.modified.length, 1, position);
	}
});

Deno.test('an unknown position leaves the object where it is', () =>
{
	using _fabric = useFakeFabric();
	const {service, object} = createService();

	service.setPosition('diagonal');

	assertEquals(object.moved, null);
});

Deno.test('without a selection there is nothing to read or to set', () =>
{
	const {wrapper, service} = createService();
	wrapper.active = null;

	assertThrows(() => service.getOpacity(), Error, 'No active object');
	assertThrows(() => service.setStrokeColor('#fff'), Error, 'No active object');
	assertEquals(wrapper.renders, 0);
});
