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

import {assertEquals}                             from "@std/assert";
import {assertSameElement, createDom}             from "@support/dom.js";
import {FakeToggleButtonFactory, PROPERTIES_PAGE} from "@support/ComposerPropertiesFakes.js";
import {GroupPropertiesView}                      from "@js/templates/canvas-composer/Views/Properties/GroupPropertiesView.js";

function createView()
{
	const dom     = createDom(PROPERTIES_PAGE);
	const factory = new FakeToggleButtonFactory();
	const view    = new GroupPropertiesView(factory);
	return {document: dom.document, view, button: factory.buttons.get('object_group'), [Symbol.dispose]: () => dom[Symbol.dispose]()};
}

Deno.test('the button is the one to group of the page', () =>
{
	using page = createView();

	assertSameElement(page.view.getGroupElement(), page.document.getElementById('object_group'));
	assertSameElement(page.view.group.getElement(), page.document.getElementById('object_group'));
});

Deno.test('a group offers to ungroup, a selection to group', () =>
{
	using page = createView();

	page.view.show('group');
	page.view.show('activeSelection');

	assertEquals(page.button.updates, [['display', 'ungroup'], ['display', 'group']]);
	assertEquals(page.button.shown, true);
});

Deno.test('after grouping or ungrouping the button offers the other way', () =>
{
	using page = createView();

	page.view.ungrouping();
	page.view.grouping();

	assertEquals(page.button.updates, [['display', 'ungroup'], ['display', 'group']]);
});

Deno.test('the button is hidden', () =>
{
	using page = createView();
	page.view.show('group');

	page.view.hide();

	assertEquals(page.button.shown, false);
});
