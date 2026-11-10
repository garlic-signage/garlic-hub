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
import {TriggerService}                   from "@js/playlists/compose/standard/editors/TriggerService.js";

Deno.test('the editor of an item is loaded with a GET', async () =>
{
	const answer = {success: true, html: '<p>editor</p>'};
	const client = new FakeFetchClient(answer);

	assertStrictEquals(await new TriggerService(client).fetchBeginTrigger(42), answer);

	assertEquals(client.requests[0].url, '/async/playlists/item/begin-trigger/42');
	assertEquals(client.requests[0].options.method, 'GET');
	assertEquals('body' in client.requests[0].options, false);
});

Deno.test('the values are saved with a PATCH, the item id and the CSRF token', async () =>
{
	using _dom   = createDom('<meta name="csrf-token" content="the-token">');
	const answer = {success: true};
	const client = new FakeFetchClient(answer);

	assertStrictEquals(await new TriggerService(client).storeBeginTrigger(42, {wallclocks: [{iso_date: '2026-01-01T08:00'}]}), answer);

	assertEquals(client.requests[0].url, '/async/playlists/item/begin-trigger');
	assertEquals(client.requests[0].options.method, 'PATCH');
	assertEquals(client.bodyOf(), {...{wallclocks: [{iso_date: '2026-01-01T08:00'}]}, item_id: 42, csrf_token: 'the-token'});
});

Deno.test('a failed request comes back as error', async () =>
{
	const service = new TriggerService(new FakeFetchClient(new Error('Timeout: Server did not respond.')));

	assertEquals(await service.fetchBeginTrigger(42), {success: false, error_message: 'Timeout: Server did not respond.'});
});
