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

import {assertEquals}               from "@std/assert";
import {MultizoneFakeFabricWrapper} from "@support/MultizoneFakeFabricWrapper.js";
import {ViewportService}            from "@js/playlists/compose/multizone/Services/ViewportService.js";

Deno.test('a new viewport has no size', () =>
{
	const service = new ViewportService(new MultizoneFakeFabricWrapper());

	assertEquals([service.width, service.height], [0, 0]);
});

Deno.test('the size of the screen is the size of the canvas', () =>
{
	const canvas  = new MultizoneFakeFabricWrapper();
	const service = new ViewportService(canvas);

	service.setDimensions(1920, 1080);

	assertEquals([service.width, service.height], [1920, 1080]);
	assertEquals(canvas.calls, [['setWidth', 1920], ['setHeight', 1080]]);
	assertEquals(canvas.renders, 1);
});

Deno.test('the zoom scales the canvas and its size, the screen keeps its size', () =>
{
	const canvas  = new MultizoneFakeFabricWrapper();
	const service = new ViewportService(canvas);
	service.setDimensions(1920, 1080);
	canvas.calls = [];

	service.scaleCanvas(35);

	assertEquals(canvas.calls, [['setZoom', 0.35], ['setWidth', 672], ['setHeight', 378]]);
	assertEquals([service.width, service.height], [1920, 1080]);
});

Deno.test('the scaled size is rounded down', () =>
{
	const canvas  = new MultizoneFakeFabricWrapper();
	const service = new ViewportService(canvas);
	service.setDimensions(1366, 768);
	canvas.calls = [];

	service.scaleCanvas(33);

	assertEquals(canvas.calls.slice(1), [['setWidth', 450], ['setHeight', 253]]);
});
