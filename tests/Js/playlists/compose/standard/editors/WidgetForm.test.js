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

import {assertEquals} from "@std/assert";
import {createDom}    from "@support/dom.js";
import {WidgetForm}   from "@js/playlists/compose/standard/editors/WidgetForm.js";

/**
 * linkedom has no canvas: the 2d context standardizes a color like the browser, a name becomes its hex value.
 */
function createPage()
{
	const dom       = createDom('<div id="editItemContent"></div>');
	const document  = dom.document;
	const prototype = Object.getPrototypeOf(document.createElement('canvas'));
	const original  = Object.getOwnPropertyDescriptor(prototype, 'getContext');
	const names     = {red: '#ff0000', white: '#ffffff'};
	Object.defineProperty(prototype, 'getContext', {configurable: true, value: () =>
	{
		let fillStyle = '#000000';
		return {
			get fillStyle() { return fillStyle; },
			set fillStyle(color) { fillStyle = names[color] ?? color.toLowerCase(); }
		};
	}});

	return {
		document,
		form:    new WidgetForm(),
		content: document.getElementById('editItemContent'),
		[Symbol.dispose]: () =>
		{
			if (original === undefined)
				delete prototype.getContext;
			else
				Object.defineProperty(prototype, 'getContext', original);
			dom[Symbol.dispose]();
		}
	};
}

/**
 * The form in the dialog. linkedom takes neither the checked attribute into the property nor knows the options of a
 * select by their index: both are set like the browser does.
 */
function show(page)
{
	page.content.innerHTML = page.form.getForm();
	for (const input of page.content.querySelectorAll('input[checked]'))
		input.checked = true;
	for (const select of page.content.querySelectorAll('select'))
	{
		const options  = [...select.querySelectorAll('option')];
		const selected = options.findIndex(option => option.hasAttribute('selected'));
		options.forEach((option, index) => Object.defineProperty(select, index, {value: option}));
		Object.defineProperty(select, 'selectedIndex', {value: selected === -1 ? 0 : selected, writable: true});
	}
}

function field(page, id)
{
	return page.content.querySelector('#' + id);
}

Deno.test('a widget without preferences has no form', () =>
{
	using page = createPage();

	page.form.parsePreferences({}, {}, 42);

	assertEquals(page.form.getForm(), '');
});

Deno.test('the label is the one of the preference, else its tooltip, else its name', () =>
{
	using page = createPage();

	page.form.parsePreferences({
		city:    {types: 'text', label: 'City', tooltip: 'The city of the weather'},
		country: {types: 'text', tooltip: 'Country code'},
		zip:     {types: 'text'}
	}, {});
	show(page);

	assertEquals([...page.content.querySelectorAll('label')].map(label => label.textContent), ['City', 'Country code', 'zip']);
});

Deno.test('a text field has the saved value, else the default, its tooltip and whether it is mandatory', () =>
{
	using page = createPage();

	page.form.parsePreferences({
		city:    {types: 'text', default: 'Berlin', tooltip: 'Weather of', mandatory: 'true'},
		country: {default: 'DE', mandatory: 'false'}
	}, {city: 'Hamburg'});
	show(page);

	const city = field(page, 'city');
	assertEquals([city.getAttribute('type'), city.getAttribute('name'), city.value], ['text', 'city', 'Hamburg']);
	assertEquals([city.getAttribute('title'), city.hasAttribute('required')], ['Weather of', true]);
	// without a type a preference is a text
	const country = field(page, 'country');
	assertEquals([country.getAttribute('type'), country.value, country.hasAttribute('required')], ['text', 'DE', false]);
});

Deno.test('numbers are limited, an opacity to 0 - 100, an integer to 0 and more', () =>
{
	using page = createPage();

	page.form.parsePreferences({opacity: {types: 'colorOpacity', default: '80'}, seconds: {types: 'integer'}}, {seconds: '30'});
	show(page);

	const opacity = field(page, 'opacity');
	assertEquals([opacity.getAttribute('type'), opacity.getAttribute('min'), opacity.getAttribute('max'), opacity.value], ['number', '0', '100', '80']);
	const seconds = field(page, 'seconds');
	assertEquals([seconds.getAttribute('min'), seconds.hasAttribute('max'), seconds.value], ['0', false, '30']);
});

