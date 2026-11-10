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

import {assertEquals}                  from "@std/assert";
import {stub}                          from "@std/testing/mock";
import {FakeFetchClient}               from "@support/FakeFetchClient.js";
import {MemoryStorage, replaceGlobals} from "@support/globals.js";
import {UnsplashPlatform}              from "@js/mediapool/uploads/StockPlatform/Platforms/UnsplashPlatform.js";

// a photo of the API of Unsplash: the browser asks Unsplash directly
const PHOTO = {
	id: 'abc', description: 'A lobby',
	urls: {small: 'https://images.unsplash.com/small/abc', thumb: 'https://images.unsplash.com/thumb/abc'},
	links: {download_location: 'https://api.unsplash.com/photos/abc/download?ixid=1', html: 'https://unsplash.com/photos/abc'},
	tags: [{title: 'lobby'}, {title: ''}, {}, {title: 'hall'}],
	user: {username: 'jane', name: 'Jane Doe', portfolio_url: 'https://jane.example.com'}
};

function photos(totalPages = 3, list = [PHOTO])
{
	return {total: 55, total_pages: totalPages, results: list};
}

function createPlatform(answer = photos())
{
	const client   = new FakeFetchClient(answer);
	const platform = new UnsplashPlatform(client);
	platform.apiToken = 'secret';
	return {client, platform};
}

Deno.test('Unsplash has no videos', () =>
{
	assertEquals(new UnsplashPlatform(new FakeFetchClient({})).hasVideos, false);
});

Deno.test('the photos are asked from Unsplash with the client id, videos are no option', async () =>
{
	const {client, platform} = createPlatform();

	await platform.search('lobby & hall', 'videos');

	assertEquals(client.requests[0].url,
		'https://api.unsplash.com/search/photos/?query=lobby%20%26%20hall&page=1&per_page=20&client_id=secret');
	assertEquals(client.requests[0].options, undefined);
});

Deno.test('a photo becomes a result with its sizes, tags and author', async () =>
{
	const {platform} = createPlatform();

	const results = await platform.search('lobby');

	assertEquals(results, {abc: {
		type: 'image',
		preview: 'https://images.unsplash.com/small/abc',
		thumb: 'https://images.unsplash.com/thumb/abc',
		downloadUrl: 'https://api.unsplash.com/photos/abc/download?ixid=1',
		metadata: {
			origin: 'Unsplash', description: 'A lobby', tags: 'lobby, hall', page_url: 'https://unsplash.com/photos/abc',
			user: {username: 'jane', name: 'Jane Doe', url: 'https://jane.example.com'}
		}
	}});
	assertEquals([platform.totalResults, platform.totalPages, platform.currentPage], [55, 3, 1]);
});

Deno.test('missing fields of a photo are null', async () =>
{
	const {platform} = createPlatform(photos(1, [{id: 'x'}, {id: 'y', tags: [{title: ''}]}]));

	const results = await platform.search('lobby');

	assertEquals(results.x, {
		type: 'image', preview: null, thumb: null, downloadUrl: null,
		metadata: {origin: 'Unsplash', description: null, tags: null, page_url: null, user: {username: null, name: null, url: null}}
	});
	assertEquals(results.y.metadata.tags, null);
});

Deno.test('the next page is loaded until the last page', async () =>
{
	const {client, platform} = createPlatform(photos(2));
	await platform.search('lobby');

	await platform.loadNextPage();
	assertEquals(client.requests[1].url, 'https://api.unsplash.com/search/photos/?query=lobby&page=2&per_page=20&client_id=secret');

	assertEquals(await platform.loadNextPage(), undefined);
	assertEquals(client.requests.length, 2);
});

Deno.test('an empty answer is logged and gives nothing', async () =>
{
	using errorStub = stub(console, 'error');
	const {platform} = createPlatform(null);

	assertEquals(await platform.search('lobby'), undefined);
	assertEquals(errorStub.calls[0].args, ['Error:', 'Unknown error']);
});

Deno.test('a failed request or an answer in another format is logged and gives nothing', async () =>
{
	using logStub = stub(console, 'log');

	const failed = createPlatform(new Error('HTTP error: 401'));
	assertEquals(await failed.platform.search('lobby'), undefined);

	const wrong = createPlatform({total: 0, total_pages: 0});
	assertEquals(await wrong.platform.search('lobby'), undefined);

	assertEquals(logStub.calls.map(call => call.args), [['Search error:', 'HTTP error: 401'], ['Search error:', 'Wrong JSON format']]);
});

Deno.test('the download asks Unsplash for the address and limits it to 1920 x 1920', async () =>
{
	const {client, platform} = createPlatform({url: 'https://images.unsplash.com/photo-abc?ixid=1'});

	const url = await platform.determineMediaDownloadUrl('https://api.unsplash.com/photos/abc/download?ixid=1');

	assertEquals(client.requests[0].url, 'https://api.unsplash.com/photos/abc/download?ixid=1&client_id=secret');
	assertEquals(url, 'https://images.unsplash.com/photo-abc?ixid=1&fit=clip&w=1920&h=1920&client_id=secret');
});

Deno.test('a failed download address is logged and gives null', async () =>
{
	using errorStub = stub(console, 'error');
	using logStub   = stub(console, 'log');
	const download  = (answer) => createPlatform(answer).platform.determineMediaDownloadUrl('https://api.unsplash.com/d?x=1');

	assertEquals(await download(null), null);
	assertEquals(await download({errors: ['Rate Limit Exceeded']}), null);
	assertEquals(await download({}), null);
	assertEquals(await download(new Error('HTTP error: 403')), null);

	assertEquals(errorStub.calls.map(call => call.args), [
		['Error:', 'Unknown error'], ['Error:', 'Rate Limit Exceeded'], ['Error:', 'Unknown error']
	]);
	assertEquals(logStub.calls[0].args, ['Download error:', 'HTTP error: 403']);
});

Deno.test('the token is saved in the browser and read again', () =>
{
	using _globals = replaceGlobals({localStorage: new MemoryStorage()});
	const platform = new UnsplashPlatform(new FakeFetchClient({}));
	assertEquals(platform.hasApiToken(), false);

	platform.saveToken('secret');

	assertEquals(localStorage.getItem('UnsplashApiToken'), 'secret');
	assertEquals(platform.apiToken, 'secret');
	assertEquals(new UnsplashPlatform(new FakeFetchClient({})).hasApiToken(), true);
});
