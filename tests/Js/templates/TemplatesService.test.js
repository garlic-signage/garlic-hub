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
import {FetchClient}                      from "@js/core/FetchClient.js";
import {TemplatesService}                 from "@js/templates/TemplatesService.js";

const CSRF_META = '<meta name="csrf-token" content="the-token">';

/**
 * Every method with its call and the request it must send: body without the CSRF token, null for a GET.
 */
const REQUESTS = [
	{
		name: 'loadTemplateContent', call: (service) => service.loadTemplateContent(7),
		url: '/async/templates/7', method: 'GET', body: null
	},
	{
		name: 'loadPlaylistItemContent', call: (service) => service.loadPlaylistItemContent(12),
		url: '/async/playlist-item/composer/load/12', method: 'GET', body: null
	},
	{
		name: 'find', call: (service) => service.find(),
		url: '/async/templates/find', method: 'GET', body: null
	},
	{
		name: 'determineRights', call: (service) => service.determineRights(7),
		url: '/async/templates/acls/7', method: 'GET', body: null
	},
	{
		name: 'delete', call: (service) => service.delete(7),
		url: '/async/templates', method: 'DELETE', body: {template_id: 7}
	},
	{
		name: 'installPack', call: (service) => service.installPack(),
		url: '/async/templates/pack', method: 'POST', body: {}
	},
	{
		name: 'copy', call: (service) => service.copy(7),
		url: '/async/templates/copy', method: 'POST', body: {template_id: 7}
	},
	{
		name: 'saveTemplateContent', call: (service) => service.saveTemplateContent(7, '{"objects":[]}', 'data:image/png;base64,AAAA'),
		url: '/async/templates', method: 'PATCH', body: {template_id: 7, content: '{"objects":[]}', image: 'data:image/png;base64,AAAA'}
	},
	{
		name: 'savePlaylistItemContent', call: (service) => service.savePlaylistItemContent(12, '{"objects":[]}', 'data:image/png;base64,AAAA'),
		url: '/async/playlist-item/composer/save', method: 'PATCH', body: {item_id: 12, content: '{"objects":[]}', image: 'data:image/png;base64,AAAA'}
	},
	{
		name: 'correctText', call: (service) => service.correctText('Helo'),
		url: '/async/templates/correct-text', method: 'POST', body: {text: 'Helo'}, timeout: FetchClient.LLM_TIMEOUT
	},
	{
		name: 'shortenText', call: (service) => service.shortenText('A long text', 6),
		url: '/async/templates/shorten-text', method: 'POST', body: {text: 'A long text', max_length: 6}, timeout: FetchClient.LLM_TIMEOUT
	},
	{
		name: 'translateText', call: (service) => service.translateText('Hello', 'de_DE'),
		url: '/async/templates/translate-text', method: 'POST', body: {text: 'Hello', locale: 'de_DE'}, timeout: FetchClient.LLM_TIMEOUT
	},
	{
		name: 'suggestImages', call: (service) => service.suggestImages(['Lobby', 'Welcome']),
		url: '/async/templates/suggest-images', method: 'POST', body: {texts: ['Lobby', 'Welcome']}, timeout: FetchClient.LLM_TIMEOUT
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

			assertStrictEquals(await expected.call(new TemplatesService(client)), answer);

			const {url, options} = client.requests[0];
			assertEquals(client.requests.length, 1);
			assertEquals(url, expected.url);
			assertEquals(options.method, expected.method);
			assertEquals(options.headers, {'Content-Type': 'application/json'});
			assertEquals(options.timeout, expected.timeout);

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
			const service = new TemplatesService(new FakeFetchClient(new Error('Network error: Failed to fetch')));

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

			assertEquals(await expected.call(new TemplatesService(client)), {success: false, error_message: 'No CSRF token found in meta tag'});
			assertEquals(client.requests, []);
		});
	}
});
