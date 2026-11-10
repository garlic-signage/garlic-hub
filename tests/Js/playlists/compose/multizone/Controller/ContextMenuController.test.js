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
import {ContextMenuController}      from "@js/playlists/compose/multizone/Controller/ContextMenuController.js";

class FakeMenuView
{
	calls    = [];
	handlers = {};

	show(x, y)
	{
		this.calls.push(['show', x, y]);
	}

	remove()
	{
		this.calls.push(['remove']);
	}

	onClick(entryId, handler)
	{
		this.handlers[entryId] = handler;
	}
}

class FakeZoneService
{
	calls = [];

	sendToBack()      { this.calls.push('sendToBack'); }
	sendBackwards()   { this.calls.push('sendBackwards'); }
	bringForward()    { this.calls.push('bringForward'); }
	bringToFront()    { this.calls.push('bringToFront'); }
	duplicateActive() { this.calls.push('duplicateActive'); }
	removeActive()    { this.calls.push('removeActive'); }
}

function createController()
{
	const bus    = busOfTest();
	const view   = new FakeMenuView();
	const zones  = new FakeZoneService();
	const canvas = new MultizoneFakeFabricWrapper();
	new ContextMenuController(view, zones, canvas);

	return {bus, view, zones, canvas, [Symbol.dispose]: () => bus[Symbol.dispose]()};
}

const ZONE = {getType: () => 'LabeledZone'};

function rightClick(page, target)
{
	page.bus.send('mouseRightUp', {target, e: {pageX: 120, pageY: 80}});
}

Deno.test('the right mouse button on a zone selects it and opens the menu there', () =>
{
	using page = createController();

	rightClick(page, ZONE);

	assertEquals(page.canvas.active === ZONE, true);
	assertEquals(page.view.calls, [['remove'], ['show', 120, 80]]);
	assertEquals(Object.keys(page.view.handlers),
		['move_background', 'move_back', 'move_front', 'move_foreground', 'duplicate_zone', 'delete_zone']);
});

Deno.test('every entry calls its action and closes the menu', () =>
{
	const actions = {move_background: 'sendToBack', move_back: 'sendBackwards', move_front: 'bringForward',
		move_foreground: 'bringToFront', duplicate_zone: 'duplicateActive', delete_zone: 'removeActive'};

	for (const [entry, action] of Object.entries(actions))
	{
		using page = createController();
		rightClick(page, ZONE);
		page.view.calls = [];

		page.view.handlers[entry]();

		assertEquals(page.zones.calls, [action], entry);
		assertEquals(page.view.calls, [['remove']], entry);
	}
});

Deno.test('beside the zones the right mouse button opens no menu', () =>
{
	using page = createController();

	rightClick(page, null);

	assertEquals(page.view.calls, [['remove']]);
	assertEquals(page.canvas.active, null);
});

Deno.test('another object is selected, but has no menu', () =>
{
	using page   = createController();
	const object = {getType: () => 'Grid'};

	rightClick(page, object);

	assertEquals(page.canvas.active === object, true);
	assertEquals(page.view.calls, [['remove']]);
});

Deno.test('the left mouse button closes the menu', () =>
{
	using page = createController();

	page.bus.send('mouseLeftUp', {target: null});

	assertEquals(page.view.calls, [['remove']]);
});
