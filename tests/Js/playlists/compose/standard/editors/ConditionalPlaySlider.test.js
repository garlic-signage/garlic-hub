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

import {assertEquals}          from "@std/assert";
import {stub}                  from "@std/testing/mock";
import {createDom}             from "@support/dom.js";
import noUiSlider              from "@js/external/nouislider.min.mjs";
import {ConditionalPlaySlider} from "@js/playlists/compose/standard/editors/ConditionalPlaySlider.js";

// a weekday of templates/playlists/conditional-play.mustache
const PAGE = `
	<input type="checkbox" value="2" id="enable_weekday_2" class="enable_weekdays">
	<span id="range_from_2"></span> - <span id="range_until_2"></span>
	<div class="slider-range" id="slider_2"></div>`;

/**
 * The API of noUiSlider at the element: the real one needs a layout. The handles are where the test puts them.
 */
class FakeSliderApi
{
	calls     = [];
	handlers  = {};
	positions = [0, 96];

	constructor(options)
	{
		this.options = options;
	}

	enable()  { this.calls.push('enable'); }
	disable() { this.calls.push('disable'); }

	get(unencoded)
	{
		this.calls.push(['get', unencoded]);
		return this.positions;
	}

	on(event, handler)
	{
		this.handlers[event] = handler;
	}
}

/**
 * linkedom does not take the checked attribute into the property: the box is set like the server checked it.
 */
function createPage(checked, positions = [32, 72])
{
	const dom      = createDom(PAGE);
	const document = dom.document;
	document.getElementById('enable_weekday_2').checked = checked;
	const created  = [];
	const create   = stub(noUiSlider, 'create', (element, options) =>
	{
		element.noUiSlider = new FakeSliderApi(options);
		element.noUiSlider.positions = positions;
		created.push(element);
		return element.noUiSlider;
	});

	return {
		document, created,
		api:   () => document.getElementById('slider_2').noUiSlider,
		range: () => [document.getElementById('range_from_2').innerHTML, document.getElementById('range_until_2').innerHTML],
		box:   document.getElementById('enable_weekday_2'),
		[Symbol.dispose]: () => { create.restore(); dom[Symbol.dispose](); }
	};
}

Deno.test('the slider of the weekday has two handles in quarters of an hour of a day', () =>
{
	using page = createPage(true);

	new ConditionalPlaySlider().create(2, 32, 72);

	assertEquals(page.created.map(element => element.id), ['slider_2']);
	assertEquals(page.api().options, {start: [32, 72], connect: true, step: 1, margin: 1, range: {min: [0], max: [96]}});
});

Deno.test('a checked weekday is enabled and shows its times', () =>
{
	using page = createPage(true);

	const slider = new ConditionalPlaySlider();
	slider.create(2, 32, 72);

	assertEquals(page.api().calls.includes('enable'), true);
	assertEquals(page.range(), ['08:00', '18:00']);
	assertEquals(slider.isEnabled(), true);
});

Deno.test('an unchecked weekday is disabled and shows no times', () =>
{
	using page = createPage(false);

	const slider = new ConditionalPlaySlider();
	slider.create(2, 32, 72);

	assertEquals(page.api().calls, ['disable']);
	assertEquals(page.range(), ['', '']);
	assertEquals(slider.isEnabled(), false);
});

Deno.test('the checkbox switches the weekday on and off', () =>
{
	using page = createPage(false);
	new ConditionalPlaySlider().create(2, 32, 72);

	page.box.checked = true;
	page.box.dispatchEvent(new Event('click'));
	assertEquals(page.api().calls.at(-1), ['get', true]);
	assertEquals(page.api().calls.at(-2), 'enable');
	assertEquals(page.range(), ['08:00', '18:00']);

	page.box.checked = false;
	page.box.dispatchEvent(new Event('click'));
	assertEquals(page.api().calls.at(-1), 'disable');
	assertEquals(page.range(), ['', '']);
});

Deno.test('moving a handle shows the new times of an enabled weekday only', () =>
{
	using page = createPage(true);
	new ConditionalPlaySlider().create(2, 32, 72);

	page.api().handlers.update(['4.00', '93.00']);
	assertEquals(page.range(), ['01:00', '23:15']);

	page.box.checked = false;
	page.api().handlers.update(['8.00', '90.00']);
	assertEquals(page.range(), ['01:00', '23:15']);
});

Deno.test('the end of the day is shown as midnight', () =>
{
	using page = createPage(true, [0, 96]);

	new ConditionalPlaySlider().create(2, 0, 96);

	assertEquals(page.range(), ['00:00', '00:00']);
});

Deno.test('the positions of the handles are the numbers without format', () =>
{
	using page = createPage(true);
	const slider = new ConditionalPlaySlider();
	slider.create(2, 32, 72);

	assertEquals(slider.getHandlePositions(), [32, 72]);
	assertEquals(page.api().calls.at(-1), ['get', true]);
});
