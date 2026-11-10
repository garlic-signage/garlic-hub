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
import {TokensService}                    from "@js/users/edit/tokens/TokensService.js";

const CSRF_META = '<meta name="csrf-token" content="the-token">';

/**
 * Every method with its call and the request it must send: body without the CSRF token, null for a GET.
 */
const REQUESTS = [
	{
		name: 'delete', call: (service) => service.delete(7, 'initial_password'),
		url: '/async/users/tokens', method: 'DELETE', body: {UID: 7, purpose: 'initial_password'}
	},
	{
		name: 'refresh', call: (service) => service.refresh(7, 'initial_password'),
		url: '/async/users/tokens', method: 'POST', body: {UID: 7, purpose: 'initial_password'}
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

			assertStrictEquals(await expected.call(new TokensService(client)), answer);

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
			const service = new TokensService(new FakeFetchClient(new Error('Network error: Failed to fetch')));

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

			assertEquals(await expected.call(new TokensService(client)), {success: false, error_message: 'No CSRF token found in meta tag'});
			assertEquals(client.requests, []);
		});
	}
});
