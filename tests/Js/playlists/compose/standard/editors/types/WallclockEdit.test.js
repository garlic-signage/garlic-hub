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
import {WallclockEdit}                from "@js/playlists/compose/standard/editors/types/WallclockEdit.js";

// the template of templates/playlists/trigger.mustache, with two weekdays
const PAGE = `
	<template id="wallclockTemplate">
		<div class="trigger-form">
			<p><button type="button" class="bi bi-trash remove naked-button">Remove</button></p>
			<div class="edit-point">
				<label>Weekday
					<select class="edit-weekday">
						<option value="0">-</option>
						<option value="1">Monday</option>
						<option value="3">Wednesday</option>
					</select>
				</label>
				<label>
					<select class="edit-weekday-prefix">
						<option value="+">after</option>
						<option value="-">before</option>
					</select>
				</label>
				<label>Date<input type="datetime-local" class="edit-datetime" step="1"></label>
			</div>
			<div class="edit-repeats">
				<label><input type="radio" value="-1" class="no-repeats-select"> none</label>
				<label><input type="radio" value="0" class="infinite-repeats-select"> infinite</label>
				<label>
					<input type="radio" value="1" class="number-repeats-select"> number
					<input type="number" class="number-repeats" min="1" max="9999">
				</label>
			</div>
			<div class="edit-repeat-periods">
				<input type="number" class="repeat-minutes" min="0" max="59">
				<input type="number" class="repeat-hours" min="0" max="23">
				<input type="number" class="repeat-days" min="0" max="7">
				<input type="number" class="repeat-weeks" min="0" max="4">
				<input type="number" class="repeat-months" min="0" max="11">
				<input type="number" class="repeat-years" min="0" max="11">
			</div>
		</div>
	</template>
	<div id="wallclocksContainer"></div>`;

const NO_PERIODS = {repeat_minutes: 0, repeat_hours: 0, repeat_days: 0, repeat_weeks: 0, repeat_months: 0, repeat_years: 0};

/**
 * Editors in the container of the page, as the TriggerForm adds them; the id is set by the EditorsManager.
 */
function createPage()
{
	const dom       = createDom(PAGE);
	const container = dom.document.getElementById('wallclocksContainer');
	// linkedom has no getElementsByName(), getValues() finds the chosen repeat with it
	dom.document.getElementsByName = (name) => dom.document.querySelectorAll(`[name="${name}"]`);

	return {
		document: dom.document, container,
		add(data = {}, id = 0)
		{
			const editor = new WallclockEdit();
			editor.id    = id;
			editor.init(data);
			const form   = editor.getEditor().querySelector('.trigger-form');
			container.appendChild(editor.getEditor());
			return {editor, part: (selector) => form.querySelector(selector), form};
		},
		[Symbol.dispose]: () => dom[Symbol.dispose]()
	};
}

function visibility(wallclock, selector)
{
	return wallclock.part(selector).style.visibility;
}

function click(element)
{
	element.dispatchEvent(new Event('click'));
}

Deno.test('a new wallclock has no weekday, no date and no repeats', () =>
{
	using page      = createPage();
	const wallclock = page.add();

	assertEquals(wallclock.editor.exists, true);
	assertEquals(visibility(wallclock, '.edit-weekday-prefix'), 'hidden');
	assertEquals(wallclock.editor.getValues(), {weekday: 0, iso_date: '', repeat_counts: '-1', ...NO_PERIODS});
});

Deno.test('a missing date is marked red, a given one black', () =>
{
	using page      = createPage();
	const wallclock = page.add();
	const date      = wallclock.part('.edit-datetime');

	wallclock.editor.getValues();
	assertEquals(date.style.color, 'red');

	date.value = '2026-11-10T08:30';
	wallclock.editor.getValues();
	assertEquals(date.style.color, 'black');
});

Deno.test('the seconds the browser leaves out are added to the date', () =>
{
	using page      = createPage();
	const wallclock = page.add({iso_date: '2026-11-10T08:30'});

	assertEquals(wallclock.editor.getValues().iso_date, '2026-11-10T08:30:00');

	wallclock.part('.edit-datetime').value = '2026-11-10T08:30:15';
	assertEquals(wallclock.editor.getValues().iso_date, '2026-11-10T08:30:15');
});

Deno.test('a weekday is given with its prefix, after the date by default', () =>
{
	using page = createPage();

	assertEquals(page.add({weekday: '3'}).editor.getValues().weekday, '+3');
	assertEquals(page.add({weekday: '3', weekday_prefix: '+'}, 1).editor.getValues().weekday, '+3');
	assertEquals(page.add({weekday: '1', weekday_prefix: '-'}, 2).editor.getValues().weekday, '-1');
});

