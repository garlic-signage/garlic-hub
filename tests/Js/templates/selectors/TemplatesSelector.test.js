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
import {assertEquals, assertRejects}  from "@std/assert";
import {assertSameElement, createDom} from "@support/dom.js";
import {TemplatesSelector}            from "@js/templates/selectors/TemplatesSelector.js";

const PAGE = '<div id="itemSelectContainer"><p>old</p></div>';

/**
 * Answers find() like the BaseService: the templates or {success: false, error_message}.
 */
class FakeTemplatesService
{
	calls  = 0;
	answer = {success: true, templates: [{id: 4, name: 'Lobby', src: '/a.jpg'}]};

	async find()
	{
		this.calls++;
		return this.answer;
	}
}

/**
 * The selector of the page: its list is the section of templates/generic/templates_selector.mustache.
 */
class FakeView
{
	displayed = [];
	#document;

	constructor(document)
	{
		this.#document = document;
	}

	loadSelectorTemplate()
	{
		const section = this.#document.createElement('section');
		section.id = 'templatesList';
		return section;
	}

	displayList(items)
	{
		for (const item of items)
			this.displayed.push(item.id);
	}
}

function createPage()
{
	const dom     = createDom(PAGE);
	const service = new FakeTemplatesService();
	const view    = new FakeView(dom.document);

	return {
		document: dom.document, service, view, selector: new TemplatesSelector(service, view),
		container: dom.document.getElementById('itemSelectContainer'),
		[Symbol.dispose]: () => dom[Symbol.dispose]()
	};
}

Deno.test('the selector replaces the content of the element and lists the templates', async () =>
{
	using page = createPage();

	await page.selector.showSelector(page.container);

	assertSameElement(page.container.querySelector('p'), null);
	assertSameElement(page.selector.getItemsContainer(), page.container.querySelector('#templatesList'));
	assertEquals(page.service.calls, 1);
	assertEquals(page.view.displayed, [4]);
	assertEquals(page.selector.items, [{id: 4, name: 'Lobby', src: '/a.jpg'}]);
});

Deno.test('before the selector is shown there are no templates', () =>
{
	using page = createPage();

	assertEquals(page.selector.items, null);
	assertSameElement(page.selector.getItemsContainer(), null);
});

Deno.test('a failed search fails the selector, which stays empty', async () =>
{
	using page = createPage();
	page.service.answer = {success: false, error_message: 'Timeout: Server did not respond.'};

	// the BaseService does not throw: result.templates is undefined and the list cannot be shown
	await assertRejects(() => page.selector.showSelector(page.container), TypeError);

	assertEquals(page.view.displayed, []);
	assertEquals(page.selector.items, undefined);
});
