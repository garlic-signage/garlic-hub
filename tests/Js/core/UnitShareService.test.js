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

import {assertEquals, assertStrictEquals} from "@std/assert";
import {createDom}                        from "@support/dom.js";
import {FakeFetchClient}                  from "@support/FakeFetchClient.js";
import {UnitShareService}                 from "@js/core/UnitShareService.js";

Deno.test('the users of a unit are loaded with a GET', async () =>
{
	const answer = {success: true, users: []};
	const client = new FakeFetchClient(answer);

	assertStrictEquals(await new UnitShareService(client).load('playlists', 7), answer);

	assertEquals(client.requests[0].url, '/async/acl/playlists/units/7');
	assertEquals(client.requests[0].options.method, 'GET');
	assertEquals('body' in client.requests[0].options, false);
});

Deno.test('a role is saved with a PUT and the CSRF token, an empty one removes it', async () =>
{
	using _dom   = createDom('<meta name="csrf-token" content="the-token">');
	const client = new FakeFetchClient({success: true});
	const service = new UnitShareService(client);

	await service.save('mediapool', 12, 4, 'editor');
	await service.save('mediapool', 12, 4, '');

	assertEquals(client.requests[0].url, '/async/acl/mediapool/units/12');
	assertEquals(client.requests[0].options.method, 'PUT');
	assertEquals(client.bodyOf(0), {UID: 4, role: 'editor', csrf_token: 'the-token'});
	assertEquals(client.bodyOf(1), {UID: 4, role: '', csrf_token: 'the-token'});
});

Deno.test('the module and the unit are encoded in the addresses', async () =>
{
	const client = new FakeFetchClient({success: true});

	await new UnitShareService(client).load('play/lists', 'a b?');

	assertEquals(client.requests[0].url, '/async/acl/play%2Flists/units/a%20b%3F');
	assertEquals(UnitShareService.findUrl('play/lists'), '/async/acl/play%2Flists/find/');
});
