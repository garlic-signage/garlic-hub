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
import {PexelsPlatform}                from "@js/mediapool/uploads/StockPlatform/Platforms/PexelsPlatform.js";

const CSRF_META = '<meta name="csrf-token" content="the-token">';

// a photo and a video of the API of Pexels, as the server passes them on
const PHOTO = {
	id: 101, alt: 'A lobby', url: 'https://www.pexels.com/photo/101/',
	photographer: 'Jane Doe', photographer_id: 7, photographer_url: 'https://www.pexels.com/@jane',
	src: {original: 'https://images.pexels.com/photos/101/lobby.jpeg', medium: 'https://images.pexels.com/m/101', small: 'https://images.pexels.com/s/101'}
};

const VIDEO = {
	id: 202, url: 'https://www.pexels.com/video/202/',
	user: {id: 8, name: 'John Roe', url: 'https://www.pexels.com/@john'},
	video_pictures: [{picture: 'https://images.pexels.com/v/202.jpg'}],
	video_files: [
		{quality: 'hd', width: 1280, height: 720, link: 'https://videos.pexels.com/202-720.mp4'},
		{quality: 'sd', width: 960, height: 540, link: 'https://videos.pexels.com/202-540.mp4'},
		{quality: 'hd', width: 1920, height: 1080, link: 'https://videos.pexels.com/202-1080.mp4'},
		{quality: 'sd', width: 640, height: 360, link: 'https://videos.pexels.com/202-360.mp4'},
	]
};

function photos(total = 45, list = [PHOTO])
{
	return {success: true, data: {total_results: total, photos: list}};
}

function createPlatform(answer = photos())
{
	const client   = new FakeFetchClient(answer);
	const platform = new PexelsPlatform(client);
	platform.apiToken = 'secret';
	return {client, platform};
}

function apiUrl(client, index = 0)
{
	return client.bodyOf(index).api_url;
}

Deno.test('Pexels has videos', () =>
{
	assertEquals(new PexelsPlatform(new FakeFetchClient({})).hasVideos, true);
});

Deno.test('the images are searched by the server with the token in the header', async () =>
{
	using _dom = createDom(CSRF_META);
	const {client, platform} = createPlatform();

	await platform.search('lobby & hall', 'images');

	assertEquals(client.bodyOf(), {
		api_url: 'https://api.pexels.com/v1/search/?query=lobby%20%26%20hall&page=1&per_page=20',
		headers: {Authorization: 'secret'},
		csrf_token: 'the-token'
	});
});

Deno.test('the videos have their own address', async () =>
{
	using _dom = createDom(CSRF_META);
	const {client, platform} = createPlatform({success: true, data: {total_results: 1, videos: [VIDEO]}});

	await platform.search('lobby', 'videos');

	assertEquals(apiUrl(client), 'https://api.pexels.com/videos/search/?query=lobby&page=1&per_page=20');
});

Deno.test('a photo becomes a result with its sizes and its author', async () =>
{
	using _dom = createDom(CSRF_META);
	const {platform} = createPlatform();

	const results = await platform.search('lobby', 'images');

	assertEquals(results, {101: {
		type: 'image',
		preview: 'https://images.pexels.com/m/101',
		thumb: 'https://images.pexels.com/s/101',
		downloadUrl: 'https://images.pexels.com/photos/101/lobby.jpeg',
		metadata: {
			origin: 'pexels', description: 'A lobby', page_url: 'https://www.pexels.com/photo/101/',
			user: {username: 7, name: 'Jane Doe', url: 'https://www.pexels.com/@jane'}
		}
	}});
	assertEquals([platform.totalResults, platform.totalPages, platform.currentPage], [45, 3, 1]);
});

Deno.test('missing fields of a photo are null', async () =>
{
	using _dom = createDom(CSRF_META);
	const {platform} = createPlatform(photos(1, [{id: 5}]));

	const results = await platform.search('lobby', 'images');

	assertEquals(results[5].preview, null);
	assertEquals(results[5].thumb, null);
	assertEquals(results[5].downloadUrl, null);
	assertEquals(results[5].metadata.description, null);
	assertEquals(results[5].metadata.user, {username: null, name: null, url: undefined});
});

Deno.test('a video shows the smallest SD file and downloads the largest HD file', async () =>
{
	using _dom = createDom(CSRF_META);
	const {platform} = createPlatform({success: true, data: {total_results: 1, videos: [VIDEO]}});

	const results = await platform.search('lobby', 'videos');

	assertEquals(results, {202: {
		type: 'video',
		preview: 'https://videos.pexels.com/202-360.mp4',
		thumb: 'https://images.pexels.com/v/202.jpg',
		downloadUrl: 'https://videos.pexels.com/202-1080.mp4',
		metadata: {
			origin: 'pexels', description: null, page_url: 'https://www.pexels.com/video/202/',
			user: {username: 8, name: 'John Roe', url: 'https://www.pexels.com/@john'}
		}
	}});
});

