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

import {assertEquals}               from "@std/assert";
import {busOfTest}                  from "@support/MultizoneEvents.js";
import {MultizoneFakeFabricWrapper} from "@support/MultizoneFakeFabricWrapper.js";
import {ZonePropertiesController}   from "@js/playlists/compose/multizone/Controller/ZonePropertiesController.js";

/**
 * A field of the view: an EventTarget with a value.
 */
function field(value = '')
{
	return Object.assign(new EventTarget(), {value, checked: false});
}

class FakePropertiesView
{
	name        = field();
	left        = field();
	top         = field();
	width       = field();
	height      = field();
	bgcolor     = field();
	transparent = field();
	calls       = [];

	show(...values)        { this.calls.push(['show', ...values]); }
	clear()                { this.calls.push(['clear']); }
	enable()               { this.calls.push(['enable']); }
	disable()              { this.calls.push(['disable']); }
	showTransparent()      { this.calls.push(['showTransparent']); }
	showColor(color)       { this.calls.push(['showColor', color]); }
	enableColor(isEnabled) { this.calls.push(['enableColor', isEnabled]); }
}

/**
 * Records the changes, the playlist of a zone is set by the test.
 */
class FakePropertiesService
{
	zone     = null;
	calls    = [];
	playlist = {id: 0, name: ''};

	select(zone)          { this.zone = zone; this.calls.push(['select']); }
	clear()               { this.zone = null; this.calls.push(['clear']); }
	rename(name)          { this.calls.push(['rename', name]); }
	setLeft(value)        { this.calls.push(['setLeft', value]); }
	setTop(value)         { this.calls.push(['setTop', value]); }
	setWidth(value)       { this.calls.push(['setWidth', value]); }
	setHeight(value)      { this.calls.push(['setHeight', value]); }
	setColor(color)       { this.calls.push(['setColor', color]); }
	setTransparent(value) { this.calls.push(['setTransparent', value]); }
	setPlaylist(id, name) { this.calls.push(['setPlaylist', id, name]); }
	removePlaylist()      { this.calls.push(['removePlaylist']); }
	async findPlaylist()  { return this.playlist; }
	isTransparent(fill)   { return fill === 'transparent'; }
	fixRGBA(color)        { return color === 'rgba(0,0,0,0.5' ? 'rgba(0,0,0,0.5)' : color; }
}

class FakeAutocomplete
{
	hidden = field();
	edit   = field();
	calls  = [];

	getHiddenIdElement()     { return this.hidden; }
	getEditFieldElement()    { return this.edit; }
	setInputFields(id, name) { this.calls.push(['setInputFields', id, name]); }
	clearAll()               { this.calls.push(['clearAll']); }
}

function createController()
{
	const bus          = busOfTest();
	const view         = new FakePropertiesView();
	const service      = new FakePropertiesService();
	const autocomplete = new FakeAutocomplete();
	const canvas       = new MultizoneFakeFabricWrapper();
	const controller   = new ZonePropertiesController(view, service, autocomplete, canvas);

	return {bus, view, service, autocomplete, canvas, controller, [Symbol.dispose]: () => bus[Symbol.dispose]()};
}

function zone(fill = '#ff0000')
{
	return {left: 10.4, top: 19.6, width: 300.5, height: 200, scaleX: 1, scaleY: 1, label: {text: 'Lobby'}, rect: {fill}, scaled: 0,
		onScaling() { this.scaled++; }};
}

function wait()
{
	return new Promise(resolve => setTimeout(resolve, 0));
}

Deno.test('a zone shows its rounded values and its color', async () =>
{
	using page = createController();

	page.controller.activate(zone());
	await wait();

	assertEquals(page.view.calls, [['show', 'Lobby', 10, 20, 301, 200], ['showColor', '#ff0000'], ['enable']]);
	assertEquals(page.service.calls, [['select']]);
});

Deno.test('a transparent zone shows no color', () =>
{
	using page = createController();

	page.controller.activate(zone('transparent'));

	assertEquals(page.view.calls[1], ['showTransparent']);
});

