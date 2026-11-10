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
import {ZoneListController}         from "@js/playlists/compose/multizone/Controller/ZoneListController.js";

class FakeListView
{
	calls  = [];
	clicks = {};

	add(id, name, onClick)
	{
		this.calls.push(['add', id, name]);
		this.clicks[id] = onClick;
	}

	highlight(id)    { this.calls.push(['highlight', id]); }
	remove(id)       { this.calls.push(['remove', id]); }
	rename(id, name) { this.calls.push(['rename', id, name]); }
}

function createController(zones = {})
{
	const bus         = busOfTest();
	const view        = new FakeListView();
	const canvas      = new MultizoneFakeFabricWrapper();
	const activated   = [];
	const selected    = [];
	const zoneService = {selectById: (id) => { selected.push(id); return zones[id] ?? null; }};
	new ZoneListController(view, zoneService, {activate: (zone) => activated.push(zone)}, canvas);

	return {bus, view, canvas, activated, selected, [Symbol.dispose]: () => bus[Symbol.dispose]()};
}

Deno.test('a new zone is added to the list with its name', () =>
{
	using page = createController();

	page.bus.send('zoneAdded', {zone: {id: 'id-1', label: {text: 'Lobby'}}});

	assertEquals(page.view.calls, [['add', 'id-1', 'Lobby']]);
});

Deno.test('a removed or renamed zone changes its entry', () =>
{
	using page = createController();

	page.bus.send('zoneRemoved', {id: 'id-1'});
	page.bus.send('zoneRenamed', {id: 'id-2', name: 'Hall'});

	assertEquals(page.view.calls, [['remove', 'id-1'], ['rename', 'id-2', 'Hall']]);
});

Deno.test('a zone selected on the canvas is highlighted in the list', () =>
{
	using page = createController();

	page.canvas.handlers['selection:created']({selected: [{id: 'id-1'}]});
	page.canvas.handlers['selection:updated']({selected: [{id: 'id-2'}]});

	assertEquals(page.view.calls, [['highlight', 'id-1'], ['highlight', 'id-2']]);
});

Deno.test('a click on an entry selects its zone and shows its properties', () =>
{
	const zone = {id: 'id-1'};
	using page = createController({'id-1': zone});
	page.bus.send('zoneAdded', {zone: {id: 'id-1', label: {text: 'Lobby'}}});

	page.view.clicks['id-1']('id-1');

	assertEquals(page.view.calls.at(-1), ['highlight', 'id-1']);
	assertEquals(page.selected, ['id-1']);
	assertEquals(page.activated.length, 1);
	assertEquals(page.activated[0] === zone, true);
	assertEquals(page.canvas.renders, 1);
});

Deno.test('an entry of a zone which is gone shows no properties', () =>
{
	using page = createController();
	page.bus.send('zoneAdded', {zone: {id: 'id-1', label: {text: 'Lobby'}}});

	page.view.clicks['id-1']('id-1');

	assertEquals(page.activated, []);
	assertEquals(page.canvas.renders, 0);
});
