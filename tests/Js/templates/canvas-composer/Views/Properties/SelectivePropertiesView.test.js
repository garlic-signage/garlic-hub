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
import {PROPERTIES_PAGE}              from "@support/ComposerPropertiesFakes.js";
import {SelectivePropertiesView}      from "@js/templates/canvas-composer/Views/Properties/SelectivePropertiesView.js";

Deno.test('the fill colour is a field of the page which is shown and hidden', () =>
{
	using dom   = createDom(PROPERTIES_PAGE);
	const view  = new SelectivePropertiesView();
	const field = dom.document.getElementById('fillColor');

	assertSameElement(view.fillColor, field);

	view.show();
	assertEquals(field.style.display, 'block');
	view.hide();
	assertEquals(field.style.display, 'none');
});

Deno.test('the fill colour is set and read', () =>
{
	using _dom = createDom(PROPERTIES_PAGE);
	const view = new SelectivePropertiesView();

	view.setFillColorValue('#00ff00');

	assertEquals(view.getfillColorValue(), '#00ff00');
});
