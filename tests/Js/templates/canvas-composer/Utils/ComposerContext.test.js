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

import {assertEquals}    from "@std/assert";
import {createDom}       from "@support/dom.js";
import {replaceGlobals}  from "@support/globals.js";
import {ComposerContext} from "@js/templates/canvas-composer/Utils/ComposerContext.js";

// the hidden fields of templates/templates/canvas.mustache, the ids of a playlist item only for an item
const TEMPLATE = '<input type="hidden" id="template_id" value="7">';
const ITEM     = '<input type="hidden" id="item_id" value="12"><input type="hidden" id="playlist_id" value="3">';

Deno.test('a template has its id and no playlist item', () =>
{
	using _dom = createDom(TEMPLATE);

	const context = new ComposerContext({});

	assertEquals([context.templateId, context.playlistId, context.itemId], ['7', 0, 0]);
});

Deno.test('a template of a playlist item knows the item and the playlist', () =>
{
	using _dom = createDom(TEMPLATE + ITEM);

	const context = new ComposerContext({});

	// the values of the fields are texts
	assertEquals([context.templateId, context.playlistId, context.itemId], ['7', '3', '12']);
});

Deno.test('the texts are read from the lang of the page', () =>
{
	using _dom     = createDom(TEMPLATE);
	using _globals = replaceGlobals({lang: {no_objects: 'No objects of the page'}});

	const context = new ComposerContext({no_objects: 'No objects of the context'});

	// the lang given to the constructor is not used (see the findings)
	assertEquals(context.getLangByKey('no_objects'), 'No objects of the page');
	assertEquals(context.getLangByKey('unknown'), undefined);
});
