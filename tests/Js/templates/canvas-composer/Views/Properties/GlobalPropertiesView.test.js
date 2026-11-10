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
import {GlobalPropertiesView}         from "@js/templates/canvas-composer/Views/Properties/GlobalPropertiesView.js";

const ELEMENTS = {
	opacity: 'opacity', strokeColor: 'strokeColor', strokeWidth: 'strokeWidth',
	alignLeft: 'alignLeft', alignCenter: 'alignCenter', alignRight: 'alignRight',
	alignTop: 'alignTop', alignMiddle: 'alignMiddle', alignBottom: 'alignBottom'
};

function display(document, id)
{
	return document.getElementById(id).style.display;
}

Deno.test('the elements of the panel are read from the page', () =>
{
	using dom  = createDom(PROPERTIES_PAGE);
	const view = new GlobalPropertiesView();

	for (const [getter, id] of Object.entries(ELEMENTS))
		assertSameElement(view[getter], dom.document.getElementById(id), getter);
});

Deno.test('the template has no fields for the scale', () =>
{
	using _dom = createDom(PROPERTIES_PAGE);
	const view = new GlobalPropertiesView();

	assertSameElement(view.scaleX, null);
	assertSameElement(view.scaleY, null);
});

Deno.test('the panel is shown and hidden', () =>
{
	using dom  = createDom(PROPERTIES_PAGE);
	const view = new GlobalPropertiesView();

	view.showGlobalProperties();
	assertEquals(display(dom.document, 'globalProperties'), 'flex');

	view.hideGlobalProperties();
	assertEquals(display(dom.document, 'globalProperties'), 'none');
});

Deno.test('opacity and width of the outline are shown in their field and beside it', () =>
{
	using dom  = createDom(PROPERTIES_PAGE);
	const view = new GlobalPropertiesView();

	view.setOpacityValue(40);
	view.setStrokeWidthValue(6);

	assertEquals([view.getOpacityValue(), dom.document.getElementById('opacityDisplay').textContent], ['40', '40']);
	assertEquals([view.getStrokeWidthValue(), dom.document.getElementById('strokeWidthDisplay').textContent], ['6', '6']);
});

Deno.test('the colour of the outline is set and read', () =>
{
	using _dom = createDom(PROPERTIES_PAGE);
	const view = new GlobalPropertiesView();

	view.setStrokeColorValue('#ff0000');

	assertEquals(view.getStrokeColorValue(), '#ff0000');
});
