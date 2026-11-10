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

import {assertEquals, assertInstanceOf}               from "@std/assert";
import {FakeFabricWrapper, FakeObject, useFakeFabric} from "@support/ComposerPropertiesFakes.js";
import {ShadowPropertiesService}                      from "@js/templates/canvas-composer/Services/Properties/ShadowPropertiesService.js";

function createService(shadow = null)
{
	const wrapper  = new FakeFabricWrapper();
	const object   = new FakeObject({type: 'rect', shadow});
	wrapper.active = object;
	return {wrapper, object, service: new ShadowPropertiesService(wrapper)};
}

Deno.test('a new shadow is grey, blurred and shifted to the bottom right', () =>
{
	using _fabric = useFakeFabric();
	const {wrapper, object, service} = createService();

	service.create();

	assertInstanceOf(object.shadow, fabric.Shadow);
	assertEquals({...object.shadow}, {color: '#aaaaaa', blur: 10, offsetX: 20, offsetY: 20});
	assertEquals(service.hasShadow(), true);
	assertEquals(wrapper.renders, 1);
});

Deno.test('a deleted shadow is gone', () =>
{
	const {wrapper, object, service} = createService({color: '#000', blur: 1, offsetX: 1, offsetY: 1});

	service.delete();

	assertEquals(object.shadow, null);
	assertEquals(service.hasShadow(), false);
	assertEquals(wrapper.renders, 1);
});

Deno.test('the values of the shadow are read and changed', () =>
{
	const {wrapper, object, service} = createService({color: '#111111', blur: 5, offsetX: 3, offsetY: 4});

	assertEquals([service.getColor(), service.getOffsetX(), service.getOffsetY(), service.getBlur()], ['#111111', 3, 4, 5]);

	service.setColor('#222222');
	service.setOffsetX(7);
	service.setOffsetY(8);
	service.setBlur(9);

	assertEquals({...object.shadow}, {color: '#222222', blur: 9, offsetX: 7, offsetY: 8});
	assertEquals(wrapper.modified.length, 4);
});

Deno.test('without a shadow the panel shows a transparent colour and zeros', () =>
{
	for (const shadow of [null, undefined])
	{
		const {service} = createService(shadow);

		assertEquals(service.hasShadow(), false, String(shadow));
		assertEquals([service.getColor(), service.getOffsetX(), service.getOffsetY(), service.getBlur()],
			['rgba(0, 0, 0, 0)', 0, 0, 0], String(shadow));
	}
});