Deno.test('the next page is added to the results until the last page', async () =>
{
	using _dom = createDom(CSRF_META);
	const {client, platform} = createPlatform(photos(45));
	await platform.search('lobby', 'images');

	platform.resultList = {...platform.resultList, 99: {type: 'image'}};
	const results = await platform.loadNextPage();
	assertEquals(Object.keys(results), ['99', '101']);
	assertEquals(apiUrl(client, 1), 'https://api.pexels.com/v1/search/?query=lobby&page=2&per_page=20');

	await platform.loadNextPage();
	assertEquals(platform.currentPage, 3);

	assertEquals(await platform.loadNextPage(), undefined);
	assertEquals(client.requests.length, 3);
});

Deno.test('a new search starts again on the first page', async () =>
{
	using _dom = createDom(CSRF_META);
	const {client, platform} = createPlatform(photos(100));
	await platform.search('lobby', 'images');
	await platform.loadNextPage();

	await platform.search('hall', 'images');

	assertEquals(platform.currentPage, 1);
	assertEquals(platform.currentSearchQuery, 'hall');
	assertEquals(apiUrl(client, 2), 'https://api.pexels.com/v1/search/?query=hall&page=1&per_page=20');
});

Deno.test('a refused search is logged and gives nothing', async () =>
{
	using _dom      = createDom(CSRF_META);
	using errorStub = stub(console, 'error');

	for (const answer of [{success: false, error_message: 'Invalid key'}, {success: false}])
	{
		const {platform} = createPlatform(answer);
		assertEquals(await platform.search('lobby', 'images'), undefined);
	}

	assertEquals(errorStub.calls.map(call => call.args), [['Error:', 'Invalid key'], ['Error:', 'Unknown error']]);
});

Deno.test('a failed request or an answer in another format is logged and gives nothing', async () =>
{
	using _dom = createDom(CSRF_META);
	using logStub = stub(console, 'log');

	const failed = createPlatform(new Error('Timeout: Server did not respond.'));
	assertEquals(await failed.platform.search('lobby', 'images'), undefined);

	const wrong = createPlatform({success: true, data: {total_results: 1}});
	assertEquals(await wrong.platform.search('lobby', 'images'), undefined);

	assertEquals(logStub.calls.map(call => call.args), [
		['Search error:', 'Timeout: Server did not respond.'], ['Search error:', 'Wrong JSON format']
	]);
});

Deno.test('the download is compressed to 1920 x 1920', async () =>
{
	const platform = new PexelsPlatform(new FakeFetchClient({}));

	assertEquals(await platform.determineMediaDownloadUrl('https://images.pexels.com/photos/101/lobby.jpeg'),
		'https://images.pexels.com/photos/101/lobby.jpeg?auto=compress&cs=tinysrgb&w=1920&h=1920');
});

Deno.test('the token is saved in the browser and read again', () =>
{
	using _globals = replaceGlobals({localStorage: new MemoryStorage()});
	const platform = new PexelsPlatform(new FakeFetchClient({}));
	assertEquals(platform.hasApiToken(), false);

	platform.saveToken('secret');

	assertEquals(localStorage.getItem('PexelsApiToken'), 'secret');
	assertEquals(platform.apiToken, 'secret');
	const other = new PexelsPlatform(new FakeFetchClient({}));
	assertEquals(other.hasApiToken(), true);
	assertEquals(other.apiToken, 'secret');
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
	const {client, platform} = createPlatform(photos(45));
	await platform.search('lobby', 'images');

	failOnce(client, new Error('Timeout: Server did not respond.'));
	assertEquals(await platform.loadNextPage(), undefined);
	assertEquals(platform.currentPage, 1);

	await platform.loadNextPage();
	assertEquals(apiUrl(client, 2), 'https://api.pexels.com/v1/search/?query=lobby&page=2&per_page=20');
	assertEquals(platform.currentPage, 2);
});

Deno.test('the last page that is only partly filled is loaded, no empty page after it', async () =>
{
	using _dom = createDom(CSRF_META);
	const {client, platform} = createPlatform(photos(41));
	await platform.search('lobby', 'images');

	await platform.loadNextPage();
	await platform.loadNextPage();
	assertEquals(await platform.loadNextPage(), undefined);

	assertEquals(client.requests.length, 3);
	assertEquals(platform.currentPage, 3);
});

Deno.test('a failed search leaves no page to scroll to', async () =>
{
	using _dom      = createDom(CSRF_META);
	using errorStub = stub(console, 'error');
	const {client, platform} = createPlatform(photos(100));
	await platform.search('lobby', 'images');

	failOnce(client, null);
	assertEquals(await platform.search('hall', 'images'), undefined);

	assertEquals(await platform.loadNextPage(), undefined);
	assertEquals(client.requests.length, 2);
	assertEquals(errorStub.calls[0].args, ['Error:', 'Unknown error']);
});
