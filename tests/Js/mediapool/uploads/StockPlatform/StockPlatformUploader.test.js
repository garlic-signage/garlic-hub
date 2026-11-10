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
import {StockPlatformElements}         from "@js/mediapool/uploads/StockPlatform/StockPlatformElements.js";
import {StockPlatformUploader}         from "@js/mediapool/uploads/StockPlatform/StockPlatformUploader.js";
import {UploadedMedia}                 from "@js/mediapool/uploads/UploadedMedia.js";

// the tab of the stock platforms in templates/mediapool/overview.mustache
const PAGE = `
	<meta name="csrf-token" content="the-token">
	<select id="selectStockPlatform"><option value="-">-</option></select>
	<div id="radioSearchConfig" style="display:none;">
		<input type="radio" name="config_search" value="configPlatform" id="checkConfigPlatform">
		<input type="radio" name="config_search" value="searchPlatform" id="checkSearchPlatform" checked>
	</div>
	<div id="radioSelectMediatype" style="display:none;">
		<input type="radio" name="mediatype_search" value="images" id="searchMediatypeImages" checked>
		<input type="radio" name="mediatype_search" value="videos" id="searchMediatypeVideos">
	</div>
	<div id="searchInPlatform" style="display:none;">
		<input type="text" id="searchTerms">
		<button id="searchStockPlatform"></button>
		<div id="searchResultsArea"></div>
	</div>
	<div id="configPlatform" style="display:none;">
		<input type="text" id="platformApiToken">
		<button id="savePlatformApiToken"></button>
	</div>
	<div id="downloadStatus"></div>
	<button id="startPlatformsUpload"></button>
	<template id="resultMediaTemplate">
		<article class="result-media-container">
			<input type="checkbox" class="result-checkbox">
			<img src="" class="result-thumbnail" alt="result-thumbnail">
			<i class="media-view-detail bi-eye-fill bi"></i>
		</article>
	</template>`;

function image(id, description = null, tags = null)
{
	return {
		type: 'image', preview: `https://stock.example.com/preview/${id}.jpg`, thumb: `https://stock.example.com/thumb/${id}.jpg`,
		downloadUrl: `https://stock.example.com/files/${id}.jpg`,
		metadata: {origin: 'Stock', description: description, tags: tags}
	};
}

/**
 * A platform of the factory: the results of a search and of the next pages are set by the test.
 * Like the real platforms it returns all results up to now.
 */
class FakePlatform
{
	calls        = [];
	hasVideos    = false;
	apiToken     = '';
	resultList   = {};
	searchAnswer = {};
	pages        = [];
	nextPage     = null;
	/** the ids of the results without a download address */
	noDownload   = [];

	constructor(hasVideos = false, token = '')
	{
		this.hasVideos = hasVideos;
		this.apiToken  = token;
	}

	hasApiToken()
	{
		return this.apiToken !== '';
	}

	saveToken(token)
	{
		this.calls.push(['saveToken', token]);
		this.apiToken = token;
	}

	resetResultList()
	{
		this.calls.push(['resetResultList']);
		this.resultList = {};
	}

	async search(query, mediatype)
	{
		this.calls.push(['search', query, mediatype]);
		if (this.searchAnswer === undefined)
			return undefined;

		this.resultList = {...this.searchAnswer};
		return this.resultList;
	}

	async loadNextPage()
	{
		this.calls.push(['loadNextPage']);
		if (this.nextPage !== null)
			await this.nextPage;

		const page = this.pages.shift();
		if (page === undefined)
			return undefined;

		this.resultList = {...this.resultList, ...page};
		return this.resultList;
	}

	async determineMediaDownloadUrl(uri)
	{
		this.calls.push(['determineMediaDownloadUrl', uri]);
		if (this.noDownload.some(id => uri.endsWith(`/${id}.jpg`)))
			return null;

		return uri + '?w=1920';
	}
}

class FakeUploaderDialog
{
	disableActions() {}
	enableActions()  {}
}

