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

import {assertEquals, assertStrictEquals, assertThrows} from "@std/assert";
import {createDom}                                      from "@support/dom.js";
import {FakeFetchClient}                                from "@support/FakeFetchClient.js";
import {MemoryStorage, replaceGlobals}                  from "@support/globals.js";
import {AbstractStockPlatform}                          from "@js/mediapool/uploads/StockPlatform/Platforms/AbstractStockPlatform.js";

Deno.test('a new platform starts without token, results and pages, images up to 1920 x 1920', () =>
{
	const client   = new FakeFetchClient({});
	const platform = new AbstractStockPlatform(client);

	assertStrictEquals(platform.fetchClient, client);
	assertEquals(platform.apiToken, '');
	assertEquals(platform.hasVideos, false);
	assertEquals([platform.maxWith, platform.maxHeight], [1920, 1920]);
	assertEquals([platform.totalPages, platform.totalResults, platform.currentPage], [0, 0, 0]);
	assertEquals(platform.currentSearchQuery, '');
	assertEquals(platform.resultsPerPage, 20);
	assertEquals(platform.resultList, {});
});

Deno.test('search, download and the token must come from the platform', () =>
{
	const platform = new AbstractStockPlatform(new FakeFetchClient({}));

	assertThrows(() => platform.search('lobby'));
	assertThrows(() => platform.download());
	assertThrows(() => platform.setApiToken());
});

Deno.test('a saved token is taken, an empty or missing one is no token', () =>
{
	using _globals = replaceGlobals({localStorage: new MemoryStorage()});
	const platform = new AbstractStockPlatform(new FakeFetchClient({}));

	assertEquals(platform.hasApiToken('TestToken'), false);

	localStorage.setItem('TestToken', '');
	assertEquals(platform.hasApiToken('TestToken'), false);
	assertEquals(platform.apiToken, '');

	localStorage.setItem('TestToken', 'secret');
	assertEquals(platform.hasApiToken('TestToken'), true);
	assertEquals(platform.apiToken, 'secret');
});

Deno.test('the results are set and reset', () =>
{
	const platform = new AbstractStockPlatform(new FakeFetchClient({}));

	platform.resultList = {a: {type: 'image'}};
	assertEquals(platform.resultList, {a: {type: 'image'}});

	platform.resetResultList();
	assertEquals(platform.resultList, {});
});

Deno.test('the server asks the stock platform with a POST and the CSRF token', async () =>
{
	using _dom     = createDom('<meta name="csrf-token" content="the-token">');
	const answer   = {success: true, data: {}};
	const client   = new FakeFetchClient(answer);
	const platform = new AbstractStockPlatform(client);

	assertStrictEquals(await platform.requestStockApi({api_url: 'https://api.example.com/?q=lobby'}), answer);

	assertEquals(client.requests[0].url, '/async/mediapool/searchStockImages');
	assertEquals(client.requests[0].options.method, 'POST');
	assertEquals(client.requests[0].options.headers, {'Content-Type': 'application/json'});
	assertEquals(client.bodyOf(), {api_url: 'https://api.example.com/?q=lobby', csrf_token: 'the-token'});
});
