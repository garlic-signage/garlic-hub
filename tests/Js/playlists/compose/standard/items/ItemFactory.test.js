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
import {assertSameElement, createDom}   from "@support/dom.js";
import {ItemFactory}                    from "@js/playlists/compose/standard/items/ItemFactory.js";
import {Item}                           from "@js/playlists/compose/standard/items/Item.js";

Deno.test('the factory builds an item with its data, the service and the editors', () =>
{
	// Item reads the edition and creates its flash messages when it is built
	using _dom = createDom(`<input type="hidden" id="cms_edition" value="core">
		<template id="flashMessageTemplate"><div class="message-bar"><span></span></div></template>`);
	const editors = {created: 0, create() { this.created++; return {}; }};

	const item = new ItemFactory(editors, editors, editors).create({item_id: 12}, {});

	assertInstanceOf(item, Item);
	assertSameElement(item.previewAction, null);
	assertSameElement(item.deleteItemAction, null);
});

Deno.test('the item opens the editors of the factory', async () =>
{
	using dom = createDom(`<input type="hidden" id="cms_edition" value="core">
		<template id="flashMessageTemplate"><div class="message-bar"><span></span></div></template>
		<template id="playlistItemTemplate">
			<li class="playlist-item"><img class="thumbnail" src="" alt="" /><div class="actions">
				<span class="item-duration"></span><button class="item-preview"></button><a class="link-playlist"></a>
				<button class="conditional-play"></button><button class="edit-trigger"></button><button class="edit-widget"></button>
				<a class="edit-template"></a><button class="edit-settings"></button><button class="copy-item"></button>
				<button class="delete-item"></button></div><p class="item-name"></p></li>
		</template>`);
	const opened  = [];
	const editors = (kind) => ({create: () => ({
		fetch: async () => ({success: true}), fetchBeginTrigger: async () => ({success: true}), initDialog: () => opened.push(kind)
	})});

	const item = new ItemFactory(editors('widget'), editors('conditional'), editors('trigger')).create(
		{item_id: 12, item_name: 'Lobby', item_type: 'mediapool', item_duration: 10, mimetype: 'application/widget',
			content_data: 'a:1:{}', urls: {thumbnail: ''}}, {});
	item.belongsToMasterPlaylist = true;
	item.buildItemElement();
	for (const action of [item.conditionalPlayAction, item.editTriggerAction, item.editWidgetAction])
		action.dispatchEvent(new Event('click'));
	await new Promise(resolve => setTimeout(resolve, 0));

	assertEquals(opened, ['conditional', 'trigger', 'widget']);
});