function createPage({lastPlatform = null, platforms = {Pexels: new FakePlatform(true, 'secret'), Unsplash: new FakePlatform()},
	answer = {success: true, media_id: 'new'}} = {})
{
	const dom      = createDom(PAGE);
	const storage  = new MemoryStorage();
	if (lastPlatform !== null)
		storage.setItem('lastPlatform', lastPlatform);
	const globals  = replaceGlobals({localStorage: storage});
	const document = dom.document;
	const client   = new FakeFetchClient(answer);
	const elements = new StockPlatformElements();
	// like the StockPlatformFactory: an unknown platform is an error
	const factory  = {platforms, selectPlatform: (name) => {
		if (!Object.hasOwn(platforms, name))
			throw new Error(`Platform ${name} not registered.`);
		return platforms[name];
	}};
	const uploader = new StockPlatformUploader(factory, elements, {getActiveNodeId: () => 5}, new FakeUploaderDialog(), client);
	UploadedMedia.take();

	return {
		document, elements, uploader, client, platforms,
		[Symbol.dispose]: () => { globals[Symbol.dispose](); dom[Symbol.dispose](); }
	};
}

function choose(page, platform)
{
	const select = page.document.getElementById('selectStockPlatform');
	select.value = platform;
	select.dispatchEvent(new Event('change'));
}

function click(page, id)
{
	page.document.getElementById(id).dispatchEvent(new Event('click'));
}

function wait()
{
	return new Promise(resolve => setTimeout(resolve, 0));
}

function display(page, id)
{
	return page.document.getElementById(id).style.display;
}

function shownResults(page)
{
	return [...page.document.querySelectorAll('#searchResultsArea .result-checkbox')].map(checkbox => checkbox.id);
}

async function search(page, platform = 'Pexels', terms = 'lobby')
{
	choose(page, platform);
	page.document.getElementById('searchTerms').value = terms;
	click(page, 'searchStockPlatform');
	await wait();
}

/**
 * linkedom has no layout: the results area is scrolled to the given position.
 */
function scrollTo(page, scrollTop)
{
	const area = page.document.getElementById('searchResultsArea');
	Object.defineProperty(area, 'scrollTop', {value: scrollTop, configurable: true});
	Object.defineProperty(area, 'clientHeight', {value: 200, configurable: true});
	Object.defineProperty(area, 'scrollHeight', {value: 1000, configurable: true});
	area.dispatchEvent(new Event('scroll'));
}

function mark(page, id, checked = true)
{
	const checkbox   = page.document.getElementById(id);
	checkbox.checked = checked;
	checkbox.dispatchEvent(new Event('click'));
}

Deno.test('every platform of the factory can be chosen, the download starts disabled', () =>
{
	using page = createPage();

	const options = [...page.document.querySelectorAll('#selectStockPlatform option')].map(option => option.getAttribute('value'));
	assertEquals(options, ['-', 'Pexels', 'Unsplash']);
	assertEquals(page.elements.startFileUpload.disabled, true);
});

Deno.test('a chosen platform with a token shows the search and is remembered', () =>
{
	using page = createPage();

	choose(page, 'Pexels');

	assertEquals(localStorage.getItem('lastPlatform'), 'Pexels');
	assertEquals(display(page, 'radioSearchConfig'), 'block');
	assertEquals([display(page, 'searchInPlatform'), display(page, 'configPlatform')], ['block', 'none']);
	assertEquals(display(page, 'radioSelectMediatype'), 'block');
});

Deno.test('a chosen platform without a token asks for it', () =>
{
	using page = createPage();

	choose(page, 'Unsplash');

	assertEquals(display(page, 'radioSearchConfig'), 'none');
	assertEquals([display(page, 'searchInPlatform'), display(page, 'configPlatform')], ['none', 'block']);
	assertEquals(display(page, 'radioSelectMediatype'), 'none');
});

Deno.test('the last platform is chosen again', () =>
{
	using page = createPage({lastPlatform: 'Pexels'});

	assertEquals(page.document.getElementById('selectStockPlatform').value, 'Pexels');
	assertEquals(display(page, 'searchInPlatform'), 'block');
});

Deno.test('a remembered platform that is not registered any more is forgotten', () =>
{
	using page = createPage({lastPlatform: 'Shutterstock'});

	assertEquals(page.document.getElementById('selectStockPlatform').value, '-');
	assertEquals(localStorage.getItem('lastPlatform'), null);
	for (const id of ['radioSearchConfig', 'radioSelectMediatype', 'searchInPlatform', 'configPlatform'])
		assertEquals(display(page, id), 'none', id);
});

