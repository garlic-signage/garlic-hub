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
import {ItemsService}                     from "@js/playlists/compose/standard/items/ItemsService.js";

const CSRF_META = '<meta name="csrf-token" content="the-token">';

/**
 * Every method with its call and the request it must send: body without the CSRF token, null for a GET.
 */
const REQUESTS = [
	{
		name: 'insertMedia', call: (service) => service.insertMedia('abc', 7, 3),
		url: '/async/playlists/items/insert', method: 'POST', body: {playlist_id: 7, id: 'abc', position: 3, source: 'mediapool'}
	},
	{
		name: 'insertPlaylist', call: (service) => service.insertPlaylist(9, 7, 3),
		url: '/async/playlists/items/insert', method: 'POST', body: {playlist_id: 7, id: 9, position: 3, source: 'playlist'}
	},
	{
		name: 'insertTemplate', call: (service) => service.insertTemplate(4, 7, 3),
		url: '/async/playlists/items/insert', method: 'POST', body: {playlist_id: 7, id: 4, position: 3, source: 'template'}
	},
	{
		name: 'editItem', call: (service) => service.editItem(12, 'item_duration', '30'),
		url: '/async/playlists/item', method: 'PATCH', body: {item_id: 12, name: 'item_duration', value: '30'}
	},
	{
		name: 'updateItemsOrders', call: (service) => service.updateItemsOrders(7, {12: 1, 13: 2}),
		url: '/async/playlists/items', method: 'PATCH', body: {playlist_id: 7, items_positions: {12: 1, 13: 2}}
	},
	{
		name: 'fetchDefaultSeconds', call: (service) => service.fetchDefaultSeconds(12),
		url: '/async/playlists/item/12', method: 'GET', body: null
	},
	{
		name: 'delete', call: (service) => service.delete(7, 12),
		url: '/async/playlists/items', method: 'DELETE', body: {playlist_id: 7, item_id: 12}
	},
	{
		name: 'loadPreview', call: (service) => service.loadPreview(7),
		url: '/async/playlists/items/preview/7', method: 'GET', body: null
	},
	{
		name: 'loadByPlaylistId', call: (service) => service.loadByPlaylistId(7),
		url: '/async/playlists/items/load/7', method: 'GET', body: null
	},
];

Deno.test('every method sends its request and returns the answer unchanged', async (t) =>
{
	using _dom = createDom(CSRF_META);

	for (const request of REQUESTS)
	{
		await t.step(request.name, async () =>
		{
			const answer = {success: true, data: request.name};
			const client = new FakeFetchClient(answer);

			assertStrictEquals(await request.call(new ItemsService(client)), answer);

			const {url, options} = client.requests[0];
			assertEquals([url, options.method], [request.url, request.method]);
			if (request.body === null)
				assertEquals('body' in options, false);
			else
				assertEquals(client.bodyOf(), {...request.body, csrf_token: 'the-token'});
		});
	}
});

Deno.test('a failed request comes back as answer with its message', async () =>
{
	using _dom   = createDom(CSRF_META);
	const client = new FakeFetchClient(new Error('Timeout: Server did not respond.'));

	assertEquals(await new ItemsService(client).delete(7, 12), {success: false, error_message: 'Timeout: Server did not respond.'});
});
