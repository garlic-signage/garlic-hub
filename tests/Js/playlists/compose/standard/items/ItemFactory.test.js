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