Deno.test('the placeholder hides the platform and forgets it', () =>
{
	using page = createPage();
	choose(page, 'Pexels');

	choose(page, '-');

	assertEquals(localStorage.getItem('lastPlatform'), null);
	for (const id of ['radioSearchConfig', 'radioSelectMediatype', 'searchInPlatform', 'configPlatform'])
		assertEquals(display(page, id), 'none', id);
});

Deno.test('without a platform the buttons of a platform do nothing', async () =>
{
	using page = createPage();
	page.platforms.Pexels.searchAnswer = {1: image(1)};
	await search(page);
	mark(page, '1');
	choose(page, '-');
	page.platforms.Pexels.calls = [];

	for (const id of ['savePlatformApiToken', 'checkConfigPlatform', 'searchStockPlatform', 'startPlatformsUpload'])
		click(page, id);
	await wait();

	assertEquals(page.platforms.Pexels.calls, []);
	assertEquals(page.client.requests, []);
});

Deno.test('a saved token opens the search', () =>
{
	using page = createPage();
	choose(page, 'Unsplash');
	page.document.getElementById('platformApiToken').value = 'new-token';

	click(page, 'savePlatformApiToken');

	assertEquals(page.platforms.Unsplash.calls, [['saveToken', 'new-token']]);
	assertEquals([display(page, 'searchInPlatform'), display(page, 'configPlatform')], ['block', 'none']);
	assertEquals(display(page, 'radioSelectMediatype'), 'none');
});

Deno.test('the configuration shows the saved token, the search hides it again', () =>
{
	using page = createPage();
	choose(page, 'Pexels');

	const config = page.document.getElementById('checkConfigPlatform');
	config.checked = true;
	config.dispatchEvent(new Event('click'));
	assertEquals([display(page, 'searchInPlatform'), display(page, 'configPlatform')], ['none', 'block']);
	assertEquals(page.document.getElementById('platformApiToken').value, 'secret');

	const searchRadio = page.document.getElementById('checkSearchPlatform');
	searchRadio.checked = true;
	searchRadio.dispatchEvent(new Event('click'));
	assertEquals([display(page, 'searchInPlatform'), display(page, 'configPlatform')], ['block', 'none']);
});

Deno.test('a search shows the results with their thumbnail and a hidden preview', async () =>
{
	using page = createPage();
	page.platforms.Pexels.searchAnswer = {1: image(1, 'A lobby'), 2: image(2, null, 'hall, lobby'), 3: image(3)};

	await search(page);

	assertEquals(page.platforms.Pexels.calls, [['resetResultList'], ['search', 'lobby', 'images']]);
	assertEquals(shownResults(page), ['1', '2', '3']);
	const [first, second, third] = page.document.querySelectorAll('.result-media-container');
	assertEquals(first.querySelector('.result-thumbnail').getAttribute('src'), 'https://stock.example.com/thumb/1.jpg');
	assertEquals(first.querySelector('.result-preview').tagName, 'IMG');
	assertEquals(first.querySelector('.result-preview').getAttribute('src'), 'https://stock.example.com/preview/1.jpg');
	assertEquals([first, second, third].map(item => item.querySelector('.result-preview').getAttribute('alt')),
		['A lobby', 'hall, lobby', '']);
	assertEquals(second.querySelector('.result-thumbnail').getAttribute('alt'), 'hall, lobby');
});

Deno.test('a video is previewed muted in a loop with its thumbnail as poster', async () =>
{
	using page = createPage();
	page.platforms.Pexels.searchAnswer = {7: {...image(7), type: 'video', preview: 'https://stock.example.com/7.mp4'}};

	await search(page);

	const video = page.document.querySelector('.result-preview');
	assertEquals(video.tagName, 'VIDEO');
	assertEquals([video.autoplay, video.muted, video.loop, video.preload, video.poster],
		[true, true, true, 'none', 'https://stock.example.com/thumb/7.jpg']);
	// linkedom does not reflect the source of a video to the attribute
	assertEquals(video.src, 'https://stock.example.com/7.mp4');
});

