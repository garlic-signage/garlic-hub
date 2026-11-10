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
import {assertEquals}                 from "@std/assert";
import {assertSameElement, createDom} from "@support/dom.js";
import {TemplatesSelectorView}        from "@js/templates/selectors/TemplatesSelectorView.js";

// templates/generic/templates_selector.mustache
const PAGE = `
	<template id="templatesSelectorTemplate">
		<label><input id="templatesFilter" type="search" placeholder="Filter"></label>
		<section id="templatesList" class="templates-grid-view"></section>
	</template>
	<template id="itemTemplate">
		<article class="template-select-item">
			<img class="template-thumbnail" src="" alt="">
			<span class="template-select-name"></span>
		</article>
	</template>
	<div id="itemSelectContainer"></div>`;

/**
 * The view reads its templates when it is created, the selector is shown in the page afterward.
 * The templates leave the page then: getElementById() of linkedom finds the elements inside a template, too.
 */
function createView()
{
	const dom  = createDom(PAGE);
	const view = new TemplatesSelectorView();
	dom.document.querySelectorAll('template').forEach(template => template.remove());
	dom.document.getElementById('itemSelectContainer').replaceChildren(view.loadSelectorTemplate());

	return {document: dom.document, view, [Symbol.dispose]: () => dom[Symbol.dispose]()};
}

function shownTemplates(page)
{
	return [...page.document.querySelectorAll('#templatesList .template-select-item')].map(item => [
		item.getAttribute('data-select-id'), item.querySelector('.template-select-name').textContent,
		item.querySelector('.template-thumbnail').getAttribute('src'), item.querySelector('.template-thumbnail').getAttribute('alt')
	]);
}

Deno.test('the selector is a new copy of the template', () =>
{
	using page = createView();

	const first  = page.view.loadSelectorTemplate();
	const second = page.view.loadSelectorTemplate();

	assertEquals(first.querySelector('#templatesList') === second.querySelector('#templatesList'), false);
	assertEquals(first.querySelectorAll('#templatesFilter, #templatesList').length, 2);
});

Deno.test('the templates are shown with their name, thumbnail and the id to select them', () =>
{
	using page = createView();

	page.view.displayList([{id: 4, name: 'Lobby', src: '/var/templates/4.jpg'}, {id: 9, name: 'Menu', src: '/var/templates/9.jpg'}]);

	assertEquals(shownTemplates(page), [
		['4', 'Lobby', '/var/templates/4.jpg', 'Lobby'], ['9', 'Menu', '/var/templates/9.jpg', 'Menu']
	]);
});

Deno.test('the next list replaces the templates before', () =>
{
	using page = createView();
	page.view.displayList([{id: 4, name: 'Lobby', src: '/a.jpg'}]);

	page.view.displayList([{id: 9, name: 'Menu', src: '/b.jpg'}]);

	assertEquals(shownTemplates(page).map(([id]) => id), ['9']);
});

Deno.test('an empty list empties the selector', () =>
{
	using page = createView();
	page.view.displayList([{id: 4, name: 'Lobby', src: '/a.jpg'}]);

	page.view.displayList([]);

	assertSameElement(page.document.querySelector('#templatesList .template-select-item'), null);
});

Deno.test('a template without a thumbnail gets the text of the missing source', () =>
{
	using page = createView();

	page.view.displayList([{id: 4, name: 'Lobby'}]);

	// "" + undefined: the browser asks for an image named undefined
	assertEquals(shownTemplates(page)[0][2], 'undefined');
});
