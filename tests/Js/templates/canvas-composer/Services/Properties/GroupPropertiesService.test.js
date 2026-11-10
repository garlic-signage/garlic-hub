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

import {assertEquals, assertInstanceOf}                                                           from "@std/assert";
import {FakeActiveSelection, FakeCanvas, FakeFabricWrapper, FakeGroup, FakeObject, useFakeFabric} from "@support/ComposerPropertiesFakes.js";
import {GroupPropertiesService}                                                                   from "@js/templates/canvas-composer/Services/Properties/GroupPropertiesService.js";

/**
 * A canvas with the objects a to e in this order, the test selects some of them.
 */
function createService()
{
	const wrapper = new FakeFabricWrapper();
	const objects = Object.fromEntries(['a', 'b', 'c', 'd', 'e'].map(name => [name, new FakeObject({name})]));
	wrapper.fabricCanvas = new FakeCanvas(Object.values(objects));
	return {wrapper, objects, canvas: wrapper.fabricCanvas, service: new GroupPropertiesService(wrapper)};
}

function names(objects)
{
	return objects.map(object => object.name ?? object.type);
}

Deno.test('the selected objects become a group, selected in their place', () =>
{
	using _fabric = useFakeFabric();
	const {wrapper, objects, canvas, service} = createService();
	const selection = new FakeActiveSelection([objects.b, objects.e]);

	service.toGroup(selection);

	assertEquals(names(canvas.getObjects()), ['a', 'c', 'd', 'group']);
	const group = canvas.getObjects()[3];
	assertInstanceOf(group, fabric.Group);
	assertEquals(names(group.getObjects()), ['b', 'e']);
	assertEquals(canvas.active === group, true);
	assertEquals(canvas.calls, ['discardActiveObject']);
	assertEquals(selection.getObjects(), []);
	assertEquals(wrapper.modified[0] === group, true);
	assertEquals(wrapper.renders, 1);
});

Deno.test('a group of the topmost objects stays on top', () =>
{
	using _fabric = useFakeFabric();
	const {objects, canvas, service} = createService();

	service.toGroup(new FakeActiveSelection([objects.d, objects.e]));

	assertEquals(names(canvas.getObjects()), ['a', 'b', 'c', 'group']);
});

Deno.test('the objects of a group take its place and are selected', () =>
{
	using _fabric = useFakeFabric();
	const {wrapper, objects, canvas, service} = createService();
	const group = new FakeGroup([objects.b, objects.c]);
	canvas.objects = [objects.a, group, objects.d];

	service.toActiveSelection(group);

	assertEquals(names(canvas.getObjects()), ['a', 'b', 'c', 'd']);
	assertInstanceOf(canvas.active, fabric.ActiveSelection);
	assertEquals(names(canvas.active.getObjects()), ['b', 'c']);
	assertEquals(canvas.active.canvas === canvas, true);
	assertEquals(wrapper.modified[0] === objects.b, true);
});