Deno.test('a result of an unknown kind is left out', async () =>
{
	using page    = createPage();
	using logStub = stub(console, 'log');
	page.platforms.Pexels.searchAnswer = {1: {...image(1), type: 'audio'}, 2: image(2)};

	await search(page);

	assertEquals(shownResults(page), ['2']);
	assertEquals(logStub.calls[0].args, ['Unknown media type: audio']);
});

Deno.test('videos are searched only when chosen and the platform has videos', async () =>
{
	using page = createPage({platforms: {Pexels: new FakePlatform(true, 'k'), Unsplash: new FakePlatform(false, 'k')}});
	page.document.getElementById('searchMediatypeVideos').checked = true;

	await search(page, 'Pexels');
	await search(page, 'Unsplash');

	assertEquals(page.platforms.Pexels.calls.at(-1), ['search', 'lobby', 'videos']);
	assertEquals(page.platforms.Unsplash.calls.at(-1), ['search', 'lobby', 'images']);
});

Deno.test('a new search replaces the results before', async () =>
{
	using page = createPage();
	page.platforms.Pexels.searchAnswer = {1: image(1), 2: image(2)};
	await search(page);

	page.platforms.Pexels.searchAnswer = {2: image(2), 3: image(3)};
	await search(page, 'Pexels', 'hall');

	assertEquals(shownResults(page), ['2', '3']);
});

Deno.test('a failed search shows nothing', async () =>
{
	using page = createPage();
	page.platforms.Pexels.searchAnswer = {1: image(1)};
	await search(page);

	page.platforms.Pexels.searchAnswer = undefined;
	await search(page);

	assertEquals(shownResults(page), []);
});

Deno.test('without a chosen platform the search does nothing', async () =>
{
	using page = createPage();

	click(page, 'searchStockPlatform');
	await wait();

	assertEquals(page.platforms.Pexels.calls, []);
	assertEquals(page.platforms.Unsplash.calls, []);
});

Deno.test('scrolling to the end adds only the new results of the next page', async () =>
{
	using page = createPage();
	page.platforms.Pexels.searchAnswer = {1: image(1), 2: image(2)};
	page.platforms.Pexels.pages = [{3: image(3)}];
	await search(page);

	scrollTo(page, 500);
	await wait();
	assertEquals(page.platforms.Pexels.calls.filter(call => call[0] === 'loadNextPage'), []);

	scrollTo(page, 800);
	await wait();
	assertEquals(shownResults(page), ['1', '2', '3']);

	// the last page gives nothing
	scrollTo(page, 800);
	await wait();
	assertEquals(shownResults(page), ['1', '2', '3']);
});

Deno.test('more scroll events at the end load the next page only once', async () =>
{
	using page   = createPage();
	const answer = Promise.withResolvers();
	page.platforms.Pexels.pages    = [{3: image(3)}, {4: image(4)}];
	page.platforms.Pexels.nextPage = answer.promise;
	await search(page);

	scrollTo(page, 800);
	scrollTo(page, 800);
	answer.resolve();
	await wait();

	assertEquals(page.platforms.Pexels.calls.filter(call => call[0] === 'loadNextPage').length, 1);
	assertEquals(shownResults(page), ['3']);

	scrollTo(page, 800);
	await wait();
	assertEquals(shownResults(page), ['3', '4']);
});

Deno.test('without a chosen platform scrolling loads nothing', async () =>
{
	using page = createPage();

	scrollTo(page, 800);
	await wait();

	assertEquals(page.platforms.Pexels.calls, []);
});

Deno.test('the download is enabled while a result is marked', async () =>
{
	using page = createPage();
	page.platforms.Pexels.searchAnswer = {1: image(1), 2: image(2)};
	await search(page);

	mark(page, '1');
	assertEquals(page.elements.startFileUpload.disabled, false);

	mark(page, '2');
	mark(page, '1', false);
	assertEquals(page.elements.startFileUpload.disabled, false);

	mark(page, '2', false);
	assertEquals(page.elements.startFileUpload.disabled, true);
});

