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

import {assertEquals}        from "@std/assert";
import {createDom}           from "@support/dom.js";
import {ConditionalPlayForm} from "@js/playlists/compose/standard/editors/ConditionalPlayForm.js";

const WEEKDAYS = [1, 2, 4, 8, 16, 32, 64];

// templates/playlists/conditional-play.mustache, the editor the server sends
const PAGE = `
	<input type="checkbox" id="enable_conditional_play" value="1">
	<fieldset id="edit_conditional_play">
		<input type="checkbox" id="enable_date_period" value="1">
		<div id="edit_date_period">
			<input type="date" id="date_from" value="2026-01-01">
			<input type="date" id="date_until" value="2026-12-31">
		</div>
		<input type="checkbox" id="enable_time_period" value="1">
		<div id="edit_time_period">
			<input type="time" id="time_from" value="08:00">
			<input type="time" id="time_until" value="18:00">
		</div>
		<div id="checkboxes_weekdays">
			${WEEKDAYS.map(day => `<input type="checkbox" value="${day}" id="enable_weekday_${day}" class="enable_weekdays">`).join('')}
		</div>
	</fieldset>`;

/**
 * The slider of a weekday: the test sets whether it is enabled and where its handles are.
 */
class FakeSlider
{
	enabled   = false;
	positions = [0, 96];

	constructor(weekdayId, start, end)
	{
		this.created = [weekdayId, start, end];
	}

	isEnabled()
	{
		return this.enabled;
	}

	getHandlePositions()
	{
		return this.positions;
	}
}

class FakeSliderFactory
{
	sliders = {};

	create(weekdayId, start, end)
	{
		this.sliders[weekdayId] = new FakeSlider(weekdayId, start, end);
		return this.sliders[weekdayId];
	}
}

/**
 * linkedom does not take the checked attribute into the property: the boxes are set like the server checked them.
 */
function createPage({conditional = true, date = true, time = false} = {})
{
	const dom      = createDom(PAGE);
	const document = dom.document;
	const factory  = new FakeSliderFactory();
	const box      = (id) => document.getElementById(id);
	box('enable_conditional_play').checked = conditional;
	box('enable_date_period').checked      = date;
	box('enable_time_period').checked      = time;
	const clicked = [];
	for (const day of WEEKDAYS)
		box('enable_weekday_' + day).addEventListener('click', () => clicked.push(day));

	return {
		document, factory, box, clicked,
		form:    new ConditionalPlayForm(factory),
		display: (id) => box(id).style.display,
		[Symbol.dispose]: () => dom[Symbol.dispose]()
	};
}

function click(page, id, checked)
{
	page.box(id).checked = checked;
	page.box(id).dispatchEvent(new Event('click'));
}

Deno.test('every weekday gets a slider with its saved quarters of an hour, the whole day without', () =>
{
	using page = createPage();

	page.form.init({weekdays: {2: {from: 32, until: 72}, 64: {from: 0, until: 48}}});

	assertEquals(WEEKDAYS.map(day => page.factory.sliders[day].created), [
		[1, 0, 96], [2, 32, 72], [4, 0, 96], [8, 0, 96], [16, 0, 96], [32, 0, 96], [64, 0, 48]
	]);
});

Deno.test('the checked parts are shown, an unchecked period is hidden and emptied', () =>
{
	using page = createPage({conditional: true, date: true, time: false});

	page.form.init({});

	assertEquals(page.display('edit_conditional_play'), 'block');
	assertEquals(page.display('edit_date_period'), 'flex');
	assertEquals(page.box('date_from').value, '2026-01-01');
	assertEquals(page.display('edit_time_period'), 'none');
	assertEquals([page.box('time_from').value, page.box('time_until').value], ['', '']);
});

Deno.test('without conditional play everything is hidden, emptied and the weekdays are switched off', () =>
{
	using page = createPage({conditional: false, date: true, time: true});

	page.form.init({});

	assertEquals(page.display('edit_conditional_play'), 'none');
	assertEquals([page.box('enable_date_period').checked, page.box('enable_time_period').checked], [false, false]);
	assertEquals([page.display('edit_date_period'), page.display('edit_time_period')], ['none', 'none']);
	assertEquals([page.box('date_from').value, page.box('time_until').value], ['', '']);
	// checked and clicked: the browser unchecks them and their sliders are disabled
	assertEquals(page.clicked, WEEKDAYS);
});

Deno.test('the checkboxes show and hide their parts', () =>
{
	using page = createPage({conditional: true, date: false, time: false});
	page.form.init({});

	click(page, 'enable_date_period', true);
	assertEquals(page.display('edit_date_period'), 'flex');

	click(page, 'enable_time_period', true);
	assertEquals(page.display('edit_time_period'), 'flex');

	click(page, 'enable_conditional_play', false);
	assertEquals(page.display('edit_conditional_play'), 'none');
	assertEquals([page.display('edit_date_period'), page.display('edit_time_period')], ['none', 'none']);

	click(page, 'enable_conditional_play', true);
	assertEquals(page.display('edit_conditional_play'), 'block');
});

Deno.test('without conditional play nothing is saved', () =>
{
	using page = createPage({conditional: false});
	page.form.init({});

	assertEquals(page.form.collectValues(), {});
});

Deno.test('the periods and the enabled weekdays with their quarters of an hour are collected', () =>
{
	using page = createPage({conditional: true, date: true, time: true});
	page.form.init({});
	page.box('time_from').value  = '08:00';
	page.box('time_until').value = '18:00';
	page.factory.sliders[2].enabled    = true;
	page.factory.sliders[2].positions  = [32, 72];
	page.factory.sliders[64].enabled   = true;
	page.factory.sliders[64].positions = ['4.00', '48.00'];

	assertEquals(page.form.collectValues(), {
		date:     {from: '2026-01-01', until: '2026-12-31'},
		time:     {from: '08:00', until: '18:00'},
		weekdays: {2: {from: 32, until: 72}, 64: {from: 4, until: 48}}
	});
});

Deno.test('a switched off period is sent empty', () =>
{
	using page = createPage({conditional: true, date: false, time: false});
	page.form.init({});

	assertEquals(page.form.collectValues(), {date: {from: '', until: ''}, time: {from: '', until: ''}, weekdays: {}});
});
