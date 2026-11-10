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
import {UserService}                      from "@js/users/UserService.js";

const CSRF_META = '<meta name="csrf-token" content="the-token">';

/**
 * Every method with its call and the request it must send: body without the CSRF token, null for a GET.
 */
const REQUESTS = [
	{
		name: 'determineRights', call: (service) => service.determineRights(7),
		url: '/async/users/acls/7', method: 'GET', body: null
	},
	{
		name: 'deleteUser', call: (service) => service.deleteUser(7),
		url: '/async/users', method: 'DELETE', body: {UID: 7}
	},
	{
		name: 'setRole granted', call: (service) => service.setRole(7, 'playlists', 'editor', true),
		url: '/async/users/rights/7', method: 'PUT', body: {module: 'playlists', role: 'editor', granted: '1'}
	},
	{
		name: 'setRole removed', call: (service) => service.setRole(7, 'playlists', 'editor', false),
		url: '/async/users/rights/7', method: 'PUT', body: {module: 'playlists', role: 'editor', granted: '0'}
	},
];

Deno.test('every method sends its request and returns the answer unchanged', async (t) =>
{
	using _dom = createDom(CSRF_META);

	for (const expected of REQUESTS)
	{
		await t.step(expected.name, async () =>
		{
			const answer = {success: true, data: 'x'};
			const client = new FakeFetchClient(answer);

			assertStrictEquals(await expected.call(new UserService(client)), answer);

			const {url, options} = client.requests[0];
			assertEquals(client.requests.length, 1);
			assertEquals(url, expected.url);
			assertEquals(options.method, expected.method);
			assertEquals(options.headers, {'Content-Type': 'application/json'});

			if (expected.body === null)
				assertEquals('body' in options, false);
			else
				assertEquals(client.bodyOf(), {...expected.body, csrf_token: 'the-token'});
		});
	}
});

Deno.test('a failed request comes back as error answer, so the caller shows the message', async (t) =>
{
	using _dom = createDom(CSRF_META);

	for (const expected of REQUESTS)
	{
		await t.step(expected.name, async () =>
		{
			const service = new UserService(new FakeFetchClient(new Error('Network error: Failed to fetch')));

			assertEquals(await expected.call(service), {success: false, error_message: 'Network error: Failed to fetch'});
		});
	}
});

Deno.test('a change without CSRF token is not sent, but answered with an error', async (t) =>
{
	using _dom = createDom();

	for (const expected of REQUESTS.filter(request => request.method !== 'GET'))
	{
		await t.step(expected.name, async () =>
		{
			const client = new FakeFetchClient({success: true});

			assertEquals(await expected.call(new UserService(client)), {success: false, error_message: 'No CSRF token found in meta tag'});
			assertEquals(client.requests, []);
		});
	}
});
