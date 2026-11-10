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

import {assertEquals, assertRejects} from "@std/assert";
import {createDom}                   from "@support/dom.js";
import {FakeFetchClient}             from "@support/FakeFetchClient.js";
import {FetchClient}                 from "@js/core/FetchClient.js";
import {MediaService}                from "@js/mediapool/media/MediaService.js";

const CSRF_META = '<meta name="csrf-token" content="the-token">';

/**
 * Every method of the service with the arguments for a call.
 */
const METHODS = {
	loadMediaByNodeId:         (service) => service.loadMediaByNodeId(5),
	loadFilteredMediaByNodeId: (service) => service.loadFilteredMediaByNodeId(5, 'images'),
	searchMedia:               (service) => service.searchMedia('lobby'),
	getMediaById:              (service) => service.getMediaById('abc'),
	editMedia:                 (service) => service.editMedia('abc', 'name', 'description', 'tags'),
	suggestTags:               (service) => service.suggestTags('abc'),
	moveMedia:                 (service) => service.moveMedia('abc', 5),
	cloneMedia:                (service) => service.cloneMedia('abc'),
	removeMedia:               (service) => service.removeMedia('abc'),
};

Deno.test('the media of a directory are loaded with a GET without body', async () =>
{
	const client = new FakeFetchClient({success: true, media_list: [{media_id: 'abc'}]});

	const list = await new MediaService(client).loadMediaByNodeId(5);

	assertEquals(list, [{media_id: 'abc'}]);
	assertEquals(client.requests, [{
		url: '/async/mediapool/media/list/5',
		options: {method: 'GET', headers: {'Content-Type': 'application/json'}}
	}]);
});

Deno.test('the filtered media of a directory are loaded', async () =>
{
	const client = new FakeFetchClient({success: true, media_list: []});

	await new MediaService(client).loadFilteredMediaByNodeId(5, 'images');

	assertEquals(client.requests[0].url, '/async/mediapool/media/list/5?filter=images');
});

Deno.test('the term and the filter of a search are encoded', async () =>
{
	const client = new FakeFetchClient({success: true, media_list: [{media_id: 'abc'}]});
	const service = new MediaService(client);

	assertEquals(await service.searchMedia('a&b c', 'images/png'), [{media_id: 'abc'}]);
	await service.searchMedia('lobby');

	assertEquals(client.requests[0].url, '/async/mediapool/media/search?term=a%26b%20c&filter=images%2Fpng');
	assertEquals(client.requests[1].url, '/async/mediapool/media/search?term=lobby&filter=');
});

Deno.test('a medium is loaded by its id', async () =>
{
	const client = new FakeFetchClient({success: true, media: {media_id: 'abc'}});

	assertEquals(await new MediaService(client).getMediaById('abc'), {media_id: 'abc'});
	assertEquals(client.requests[0].url, '/async/mediapool/media/abc');
	assertEquals(client.requests[0].options.method, 'GET');
});

Deno.test('an edit is posted with the CSRF token', async () =>
{
	using _dom   = createDom(CSRF_META);
	const result = {success: true};
	const client = new FakeFetchClient(result);

	assertEquals(await new MediaService(client).editMedia('abc', 'name', 'description', 'a, b'), result);

	const request = client.requests[0];
	assertEquals(request.url, '/async/mediapool/media/edit');
	assertEquals(request.options.method, 'POST');
	assertEquals(client.bodyOf(), {media_id: 'abc', filename: 'name', description: 'description', tags: 'a, b', csrf_token: 'the-token'});
});

Deno.test('the suggested tags wait as long as the AI and return only description and tags', async () =>
{
	using _dom   = createDom(CSRF_META);
	const client = new FakeFetchClient({success: true, description: 'A lobby', tags: ['lobby', 'hall'], other: 'x'});

	assertEquals(await new MediaService(client).suggestTags('abc'), {description: 'A lobby', tags: ['lobby', 'hall']});

	const request = client.requests[0];
	assertEquals(request.url, '/async/mediapool/media/tags');
	assertEquals(request.options.timeout, FetchClient.LLM_TIMEOUT);
	assertEquals(client.bodyOf(), {media_id: 'abc', csrf_token: 'the-token'});
});

Deno.test('a medium is moved to a directory', async () =>
{
	using _dom   = createDom(CSRF_META);
	const result = {success: true};
	const client = new FakeFetchClient(result);

	assertEquals(await new MediaService(client).moveMedia('abc', 5), result);
	assertEquals(client.requests[0].url, '/async/mediapool/media/move');
	assertEquals(client.bodyOf(), {media_id: 'abc', node_id: 5, csrf_token: 'the-token'});
});

Deno.test('a clone returns the new medium', async () =>
{
	using _dom   = createDom(CSRF_META);
	const client = new FakeFetchClient({success: true, new_media: {media_id: 'def'}});

	assertEquals(await new MediaService(client).cloneMedia('abc'), {media_id: 'def'});
	assertEquals(client.requests[0].url, '/async/mediapool/media/clone');
	assertEquals(client.bodyOf(), {media_id: 'abc', csrf_token: 'the-token'});
});

Deno.test('a medium is removed with a DELETE', async () =>
{
	using _dom   = createDom(CSRF_META);
	const result = {success: true};
	const client = new FakeFetchClient(result);

	assertEquals(await new MediaService(client).removeMedia('abc'), result);
	assertEquals(client.requests[0].url, '/async/mediapool/media');
	assertEquals(client.requests[0].options.method, 'DELETE');
	assertEquals(client.bodyOf(), {media_id: 'abc', csrf_token: 'the-token'});
});

Deno.test('every method throws the error message of a failed request', async (t) =>
{
	using _dom = createDom(CSRF_META);

	for (const [name, call] of Object.entries(METHODS))
	{
		await t.step(name, async () =>
		{
			const service = new MediaService(new FakeFetchClient({success: false, error_message: 'Not allowed'}));

			await assertRejects(() => call(service), Error, 'Not allowed');
		});
	}
});

Deno.test('every method throws when the answer is no success', async (t) =>
{
	using _dom = createDom(CSRF_META);

	for (const [name, call] of Object.entries(METHODS))
	{
		await t.step(name, async () =>
		{
			for (const answer of ['<p>html</p>', {media_list: []}, {success: 'true'}])
			{
				await assertRejects(() => call(new MediaService(new FakeFetchClient(answer))), Error);
			}
		});
	}
});

Deno.test('every method throws the message of a timeout or network error', async (t) =>
{
	using _dom = createDom(CSRF_META);

	for (const [name, call] of Object.entries(METHODS))
	{
		await t.step(name, async () =>
		{
			const service = new MediaService(new FakeFetchClient(new Error('Timeout: Server did not respond.')));

			await assertRejects(() => call(service), Error, 'Timeout: Server did not respond.');
		});
	}
});

Deno.test('a change without CSRF token is not sent', async () =>
{
	using _dom   = createDom();
	const client = new FakeFetchClient({success: true});

	await assertRejects(() => new MediaService(client).removeMedia('abc'), Error, 'No CSRF token found in meta tag');
	assertEquals(client.requests, []);
});