Deno.test('a color has the saved value, else the default as hex value', () =>
{
	using page = createPage();

	page.form.parsePreferences({background: {types: 'color', default: 'red'}, text: {types: 'color', default: 'red'}}, {text: '#00ff00'});
	show(page);

	assertEquals([field(page, 'background').getAttribute('type'), field(page, 'background').value], ['color', '#ff0000']);
	assertEquals(field(page, 'text').value, '#00ff00');
});

Deno.test('a radio checks the saved option, else the default', () =>
{
	using page = createPage();
	const props = {types: 'radio', default: 'metric', options: {Celsius: 'metric', Fahrenheit: 'imperial'}};

	page.form.parsePreferences({units: props, other: props}, {other: 'imperial'});
	show(page);

	assertEquals(field(page, 'units_Celsius').checked, true);
	assertEquals(field(page, 'units_Fahrenheit').hasAttribute('checked'), false);
	assertEquals(field(page, 'other_Celsius').hasAttribute('checked'), false);
	assertEquals(field(page, 'other_Fahrenheit').checked, true);
	assertEquals([field(page, 'units_Celsius').getAttribute('name'), field(page, 'units_Celsius').value], ['units', 'metric']);
});

Deno.test('a list selects the saved key, else the default by key or text', () =>
{
	for (const type of ['list', 'combo'])
	{
		using page = createPage();
		const options = {de: 'German', en: 'English', fr: 'French'};

		page.form.parsePreferences({
			byKey:  {types: type, default: 'en', options},
			byText: {types: type, default: 'French', options},
			saved:  {types: type, default: 'en', options}
		}, {saved: 'de'});
		show(page);

		const selected = (id) => field(page, id).querySelector('option[selected]')?.getAttribute('value');
		assertEquals([selected('byKey'), selected('byText'), selected('saved')], ['en', 'fr', 'de'], type);
		assertEquals([...field(page, 'saved').querySelectorAll('option')].map(option => option.textContent), ['German', 'English', 'French']);
	}
});

Deno.test('the fields are named by the item, two widgets in a page do not mix', () =>
{
	using page = createPage();

	page.form.parsePreferences({city: {types: 'text'}, units: {types: 'radio', options: {C: 'metric'}}}, {}, 42);
	show(page);

	assertEquals(field(page, '42_city').getAttribute('name'), 'city');
	assertEquals(page.content.querySelectorAll('#\\34 2_units_C').length, 1);
});

Deno.test('labels, options and values are text, not HTML', () =>
{
	using page = createPage();

	page.form.parsePreferences({
		'"><img src=x>': {types: 'text', label: '<b>City</b>', tooltip: '" onmouseover="x'},
		units: {types: 'list', options: {'<i>': '<script>x</script>'}}
	}, {'"><img src=x>': '"><script>alert(1)</script>'});
	show(page);

	assertEquals(page.content.querySelectorAll('img, b, i, script').length, 0);
	assertEquals(page.content.querySelector('label').textContent, '<b>City</b>');
	const input = page.content.querySelector('input');
	assertEquals([input.value, input.getAttribute('title')], ['"><script>alert(1)</script>', '" onmouseover="x']);
	assertEquals(page.content.querySelector('option').textContent, '<script>x</script>');
});

Deno.test('the values of all kinds are collected', () =>
{
	using page = createPage();
	page.form.parsePreferences({
		city:       {types: 'text'},
		opacity:    {types: 'colorOpacity'},
		seconds:    {types: 'integer'},
		background: {types: 'color'},
		units:      {types: 'radio', options: {Celsius: 'metric', Fahrenheit: 'imperial'}},
		language:   {types: 'list', options: {de: 'German', en: 'English'}}
	}, {}, 42);
	show(page);
	field(page, '42_city').value       = 'Berlin';
	field(page, '42_opacity').value    = '50';
	field(page, '42_seconds').value    = '30';
	field(page, '42_background').value = '#ffffff';
	field(page, '42_units_Fahrenheit').checked = true;
	field(page, '42_language').selectedIndex   = 1;

	assertEquals(page.form.collectValues(), {
		city: 'Berlin', opacity: '50', seconds: '30', background: '#ffffff', units: 'imperial', language: 'en'
	});
});

Deno.test('a radio without a checked option is not collected', () =>
{
	using page = createPage();
	page.form.parsePreferences({units: {types: 'radio', options: {Celsius: 'metric'}}}, {});
	show(page);

	assertEquals(page.form.collectValues(), {});
});
