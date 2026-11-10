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
import {ShadowPropertiesView}         from "@js/templates/canvas-composer/Views/Properties/ShadowPropertiesView.js";

const FIELDS = ['shadowColor', 'shadowOffsetX', 'shadowOffsetY', 'shadowBlur'];

Deno.test('the elements of the shadow are read from the page', () =>
{
	using dom  = createDom(PROPERTIES_PAGE);
	const view = new ShadowPropertiesView();

	for (const id of [...FIELDS, 'deleteShadow', 'createShadow'])
		assertSameElement(view[id], dom.document.getElementById(id), id);
});

Deno.test('with a shadow it can be deleted and changed', () =>
{
	using dom  = createDom(PROPERTIES_PAGE);
	const view = new ShadowPropertiesView();

	view.toggleShadowVisibility(true);

	assertEquals([view.deleteShadow.style.display, view.createShadow.style.display], ['block', 'none']);
	assertEquals(FIELDS.map(id => dom.document.getElementById(id).disabled), [false, false, false, false]);
});

Deno.test('without a shadow it can only be created', () =>
{
	using dom  = createDom(PROPERTIES_PAGE);
	const view = new ShadowPropertiesView();

	view.toggleShadowVisibility(false);

	assertEquals([view.deleteShadow.style.display, view.createShadow.style.display], ['none', 'block']);
	assertEquals(FIELDS.map(id => dom.document.getElementById(id).disabled), [true, true, true, true]);
});

Deno.test('the values of the shadow are set and read', () =>
{
	using _dom = createDom(PROPERTIES_PAGE);
	const view = new ShadowPropertiesView();

	view.setShadowColorValue('#123456');
	view.setShadowOffsetXValue(3);
	view.setShadowOffsetYValue(4);
	view.setShadowBlurValue(5);

	assertEquals([view.getShadowColorValue(), view.getShadowOffsetXValue(), view.getShadowOffsetYValue(), view.getShadowBlurValue()],
		['#123456', '3', '4', '5']);
});
