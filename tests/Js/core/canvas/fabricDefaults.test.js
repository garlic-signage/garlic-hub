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
 * The module sets the defaults of fabric.js when it is loaded: it is loaded with a fake of fabric, under its own
 * address so no other test has loaded it before.
 */
const configured = [];
const fabric     = {
	FabricObject: {ownDefaults: {originX: 'center', originY: 'center'}},
	config:       {configure: (options) => configured.push(options)}
};
{
	using _globals = replaceGlobals({fabric});
	await import("@js/core/canvas/fabricDefaults.js?test=defaults");
}

Deno.test('the position of an object is its top left corner, like up to fabric.js 6', () =>
{
	assertEquals([fabric.FabricObject.ownDefaults.originX, fabric.FabricObject.ownDefaults.originY], ['left', 'top']);
});

Deno.test('numbers are stored with 6 decimals', () =>
{
	assertEquals(configured, [{NUM_FRACTION_DIGITS: 6}]);
});
