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

import {assertEquals}   from "@std/assert";
import {replaceGlobals} from "@support/globals.js";

/**
 * The module changes the controls of the images of fabric.js when it is loaded: it is loaded with fakes of fabric
 * and of the extensions, under its own address so no other test has loaded it before.
 */
const fabric = {
	FabricObject: {createControls: () => ({controls: {tl: 'corner tl', br: 'corner br', ml: 'edge ml', mr: 'edge mr', mt: 'edge mt', mb: 'edge mb'}})},
	FabricImage: {}
};
const fabricExtensions = {
	createImageResizeControlsWithScaleToCover: () => ({mle: 'cover left', mre: 'cover right', mte: 'cover top', mbe: 'cover bottom'})
};
{
	using _globals = replaceGlobals({fabric, fabricExtensions});
	await import("@js/templates/canvas-composer/Utils/templateFabricDefaults.js?test=defaults");
}

Deno.test('the edges of an image keep the picture filling its frame, the corners stay', () =>
{
	using _globals = replaceGlobals({fabric, fabricExtensions});

	const {controls} = fabric.FabricImage.createControls();

	assertEquals(controls, {
		tl: 'corner tl', br: 'corner br', ml: 'cover left', mr: 'cover right', mt: 'cover top', mb: 'cover bottom'
	});
});

Deno.test('the controls of the extension have the names of the edges of fabric.js', () =>
{
	using _globals = replaceGlobals({fabric, fabricExtensions});

	const names = Object.keys(fabric.FabricImage.createControls().controls);

	assertEquals(names.filter(name => name.endsWith('e') && name.length === 3), []);
});