Deno.test('an rgba color without the closing bracket is repaired on the zone', () =>
{
	using page   = createController();
	const broken = zone('rgba(0,0,0,0.5');

	page.controller.activate(broken);

	assertEquals(broken.rect.fill, 'rgba(0,0,0,0.5)');
	assertEquals(page.view.calls[1], ['showColor', 'rgba(0,0,0,0.5)']);
});

Deno.test('the playlist of the zone is shown in the search, none empties it', async () =>
{
	{
		using page = createController();
		page.service.playlist = {id: 5, name: 'Morning'};

		page.controller.activate(zone());
		await wait();

		assertEquals(page.autocomplete.calls, [['setInputFields', 5, 'Morning']]);
	}
	{
		using page = createController();

		page.controller.activate(zone());
		await wait();

		assertEquals(page.autocomplete.calls, [['setInputFields', 0, '']]);
	}
});

Deno.test('without a zone the properties are cleared and disabled', () =>
{
	for (const target of [null, undefined])
	{
		using page = createController();

		page.controller.activate(target);

		assertEquals(page.view.calls, [['clear'], ['disable']]);
		assertEquals(page.autocomplete.calls, [['clearAll']]);
		assertEquals(page.service.calls, [['clear']]);
	}
});

Deno.test('a click on the canvas shows the zone under the mouse', () =>
{
	using page = createController();

	page.bus.send('mouseLeftUp', {target: zone()});
	page.bus.send('mouseLeftUp', {target: null});

	assertEquals(page.view.calls.map(call => call[0]), ['show', 'showColor', 'enable', 'clear', 'disable']);
});

Deno.test('a zone resized by the mouse gets its real size before it is shown', () =>
{
	using page   = createController();
	const scaled = {...zone(), scaleX: 2};
	const moved  = zone();

	page.canvas.handlers['object:modified']({target: scaled});
	page.canvas.handlers['object:modified']({target: moved});

	assertEquals([scaled.scaled, moved.scaled], [1, 0]);
	assertEquals(page.view.calls.filter(call => call[0] === 'show').length, 2);
});

Deno.test('the fields change the zone', () =>
{
	using page    = createController();
	const view    = page.view;
	const changes = [['name', 'input', 'Hall', 'rename'], ['left', 'blur', '5', 'setLeft'], ['top', 'blur', '6', 'setTop'],
		['width', 'blur', '7', 'setWidth'], ['height', 'blur', '8', 'setHeight'], ['bgcolor', 'input', '#00ff00', 'setColor']];

	for (const [fieldName, eventType, value] of changes)
	{
		view[fieldName].value = value;
		view[fieldName].dispatchEvent(new Event(eventType));
	}

	assertEquals(page.service.calls, changes.map(([, , value, action]) => [action, value]));
});

Deno.test('the transparency disables the color, only with a zone', () =>
{
	using page = createController();
	page.view.transparent.checked = true;

	page.view.transparent.dispatchEvent(new Event('input'));
	assertEquals(page.service.calls, []);

	page.controller.activate(zone());
	page.view.calls = [];
	page.view.transparent.dispatchEvent(new Event('input'));
	assertEquals(page.view.calls, [['enableColor', false]]);
	assertEquals(page.service.calls.at(-1), ['setTransparent', true]);

	page.view.transparent.checked = false;
	page.view.transparent.dispatchEvent(new Event('input'));
	assertEquals(page.view.calls.at(-1), ['enableColor', true]);
	assertEquals(page.service.calls.at(-1), ['setTransparent', false]);
});

Deno.test('a playlist chosen in the search is set on the zone', () =>
{
	using page = createController();
	page.autocomplete.hidden.value = '5';
	page.autocomplete.edit.value   = 'Morning';

	page.autocomplete.hidden.dispatchEvent(new Event('selectedFromDataList'));

	assertEquals(page.service.calls, [['setPlaylist', '5', 'Morning']]);
});

Deno.test('an emptied search removes the playlist, a filled one not', () =>
{
	using page = createController();

	page.autocomplete.edit.value = 'Morn';
	page.autocomplete.edit.dispatchEvent(new Event('blur'));
	page.autocomplete.edit.value = '';
	page.autocomplete.edit.dispatchEvent(new Event('blur'));

	assertEquals(page.service.calls, [['removePlaylist']]);
});
