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


import {assertEquals, assertRejects, assertStrictEquals} from "@std/assert";
import {createDom}                                       from "@support/dom.js";
import {FakeFetchClient}                                 from "@support/FakeFetchClient.js";
import {replaceGlobals}                                  from "@support/globals.js";
import {FetchClient}                                     from "@js/core/FetchClient.js";
import {PlaylistsService}                                from "@js/playlists/compose/standard/playlists/PlaylistsService.js";

const CSRF_META = '<meta name="csrf-token" content="the-token">';

/**
 * Every method with its call and the request it must send: body without the CSRF token, null for a GET.
 */
const REQUESTS = [
	{
		name: 'delete', call: (service) => service.delete(7),
		url: '/async/playlists', method: 'DELETE', body: {playlist_id: 7}
	},
	{
		name: 'determineRights', call: (service) => service.determineRights(7),
		url: '/async/playlists/acls/7', method: 'GET', body: null
	},
	{
		name: 'toggleShuffle', call: (service) => service.toggleShuffle(7),
		url: '/async/playlists/shuffle', method: 'PATCH', body: {playlist_id: 7}
	},
	{
		name: 'shufflePicking', call: (service) => service.shufflePicking(7, '3'),
		url: '/async/playlists/picking', method: 'PATCH', body: {playlist_id: 7, shuffle_picking: '3'}
	},
	{
		name: 'export', call: (service) => service.export(7),
		url: '/async/playlists', method: 'PUT', body: {playlist_id: 7}
	},
	{
		name: 'requestRelease', call: (service) => service.requestRelease(7),
		url: '/async/playlists/release', method: 'PATCH', body: {playlist_id: 7}
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

			assertStrictEquals(await request.call(new PlaylistsService(client)), answer);

			const {url, options} = client.requests[0];
			assertEquals([url, options.method], [request.url, request.method]);
			if (request.body === null)
				assertEquals('body' in options, false);
			else
				assertEquals(client.bodyOf(), {...request.body, csrf_token: 'the-token'});
		});
	}
});

Deno.test('the AI suggests the media of a theme and waits for two questions', async () =>
{
	using _dom   = createDom(CSRF_META);
	const items  = [{media_id: 'abc'}, {media_id: 'def'}];
	const client = new FakeFetchClient({success: true, items});

	assertEquals(await new PlaylistsService(client).suggestWithAi(7, 'summer', 10), items);

	const {url, options} = client.requests[0];
	assertEquals([url, options.method, options.timeout], ['/async/playlists/ai/suggest', 'POST', 2 * FetchClient.LLM_TIMEOUT]);
	assertEquals(client.bodyOf(), {playlist_id: 7, theme: 'summer', minutes: 10, csrf_token: 'the-token'});
});

Deno.test('a failed suggestion of the AI fails with the message', async () =>
{
	using _dom = createDom(CSRF_META);

	for (const answer of [{success: false, error_message: 'No model'}, new Error('Timeout')])
	{
		const service = new PlaylistsService(new FakeFetchClient(answer));
		await assertRejects(() => service.suggestWithAi(7, 'summer', 10), Error, answer.message ?? answer.error_message);
	}
});

Deno.test('the playlists of a mode are found by their name', async () =>
{
	const requested = [];
	const playlists = [{playlist_id: 3, playlist_name: 'Lobby'}];
	using _fetch    = replaceGlobals({fetch: async (url) => { requested.push(url); return {json: async () => playlists}; }});

	assertEquals(await new PlaylistsService(new FakeFetchClient({})).findPlaylists('internal', ''), playlists);

	assertEquals(requested, ['/async/playlists/find/internal/']);
});

Deno.test('the name of a zone playlist is found by its id', async () =>
{
	const client = new FakeFetchClient({playlist_id: 3, name: 'Lobby'});

	assertEquals(await new PlaylistsService(client).findNameById('3/a'), 'Lobby');

	assertEquals(client.requests[0].url, '/async/playlists/find/3%2Fa');
	assertEquals(client.requests[0].options.method, 'GET');
});

Deno.test('a playlist that is not found or not usable has no name', async () =>
{
	for (const answer of [{}, {success: false, error_message: 'Not found'}, {playlist_id: 3}, {playlist_id: 3, name: null}, new Error('Timeout')])
	{
		const service = new PlaylistsService(new FakeFetchClient(answer));
		assertEquals(await service.findNameById(3), '', JSON.stringify(answer));
	}
});
