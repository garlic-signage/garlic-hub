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
import {createDom}                     from "@support/dom.js";
import {FakeFetchClient}               from "@support/FakeFetchClient.js";
import {MemoryStorage, replaceGlobals} from "@support/globals.js";
import {PixabayPlatform}               from "@js/mediapool/uploads/StockPlatform/Platforms/PixabayPlatform.js";

const CSRF_META = '<meta name="csrf-token" content="the-token">';

// an image and a video of the API of Pixabay, as the server passes them on
const IMAGE = {
	id: 101, tags: 'lobby, hall', pageURL: 'https://pixabay.com/photos/101/', username: 'jane',
	webformatURL: 'https://pixabay.com/web/101.jpg', previewURL: 'https://cdn.pixabay.com/preview/101.jpg',
	largeImageURL: 'https://pixabay.com/get/101_1280.jpg'
};

const VIDEO = {
	id: 202, tags: 'lobby', pageURL: 'https://pixabay.com/videos/202/', user: 'john', user_id: 8,
	videos: {
		tiny:  {url: 'https://cdn.pixabay.com/video/202_tiny.mp4', thumbnail: 'https://cdn.pixabay.com/video/202_tiny.jpg'},
		large: {url: 'https://cdn.pixabay.com/video/202_large.mp4'}
	}
};

function images(totalHits = 45, list = [IMAGE])
{
	return {success: true, data: {total: 1000, totalHits: totalHits, hits: list}};
}

function createPlatform(answer = images())
{
	const client   = new FakeFetchClient(answer);
	const platform = new PixabayPlatform(client);
	platform.apiToken = 'secret';
	return {client, platform};
}

Deno.test('Pixabay has videos', () =>
{
	assertEquals(new PixabayPlatform(new FakeFetchClient({})).hasVideos, true);
});

Deno.test('the images are searched by the server with the key in the address', async () =>
{
	using _dom = createDom(CSRF_META);
	const {client, platform} = createPlatform();

	await platform.search('lobby & hall');

	assertEquals(client.bodyOf(), {
		api_url: 'https://pixabay.com/api/?q=lobby%20%26%20hall&page=1&per_page=20&key=secret',
		csrf_token: 'the-token'
	});
});

Deno.test('the videos have their own address', async () =>
{
	using _dom = createDom(CSRF_META);
	const {client, platform} = createPlatform({success: true, data: {total: 1, totalHits: 1, hits: [VIDEO]}});

	await platform.search('lobby', 'videos');

	assertEquals(client.bodyOf().api_url, 'https://pixabay.com/api/videos/?q=lobby&page=1&per_page=20&key=secret');
});

Deno.test('an image becomes a result with its sizes, tags and author', async () =>
{
	using _dom = createDom(CSRF_META);
	const {platform} = createPlatform();

	const results = await platform.search('lobby');

	assertEquals(results, {101: {
		type: 'image',
		preview: 'https://pixabay.com/web/101.jpg',
		thumb: 'https://cdn.pixabay.com/preview/101.jpg',
		downloadUrl: 'https://pixabay.com/get/101_1280.jpg',
		metadata: {
			origin: 'Pixabay', description: null, tags: 'lobby, hall', page_url: 'https://pixabay.com/photos/101/',
			user: {username: 'jane', name: 'jane', url: null}
		}
	}});
});

Deno.test('the pages are counted from the hits Pixabay returns, the results from all', async () =>
{
	using _dom = createDom(CSRF_META);
	const {platform} = createPlatform(images(500));

	await platform.search('lobby');

	assertEquals([platform.totalResults, platform.totalPages], [1000, 25]);
});

Deno.test('missing fields of an image are null', async () =>
{
	using _dom = createDom(CSRF_META);
	const {platform} = createPlatform(images(1, [{id: 5}]));

	const results = await platform.search('lobby');

	assertEquals([results[5].preview, results[5].thumb, results[5].downloadUrl], [null, null, null]);
	assertEquals([results[5].metadata.tags, results[5].metadata.page_url], [null, null]);
	assertEquals(results[5].metadata.user, {username: null, name: null, url: null});
});

Deno.test('a video shows the tiny file and downloads the large one', async () =>
{
	using _dom = createDom(CSRF_META);
	const {platform} = createPlatform({success: true, data: {total: 1, totalHits: 1, hits: [VIDEO]}});

	const results = await platform.search('lobby', 'videos');

	assertEquals(results, {202: {
		type: 'video',
		preview: 'https://cdn.pixabay.com/video/202_tiny.mp4',
		thumb: 'https://cdn.pixabay.com/video/202_tiny.jpg',
		downloadUrl: 'https://cdn.pixabay.com/video/202_large.mp4',
		metadata: {
			origin: 'Pixabay', description: null, tags: 'lobby', page_url: 'https://pixabay.com/videos/202/',
			user: {username: 8, name: 'john', url: null}
		}
	}});
});

