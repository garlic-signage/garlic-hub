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
import {ZonePropertiesView}           from "@js/playlists/compose/multizone/Views/ZonePropertiesView.js";

// the properties of templates/playlists/multizone.mustache
const PAGE = `
	<fieldset id="zone_properties" disabled>
		<input type="text" id="zone_name">
		<input type="number" id="zone_left"><input type="number" id="zone_top">
		<input type="number" id="zone_width"><input type="number" id="zone_height">
		<input type="color" id="zone_bgcolor"><input type="checkbox" id="zone_transparent">
	</fieldset>`;

function values(view)
{
	return [view.name.value, view.left.value, view.top.value, view.width.value, view.height.value];
}

Deno.test('the fields of the properties are read from the page', () =>
{
	using dom  = createDom(PAGE);
	const view = new ZonePropertiesView();

	for (const field of ['name', 'left', 'top', 'width', 'height', 'bgcolor', 'transparent'])
		assertSameElement(view[field], dom.document.getElementById(field === 'transparent' ? 'zone_transparent' : 'zone_' + field), field);
});

Deno.test('the values of a zone are shown and cleared', () =>
{
	using _dom = createDom(PAGE);
	const view = new ZonePropertiesView();

	view.show('Lobby', 10, 20, 300, 200);
	assertEquals(values(view), ['Lobby', '10', '20', '300', '200']);

	view.bgcolor.value = '#ff0000';
	view.clear();
	assertEquals([...values(view), view.bgcolor.value], ['', '', '', '', '', '']);
});

Deno.test('the properties are enabled and disabled together', () =>
{
	using dom      = createDom(PAGE);
	const view     = new ZonePropertiesView();
	const fieldset = dom.document.getElementById('zone_properties');

	view.enable();
	assertEquals(fieldset.disabled, false);

	view.disable();
	assertEquals(fieldset.disabled, true);
});

Deno.test('a transparent zone has no color to choose, a colored one shows its color', () =>
{
	using _dom = createDom(PAGE);
	const view = new ZonePropertiesView();

	view.showTransparent();
	assertEquals([view.transparent.checked, view.bgcolor.disabled], [true, true]);

	view.showColor('#00ff00');
	assertEquals([view.transparent.checked, view.bgcolor.disabled, view.bgcolor.value], [false, false, '#00ff00']);

	view.enableColor(false);
	assertEquals(view.bgcolor.disabled, true);
	view.enableColor(true);
	assertEquals(view.bgcolor.disabled, false);
});