Deno.test('the marked results are uploaded with their metadata and unmarked', async () =>
{
	using page = createPage();
	page.platforms.Pexels.searchAnswer = {1: image(1, 'A lobby'), 2: image(2), 3: image(3)};
	await search(page);
	mark(page, '1');
	mark(page, '3');

	click(page, 'startPlatformsUpload');
	await wait();

	assertEquals(page.platforms.Pexels.calls.filter(call => call[0] === 'determineMediaDownloadUrl'),
		[['determineMediaDownloadUrl', 'https://stock.example.com/files/1.jpg'], ['determineMediaDownloadUrl', 'https://stock.example.com/files/3.jpg']]);
	const bodies = page.client.requests.map(request => Object.fromEntries(request.options.body.entries()));
	assertEquals(bodies.map(body => body.external_link), ['https://stock.example.com/files/1.jpg?w=1920', 'https://stock.example.com/files/3.jpg?w=1920']);
	assertEquals(JSON.parse(bodies[0].metadata), {origin: 'Stock', description: 'A lobby', tags: null});
	assertEquals(bodies[0].node_id, '5');
	assertEquals(UploadedMedia.take(), ['new', 'new']);
	assertEquals(page.document.getElementById('downloadStatus').textContent, 'Finish downloading: 3.jpg');
	assertEquals([...page.document.querySelectorAll('.result-checkbox')].map(checkbox => checkbox.checked === true), [false, false, false]);
});

Deno.test('a result without a download address is not sent, stays marked and the status says so', async () =>
{
	using page = createPage();
	page.platforms.Pexels.searchAnswer = {1: image(1), 2: image(2)};
	page.platforms.Pexels.noDownload   = ['2'];
	await search(page);
	mark(page, '1');
	mark(page, '2');

	click(page, 'startPlatformsUpload');
	await wait();

	const bodies = page.client.requests.map(request => Object.fromEntries(request.options.body.entries()));
	assertEquals(bodies.map(body => body.external_link), ['https://stock.example.com/files/1.jpg?w=1920']);
	assertEquals(page.document.getElementById('downloadStatus').textContent, 'Download failed: 2.jpg');
	assertEquals([...page.document.querySelectorAll('.result-checkbox')].map(checkbox => checkbox.checked === true), [false, true]);
});

Deno.test('a refused upload stays marked and the status says so', async () =>
{
	using page      = createPage({answer: {success: false, error_message: 'Not allowed'}});
	using _errorLog = stub(console, 'error');
	page.platforms.Pexels.searchAnswer = {1: image(1)};
	await search(page);
	mark(page, '1');

	click(page, 'startPlatformsUpload');
	await wait();

	assertEquals(page.document.getElementById('downloadStatus').textContent, 'Download failed: 1.jpg');
	assertEquals(page.document.getElementById('1').checked, true);
	assertEquals(UploadedMedia.take(), []);
});

Deno.test('a marked element that is no result is not uploaded', async () =>
{
	using page = createPage();
	await search(page);
	page.document.body.insertAdjacentHTML('beforeend', '<input type="checkbox" class="result-checkbox" id="other" checked>');

	click(page, 'startPlatformsUpload');
	await wait();

	assertEquals(page.client.requests, []);
});

Deno.test('the eye shows the preview, leaving it hides the preview again', async () =>
{
	using page = createPage();
	page.platforms.Pexels.searchAnswer = {1: image(1)};
	await search(page);
	const eye     = page.document.querySelector('.media-view-detail');
	const preview = page.document.querySelector('.result-preview');

	eye.dispatchEvent(new Event('mouseover'));
	assertEquals(preview.style.display, 'block');

	eye.dispatchEvent(new Event('mouseout'));
	assertEquals(preview.style.display, 'none');
});

Deno.test('a video preview plays while it is shown', async () =>
{
	using page = createPage();
	page.platforms.Pexels.searchAnswer = {7: {...image(7), type: 'video'}};
	await search(page);
	const eye     = page.document.querySelector('.media-view-detail');
	const video   = page.document.querySelector('.result-preview');
	const calls   = [];
	video.play  = () => calls.push('play');
	video.pause = () => calls.push('pause');

	eye.dispatchEvent(new Event('mouseover'));
	eye.dispatchEvent(new Event('mouseout'));

	assertEquals(calls, ['play', 'pause']);
});
