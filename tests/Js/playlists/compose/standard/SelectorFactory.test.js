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


import {assertEquals, assertInstanceOf} from "@std/assert";
import {createDom}                      from "@support/dom.js";

// the media selector loads UploadDialogElements, which reads the document when it is loaded
let SelectorFactory, MediaSelector, PlaylistsSelector, TemplatesSelector;
{
	using _dom = createDom('<button id="openUploadDialog"></button>');
	({SelectorFactory}   = await import("@js/playlists/compose/standard/SelectorFactory.js"));
	({MediaSelector}     = await import("@js/mediapool/selector/MediaSelector.js"));
	({PlaylistsSelector} = await import("@js/playlists/compose/standard/selectors/PlaylistsSelector.js"));
	({TemplatesSelector} = await import("@js/templates/selectors/TemplatesSelector.js"));
}

// the selectors of templates/generic/*_selector.mustache, only their lists
const PAGE = `
	<template id="playlistsSelectorTemplate"><section id="playlistsList"></section></template>
	<template id="templatesSelectorTemplate"><section id="templatesList"></section></template>
	<div id="itemSelectContainer"></div>`;

class FakePlaylistsService
{
	calls = [];

	async findPlaylists(mode, name)
	{
		this.calls.push([mode, name]);
		return [];
	}
}

class FakeTemplatesService
{
	calls = 0;

	async find()
	{
		this.calls++;
		return {templates: []};
	}
}

function createPage()
{
	const dom       = createDom(PAGE);
	const playlists = new FakePlaylistsService();
	const templates = new FakeTemplatesService();

	return {
		document: dom.document, playlists, templates,
		factory:   new SelectorFactory(playlists, templates),
		container: dom.document.getElementById('itemSelectContainer'),
		[Symbol.dispose]: () => dom[Symbol.dispose]()
	};
}

Deno.test('every insert menu has its own selector', () =>
{
	using page = createPage();

	assertInstanceOf(page.factory.create('mediapool'), MediaSelector);
	assertInstanceOf(page.factory.create('playlists'), PlaylistsSelector);
	assertInstanceOf(page.factory.create('templates'), TemplatesSelector);
});

Deno.test('a selector is made once and shown again', () =>
{
	using page = createPage();

	for (const type of ['mediapool', 'playlists', 'templates'])
		assertEquals(page.factory.create(type) === page.factory.create(type), true, type);
});

Deno.test('the playlists and the templates are found with the services of the composer', async () =>
{
	using page = createPage();

	await page.factory.create('playlists').showSelector(page.container);
	await page.factory.create('templates').showSelector(page.container);

	assertEquals(page.playlists.calls, [['internal', '']]);
	assertEquals(page.templates.calls, 1);
});

Deno.test('an unknown kind has no selector', () =>
{
	using page = createPage();

	assertEquals(page.factory.create('channels'), undefined);
});
