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

import {assertInstanceOf, assertNotStrictEquals} from "@std/assert";
import {createDom}                               from "@support/dom.js";
import {Widget}                                  from "@js/playlists/compose/standard/editors/Widget.js";
import {WidgetFactory}                           from "@js/playlists/compose/standard/editors/WidgetFactory.js";

// the edit dialog of templates/playlists/compose.mustache, the editor finds it when it is created
const PAGE = `
	<dialog id="editItemDialog">
		<span id="editItemName"></span><button id="closeEditDialog"></button>
		<div id="editItemErrorMessage"></div>
		<form method="dialog" id="editItemForm">
			<div id="editItemContent"></div>
			<input type="hidden" id="editItemId">
			<button value="submit" type="submit">Save</button>
			<button value="cancel" formnovalidate>Cancel</button>
		</form>
	</dialog>`;

Deno.test('every click on the item gets a new editor', () =>
{
	using _dom    = createDom(PAGE);
	const factory = new WidgetFactory();

	const first  = factory.create();
	const second = factory.create();

	assertInstanceOf(first, Widget);
	assertInstanceOf(second, Widget);
	assertNotStrictEquals(first, second);
});
