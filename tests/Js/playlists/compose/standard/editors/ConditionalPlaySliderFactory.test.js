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
import {stub}                           from "@std/testing/mock";
import {createDom}                      from "@support/dom.js";
import noUiSlider                       from "@js/external/nouislider.min.mjs";
import {ConditionalPlaySlider}          from "@js/playlists/compose/standard/editors/ConditionalPlaySlider.js";
import {ConditionalPlaySliderFactory}   from "@js/playlists/compose/standard/editors/ConditionalPlaySliderFactory.js";

Deno.test('the factory creates the slider of the weekday with its handles', () =>
{
	using dom      = createDom(`<input type="checkbox" id="enable_weekday_4"><span id="range_from_4"></span>
		<span id="range_until_4"></span><div id="slider_4"></div>`);
	const created  = [];
	// the real noUiSlider needs a layout
	using _create  = stub(noUiSlider, 'create', (element, options) =>
	{
		created.push([element.id, options.start]);
		element.noUiSlider = {enable() {}, disable() {}, get: () => [0, 96], on() {}};
	});

	const slider = new ConditionalPlaySliderFactory().create(4, 8, 40);

	assertInstanceOf(slider, ConditionalPlaySlider);
	assertEquals(created, [['slider_4', [8, 40]]]);
	assertEquals(dom.document.getElementById('slider_4').noUiSlider !== undefined, true);
});
