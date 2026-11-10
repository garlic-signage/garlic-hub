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
import {assertEquals}       from "@std/assert";
import {DOMParser}          from "linkedom";
import {createDom}          from "@support/dom.js";
import {replaceGlobals}     from "@support/globals.js";
import {DatatableRefresher} from "@js/core/DatatableRefresher.js";

/**
 * A datatable page: the search form with page, count and pager, and the rows.
 */
function table(page, rows, count = String(rows.length))
{
	return `
		<form id="form_elements_search">
			<input id="elements_page" value="${page}">
			<span class="results-count">${count}</span>
			<nav class="results-pager">page ${page}</nav>
		</form>
		<ul class="results-listing">${rows.map(id => `<li><ul class="results-body" data-id="${id}"></ul></li>`).join('')}</ul>`;
}

/**
 * The page in the browser and the answers of the server, one per request.
 */
function createPage(url, answers)
{
	const dom      = createDom(table(2, [7, 8]) + '<aside id="other">stays</aside>');
	const requests = [];
	const history  = [];
	const globals  = replaceGlobals({
		DOMParser,
		window: {location: {href: url}, history: {replaceState: (state, title, newUrl) => history.push(String(newUrl))}},
		fetch:  (requested, options) =>
		{
			requests.push([String(requested), options]);
			const answer = answers.shift();
			return answer instanceof Error ? Promise.reject(answer) : Promise.resolve({ok: true, redirected: false, text: async () => '', ...answer});
		}
	});

	return {
		document: dom.document, requests, history,
		rows:  () => [...dom.document.querySelectorAll('.results-body')].map(row => row.dataset.id),
		state: () => [dom.document.querySelector('.results-count').textContent, dom.document.querySelector('.results-pager').textContent, dom.document.getElementById('elements_page').value],
		[Symbol.dispose]: () => { globals[Symbol.dispose](); dom[Symbol.dispose](); }
	};
}

Deno.test('the rows, the count and the pager of the page are loaded again, the rest stays', async () =>
{
	using page = createPage('https://hub.example.com/player?elements_page=2', [{text: async () => table(2, [8, 9], '11')}]);

	assertEquals(await DatatableRefresher.refresh(), true);

	assertEquals(page.requests, [['https://hub.example.com/player?elements_page=2', {headers: {'X-Page-Fragment': 'content'}, credentials: 'same-origin'}]]);
	assertEquals(page.rows(), ['8', '9']);
	assertEquals(page.state(), ['11', 'page 2', '2']);
	assertEquals(page.document.getElementById('other').textContent, 'stays');
	assertEquals(page.history, []);
});

Deno.test('when the last row of the last page is gone the page before comes and goes into the url', async () =>
{
	using page = createPage('https://hub.example.com/player?elements_page=3&name=a', [
		{text: async () => table(3, [], '10')},
		{text: async () => table(2, [5, 6], '10')}
	]);

	assertEquals(await DatatableRefresher.refresh(), true);

	assertEquals(page.requests.map(([url]) => url), ['https://hub.example.com/player?elements_page=3&name=a', 'https://hub.example.com/player?elements_page=2&name=a']);
	assertEquals(page.history, ['https://hub.example.com/player?elements_page=2&name=a']);
	assertEquals(page.rows(), ['5', '6']);
	assertEquals(page.state(), ['10', 'page 2', '2']);
});

Deno.test('an empty first page stays empty', async () =>
{
	using page = createPage('https://hub.example.com/player', [{text: async () => table(1, [], '0')}]);

	assertEquals(await DatatableRefresher.refresh(), true);

	assertEquals(page.requests.length, 1);
	assertEquals(page.rows(), []);
});

Deno.test('a page which cannot be loaded changes nothing', async (t) =>
{
	const answers = {
		'an error of the server':           {ok: false},
		'a redirect, e.g. to the login':    {redirected: true, text: async () => table(2, [9])},
		'no datatable in the answer':       {text: async () => '<p>Login</p>'},
		'no connection':                    new Error('offline'),
	};

	for (const [name, answer] of Object.entries(answers))
	{
		await t.step(name, async () =>
		{
			using page = createPage('https://hub.example.com/player?elements_page=2', [answer]);

			assertEquals(await DatatableRefresher.refresh(), false);

			assertEquals(page.rows(), ['7', '8']);
		});
	}
});

Deno.test('the page before which cannot be loaded changes nothing', async () =>
{
	using page = createPage('https://hub.example.com/player?elements_page=3', [{text: async () => table(3, [])}, {ok: false}]);

	assertEquals(await DatatableRefresher.refresh(), false);

	assertEquals(page.rows(), ['7', '8']);
	assertEquals(page.history, []);
});
