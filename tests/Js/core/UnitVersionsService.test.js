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
import {UnitVersionsService}              from "@js/core/UnitVersionsService.js";

Deno.test('the versions of a unit are loaded with a GET', async () =>
{
	const answer = {success: true, versions: []};
	const client = new FakeFetchClient(answer);

	assertStrictEquals(await new UnitVersionsService(client).load('playlists', 7), answer);

	assertEquals(client.requests[0].url, '/async/playlists/versions/7');
	assertEquals(client.requests[0].options.method, 'GET');
	assertEquals('body' in client.requests[0].options, false);
});

Deno.test('a version is restored with a POST and the CSRF token', async () =>
{
	using _dom   = createDom('<meta name="csrf-token" content="the-token">');
	const client = new FakeFetchClient({success: true, redirect: '/'});

	await new UnitVersionsService(client).restore('templates', 7, 3);

	assertEquals(client.requests[0].url, '/async/templates/versions/7');
	assertEquals(client.requests[0].options.method, 'POST');
	assertEquals(client.bodyOf(), {version: 3, csrf_token: 'the-token'});
});

Deno.test('the module and the unit are encoded in the address', async () =>
{
	const client = new FakeFetchClient({success: true});

	await new UnitVersionsService(client).load('play/lists', 'a b?');

	assertEquals(client.requests[0].url, '/async/play%2Flists/versions/a%20b%3F');
});
