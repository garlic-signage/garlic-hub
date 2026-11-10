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
import {assertEquals, assertInstanceOf} from "@std/assert";
import {TypeFactory}                    from "@js/playlists/compose/standard/editors/types/TypeFactory.js";
import {WallclockEdit}                  from "@js/playlists/compose/standard/editors/types/WallclockEdit.js";
import {AccesskeyEdit}                  from "@js/playlists/compose/standard/editors/types/AccesskeyEdit.js";
import {TouchEdit}                      from "@js/playlists/compose/standard/editors/types/TouchEdit.js";
import {NotifyEdit}                     from "@js/playlists/compose/standard/editors/types/NotifyEdit.js";

Deno.test('every kind of trigger gets its editor', () =>
{
	const factory = new TypeFactory({});

	assertInstanceOf(factory.create('wallclock'), WallclockEdit);
	assertInstanceOf(factory.create('accesskey'), AccesskeyEdit);
	assertInstanceOf(factory.create('touch'), TouchEdit);
	assertInstanceOf(factory.create('notify'), NotifyEdit);
});

Deno.test('every call gives a new editor', () =>
{
	const factory = new TypeFactory({});

	assertEquals(factory.create('notify') === factory.create('notify'), false);
});

Deno.test('an unknown kind gives no editor', () =>
{
	assertEquals(new TypeFactory({}).create('shake'), undefined);
});
