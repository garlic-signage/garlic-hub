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
import {createDom}                  from "@support/dom.js";
import {replaceGlobals}             from "@support/globals.js";
import {MultizoneFakeFabricWrapper} from "@support/MultizoneFakeFabricWrapper.js";
import {ToolbarController}          from "@js/playlists/compose/multizone/Controller/ToolbarController.js";

// the events of Deno: createDom() puts the ones of linkedom in their place, the fake buttons are EventTargets of Deno
const DenoEvent = globalThis.Event;

const FLASH_TEMPLATE = `<template id="flashMessageTemplate">
	<div class="message-bar"><span></span><button class="close-button"></button></div></template>`;

class FakeZoneService
{
	calls   = [];
	objects = 2;

	insert(properties)          { this.calls.push(['insert', properties]); }
	countObjects()              { return this.objects; }
	moveActive(direction, step) { this.calls.push(['moveActive', direction, step]); }
	duplicateActive()           { this.calls.push(['duplicateActive']); }
	removeActive()              { this.calls.push(['removeActive']); }
}

function createController(saveResult = {success: true}, {confirmed = true} = {})
{
	const view      = {addZone: new EventTarget(), save: new EventTarget(), close: new EventTarget(), canvasWrap: new EventTarget()};
	const zones     = new FakeZoneService();
	const canvas    = new MultizoneFakeFabricWrapper();
	const activated = [];
	const asked     = [];
	const location  = {href: '/playlists/compose/12'};
	const globals   = replaceGlobals({
		lang: {confirm_close: 'Leave without saving?'},
		confirm: (question) => { asked.push(question); return confirmed; },
		window: {location}
	});
	const saveService = {saves: 0, save: async () => { saveService.saves++; return saveResult; }};
	new ToolbarController(view, zones, saveService, {activate: (zone) => activated.push(zone)}, canvas);

	return {view, zones, canvas, activated, asked, location, saveService, [Symbol.dispose]: () => globals[Symbol.dispose]()};
}

function press(page, key, modifiers = {})
{
	page.view.canvasWrap.dispatchEvent(Object.assign(new DenoEvent('keydown'), {key, shiftKey: false, ctrlKey: false, ...modifiers}));
}

function click(button)
{
	button.dispatchEvent(new DenoEvent('click'));
}

Deno.test('a new zone is named by the number of the objects', () =>
{
	using page = createController();

	click(page.view.addZone);

	assertEquals(page.zones.calls, [['insert', {zone_name: 'Zone 3'}]]);
});

Deno.test('the arrows move the active zone by one pixel, with shift by 50', () =>
{
	using page = createController();

	press(page, 'ArrowLeft');
	press(page, 'ArrowDown', {shiftKey: true});

	assertEquals(page.zones.calls, [['moveActive', 'ArrowLeft', 1], ['moveActive', 'ArrowDown', 50]]);
});

Deno.test('ctrl and D duplicate, Delete removes the active zone', () =>
{
	using page = createController();

	press(page, 'd', {ctrlKey: true});
	press(page, 'D', {ctrlKey: true});
	press(page, 'Delete');

	assertEquals(page.zones.calls, [['duplicateActive'], ['duplicateActive'], ['removeActive']]);
});

Deno.test('after a key the canvas is drawn, changed and shows the properties of the active zone', () =>
{
	using page = createController();
	const zone = {id: 'id-1'};
	page.canvas.setActiveObject(zone);

	press(page, 'ArrowUp');

	assertEquals([page.canvas.renders, page.canvas.hasChanged], [1, true]);
	assertEquals(page.activated.length, 1);
	assertEquals(page.activated[0] === zone, true);
});

Deno.test('a saved layout is no change any more', async () =>
{
	using page = createController();
	page.canvas.changed();

	click(page.view.save);
	await new Promise(resolve => setTimeout(resolve, 0));

	assertEquals(page.saveService.saves, 1);
	assertEquals(page.canvas.hasChanged, false);
});

Deno.test('a failed save shows the error and keeps the change', async () =>
{
	using dom  = createDom(FLASH_TEMPLATE);
	using page = createController({success: false, error_message: 'Not allowed'});
	page.canvas.changed();

	click(page.view.save);
	await new Promise(resolve => setTimeout(resolve, 0));

	assertEquals(dom.document.querySelector('.message-bar--error span').textContent, 'Not allowed');
	assertEquals(page.canvas.hasChanged, true);
});

Deno.test('without a change the editor closes at once', () =>
{
	using page = createController();

	click(page.view.close);

	assertEquals(page.asked, []);
	assertEquals(page.location.href, '/playlists');
});

Deno.test('with a change the editor asks before it closes', () =>
{
	{
		using page = createController({success: true}, {confirmed: true});
		page.canvas.changed();

		click(page.view.close);

		assertEquals(page.asked, ['Leave without saving?']);
		assertEquals(page.location.href, '/playlists');
	}
	{
		using page = createController({success: true}, {confirmed: false});
		page.canvas.changed();

		click(page.view.close);

		assertEquals(page.location.href, '/playlists/compose/12');
	}
});