Deno.test('the next page is loaded until the last page', async () =>
{
	using _dom = createDom(CSRF_META);
	const {client, platform} = createPlatform(images(40));
	await platform.search('lobby');

	await platform.loadNextPage();
	assertEquals(client.bodyOf(1).api_url, 'https://pixabay.com/api/?q=lobby&page=2&per_page=20&key=secret');

	assertEquals(await platform.loadNextPage(), undefined);
	assertEquals(client.requests.length, 2);
});

Deno.test('a refused search is logged and gives nothing', async () =>
{
	using _dom      = createDom(CSRF_META);
	using errorStub = stub(console, 'error');

	for (const answer of [{success: false, error_message: 'Invalid key'}, {success: false}])
	{
		const {platform} = createPlatform(answer);
		assertEquals(await platform.search('lobby'), undefined);
	}

	assertEquals(errorStub.calls.map(call => call.args), [['Error:', 'Invalid key'], ['Error:', 'Unknown error']]);
});

Deno.test('a failed request or an answer in another format is logged and gives nothing', async () =>
{
	using _dom = createDom(CSRF_META);
	using logStub = stub(console, 'log');

	const failed = createPlatform(new Error('Timeout: Server did not respond.'));
	assertEquals(await failed.platform.search('lobby'), undefined);

	const wrong = createPlatform({success: true, data: {total: 1, totalHits: 1}});
	assertEquals(await wrong.platform.search('lobby', 'videos'), undefined);

	assertEquals(logStub.calls.map(call => call.args), [
		['Search error:', 'Timeout: Server did not respond.'], ['Search error:', 'Wrong JSON format']
	]);
});

Deno.test('the download address is taken as it is', async () =>
{
	const platform = new PixabayPlatform(new FakeFetchClient({}));

	assertEquals(await platform.determineMediaDownloadUrl('https://pixabay.com/get/101_1280.jpg'), 'https://pixabay.com/get/101_1280.jpg');
});

Deno.test('the token is saved in the browser and read again', () =>
{
	using _globals = replaceGlobals({localStorage: new MemoryStorage()});
	const platform = new PixabayPlatform(new FakeFetchClient({}));
	assertEquals(platform.hasApiToken(), false);

	platform.saveToken('secret');

	assertEquals(localStorage.getItem('PixabayApiToken'), 'secret');
	assertEquals(platform.apiToken, 'secret');
	assertEquals(new PixabayPlatform(new FakeFetchClient({})).hasApiToken(), true);
});

/**
 * The next request fails once with the given answer, then the client answers as before.
 */
function failOnce(client, answer)
{
	const fetchData  = client.fetchData.bind(client);
	client.fetchData = (url, options) =>
	{
		client.fetchData = fetchData;
		client.requests.push({url, options});
		return answer instanceof Error ? Promise.reject(answer) : Promise.resolve(answer);
	};
}

Deno.test('a page that failed to load is asked for again by the next scroll', async () =>
{
	using _dom = createDom(CSRF_META);
	using _log = stub(console, 'log');
	const {client, platform} = createPlatform(images(45));
	await platform.search('lobby');

	failOnce(client, new Error('Timeout: Server did not respond.'));
	assertEquals(await platform.loadNextPage(), undefined);
	assertEquals(platform.currentPage, 1);

	await platform.loadNextPage();
	assertEquals(client.bodyOf(2).api_url, 'https://pixabay.com/api/?q=lobby&page=2&per_page=20&key=secret');
	assertEquals(platform.currentPage, 2);
});

Deno.test('the last page that is only partly filled is loaded, no empty page after it', async () =>
{
	using _dom = createDom(CSRF_META);
	const {client, platform} = createPlatform(images(41));
	await platform.search('lobby');

	assertEquals(platform.totalPages, 3);
	await platform.loadNextPage();
	await platform.loadNextPage();
	assertEquals(await platform.loadNextPage(), undefined);
	assertEquals(client.requests.length, 3);
});

Deno.test('an empty answer is logged as unknown error', async () =>
{
	using _dom      = createDom(CSRF_META);
	using errorStub = stub(console, 'error');

	assertEquals(await createPlatform(null).platform.search('lobby'), undefined);

	assertEquals(errorStub.calls[0].args, ['Error:', 'Unknown error']);
});
