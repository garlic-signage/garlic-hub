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

import {assertEquals, assertThrows}              from "@std/assert";
import {FakeFabricWrapper, FakeObject, FakeText} from "@support/ComposerPropertiesFakes.js";
import {BasePropertyService}                     from "@js/templates/canvas-composer/Services/Properties/BasePropertyService.js";

/**
 * The protected methods are used by the services of the panels, the test reaches them through a subclass.
 */
class TestService extends BasePropertyService
{
	find()                          { return this._findActiveObject(); }
	get()                           { return this._getActiveObject(); }
	style(object, property, value)  { this._setTextStyle(object, property, value); }
	update(object)                  { this._updateCanvas(object); }
}

function createService()
{
	const wrapper = new FakeFabricWrapper();
	return {wrapper, service: new TestService(wrapper)};
}

Deno.test('the service keeps the wrapper of the canvas', () =>
{
	const {wrapper, service} = createService();

	assertEquals(service.fabricWrapper === wrapper, true);
});

Deno.test('without a selection there is no active object', () =>
{
	const {wrapper, service} = createService();

	assertEquals(service.find(), null);
	wrapper.active = undefined;
	assertEquals(service.find(), null);
	assertThrows(() => service.get(), Error, 'No active object');
});

Deno.test('the selected object is the active object', () =>
{
	const {wrapper, service} = createService();
	const object   = new FakeObject({type: 'rect'});
	wrapper.active = object;

	assertEquals(service.find() === object, true);
	assertEquals(service.get() === object, true);
});

Deno.test('a style without a selection of characters is set on the whole text', () =>
{
	const {service} = createService();
	const text = new FakeText({fill: '#000000'});
	text.charStyles[1].fill = '#ff0000';

	service.style(text, 'fill', '#00ff00');

	assertEquals(text.fill, '#00ff00');
	// the styles of the characters would hide the value of the object
	assertEquals(text.charStyles.every(style => style.fill === undefined), true);
	assertEquals(text.calls, [['removeStyle', 'fill']]);
});

Deno.test('a style with a selection of characters is set only for them', () =>
{
	const {service} = createService();
	const text = new FakeText({fill: '#000000', selectionStart: 1, selectionEnd: 3});

	service.style(text, 'fill', '#00ff00');

	assertEquals(text.fill, '#000000');
	assertEquals(text.charStyles.map(style => style.fill ?? null), [null, '#00ff00', '#00ff00', null, null]);
});

Deno.test('a changed object is reported and the canvas drawn again', () =>
{
	const {wrapper, service} = createService();
	const object = new FakeObject();

	service.update(object);

	assertEquals(wrapper.modified.length, 1);
	assertEquals(wrapper.modified[0] === object, true);
	assertEquals(wrapper.renders, 1);
});