Deno.test('the prefix is shown only with a weekday', () =>
{
	using page      = createPage();
	const wallclock = page.add({weekday: '3'});
	const weekday   = wallclock.part('.edit-weekday');
	assertEquals(visibility(wallclock, '.edit-weekday-prefix'), '');

	weekday.value = '0';
	weekday.dispatchEvent(new Event('change'));
	assertEquals(visibility(wallclock, '.edit-weekday-prefix'), 'hidden');

	weekday.value = '1';
	weekday.dispatchEvent(new Event('change'));
	assertEquals(visibility(wallclock, '.edit-weekday-prefix'), 'visible');
});

Deno.test('a wallclock without repeats hides the periods and gives no periods', () =>
{
	using page      = createPage();
	const wallclock = page.add({iso_date: '2026-11-10T08:30:00', repeat_counts: '-1', repeat_hours: '2'});

	assertEquals(wallclock.part('.no-repeats-select').checked, true);
	assertEquals([visibility(wallclock, '.edit-repeat-periods'), visibility(wallclock, '.number-repeats')], ['hidden', 'hidden']);
	assertEquals(wallclock.editor.getValues(), {weekday: 0, iso_date: '2026-11-10T08:30:00', repeat_counts: '-1', ...NO_PERIODS});
});

Deno.test('an infinite repeat gives its periods', () =>
{
	using page      = createPage();
	const wallclock = page.add({iso_date: '2026-11-10T08:30:00', repeat_counts: '0', repeat_minutes: '15', repeat_hours: '2',
		repeat_days: '1', repeat_weeks: '0', repeat_months: '0', repeat_years: '0'});

	assertEquals(wallclock.part('.infinite-repeats-select').checked, true);
	assertEquals([visibility(wallclock, '.edit-repeat-periods'), visibility(wallclock, '.number-repeats')], ['visible', 'hidden']);
	assertEquals(wallclock.editor.getValues(), {weekday: 0, iso_date: '2026-11-10T08:30:00', repeat_counts: '0',
		repeat_minutes: '15', repeat_hours: '2', repeat_days: '1', repeat_weeks: '0', repeat_months: '0', repeat_years: '0'});
});

Deno.test('a number of repeats gives the number and the periods', () =>
{
	using page      = createPage();
	const wallclock = page.add({iso_date: '2026-11-10T08:30:00', repeat_counts: '5', repeat_days: '1'});

	assertEquals(wallclock.part('.number-repeats-select').checked, true);
	assertEquals(wallclock.part('.number-repeats').value, '5');
	assertEquals([visibility(wallclock, '.edit-repeat-periods'), visibility(wallclock, '.number-repeats')], ['visible', 'visible']);
	const values = wallclock.editor.getValues();
	assertEquals([values.repeat_counts, values.repeat_days], ['5', '1']);
});

Deno.test('the minutes are read as a number', () =>
{
	using page      = createPage();
	const wallclock = page.add({repeat_counts: '0', repeat_minutes: '07'});

	assertEquals(wallclock.part('.repeat-minutes').value, '7');
});

Deno.test('the choice of the repeats shows and hides the periods and the number', () =>
{
	using page      = createPage();
	const wallclock = page.add();
	const periods   = () => [visibility(wallclock, '.edit-repeat-periods'), visibility(wallclock, '.number-repeats')];

	click(wallclock.part('.number-repeats-select'));
	assertEquals(periods(), ['visible', 'visible']);

	click(wallclock.part('.infinite-repeats-select'));
	assertEquals(periods(), ['visible', 'hidden']);

	click(wallclock.part('.no-repeats-select'));
	assertEquals(periods(), ['hidden', 'hidden']);
});

Deno.test('the repeats of every wallclock are a group of their own', () =>
{
	using page  = createPage();
	const first  = page.add({repeat_counts: '0'}, 0);
	const second = page.add({repeat_counts: '3'}, 1);

	assertEquals(first.part('.no-repeats-select').getAttribute('name'), 'repeats-select0');
	assertEquals(second.part('.number-repeats-select').getAttribute('name'), 'repeats-select1');
	assertEquals(first.editor.getValues().repeat_counts, '0');
	assertEquals(second.editor.getValues().repeat_counts, '3');
});

Deno.test('the remove button takes the wallclock away', () =>
{
	using page      = createPage();
	const wallclock = page.add();

	click(page.container.querySelector('.remove'));

	assertEquals(wallclock.editor.exists, false);
	assertSameElement(page.container.querySelector('.trigger-form'), null);
});
