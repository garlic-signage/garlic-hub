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
import "@support/MultizoneFakeFabric.js";
import {busOfTest}                  from "@support/MultizoneEvents.js";
import {MultizoneFakeFabricWrapper} from "@support/MultizoneFakeFabricWrapper.js";
import {LabeledZoneFactory}         from "@js/playlists/compose/multizone/Utils/LabeledZoneFactory.js";
import {ZoneService}                from "@js/playlists/compose/multizone/Services/ZoneService.js";

function createService()
{
	const canvas = new MultizoneFakeFabricWrapper();
	return {canvas, service: new ZoneService(canvas, new LabeledZoneFactory())};
}

Deno.test('a new zone is put on the canvas and told', () =>
{
	using bus = busOfTest('zoneAdded');
	const {canvas, service} = createService();

	const zone = service.create({zone_name: 'Lobby'});

	assertEquals(canvas.objects.length, 1);
	assertEquals(canvas.objects[0] === zone, true);
	assertEquals(bus.events.length, 1);
	assertEquals(bus.events[0][1].zone === zone, true);
	assertEquals(canvas.renders, 0);
});

Deno.test('an inserted zone is drawn at once', () =>
{
	using _bus = busOfTest();
	const {canvas, service} = createService();

	service.insert({zone_name: 'Lobby'});

	assertEquals(canvas.renders, 1);
});

Deno.test('the zones are the labeled zones in the order of the layers', () =>
{
	using _bus = busOfTest();
	const {canvas, service} = createService();
	const first = service.create({zone_name: 'A'});
	canvas.objects.push({getType: () => 'Grid'});
	const second = service.create({zone_name: 'B'});

	assertEquals(service.zones().map(zone => zone.label.text), ['A', 'B']);
	assertEquals(service.zones()[0] === first && service.zones()[1] === second, true);
	assertEquals(service.countObjects(), 3);
});

Deno.test('the duplicate of the active zone is 20 pixel beside it and becomes active', () =>
{
	using bus = busOfTest('zoneAdded');
	const {canvas, service} = createService();
	const zone = service.create({zone_name: 'Lobby', zone_left: 100, zone_top: 50, zone_width: 300, zone_height: 200, zone_playlist_id: 5});
	canvas.setActiveObject(zone);

	service.duplicateActive();

	const copy = canvas.active;
	assertEquals(copy === zone, false);
	assertEquals([copy.left, copy.top, copy.width, copy.height], [120, 70, 300, 200]);
	assertEquals([copy.label.text, copy.zone_playlist_id], ['Lobby', 5]);
	assertEquals(bus.events.length, 2);
	assertEquals(canvas.renders, 1);
});

Deno.test('without an active zone nothing is duplicated, removed or moved', () =>
{
	using bus = busOfTest('zoneAdded', 'zoneRemoved');
	const {canvas, service} = createService();

	service.duplicateActive();
	service.removeActive();
	service.moveActive('ArrowLeft', 1);

	assertEquals(canvas.objects, []);
	assertEquals(bus.events, []);
});

Deno.test('the active zone is removed and told', () =>
{
	using bus = busOfTest('zoneRemoved');
	const {canvas, service} = createService();
	const zone = service.create({zone_name: 'Lobby'});
	service.create({zone_name: 'Hall'});
	canvas.setActiveObject(zone);

	service.removeActive();

	assertEquals(service.zones().map(other => other.label.text), ['Hall']);
	assertEquals(bus.events, [['zoneRemoved', {id: zone.getId()}]]);
});

Deno.test('all zones are removed, other objects stay', () =>
{
	using bus = busOfTest('zoneRemoved');
	const {canvas, service} = createService();
	const first  = service.create({zone_name: 'A'});
	const second = service.create({zone_name: 'B'});
	const grid   = {getType: () => 'Grid'};
	canvas.objects.push(grid);
	canvas.setActiveObject(first);

	service.removeAll();

	assertEquals(canvas.objects.length, 1);
	assertEquals(canvas.objects[0] === grid, true);
	assertEquals(canvas.active, null);
	assertEquals(bus.events.map(event => event[1].id), [first.getId(), second.getId()]);
});

Deno.test('a zone is selected by its id', () =>
{
	using _bus = busOfTest();
	const {canvas, service} = createService();
	service.create({zone_name: 'A'});
	const second = service.create({zone_name: 'B'});

	assertEquals(service.selectById(second.getId()) === second, true);
	assertEquals(canvas.active === second, true);

	assertEquals(service.selectById('id-unknown'), null);
	assertEquals(canvas.active === second, true);
});

Deno.test('the arrows move the active zone by the step', () =>
{
	using _bus = busOfTest();
	const {canvas, service} = createService();
	const zone = service.create({zone_name: 'A', zone_left: 100, zone_top: 100});
	canvas.setActiveObject(zone);

	service.moveActive('ArrowLeft', 1);
	assertEquals([zone.left, zone.top], [99, 100]);
	service.moveActive('ArrowRight', 50);
	assertEquals([zone.left, zone.top], [149, 100]);
	service.moveActive('ArrowUp', 1);
	assertEquals([zone.left, zone.top], [149, 99]);
	service.moveActive('ArrowDown', 50);
	assertEquals([zone.left, zone.top], [149, 149]);
	service.moveActive('Enter', 50);
	assertEquals([zone.left, zone.top], [149, 149]);
});

Deno.test('the layers of the active zone are changed by the canvas', () =>
{
	using _bus = busOfTest();
	const {canvas, service} = createService();
	const zone = service.create({zone_name: 'A'});
	canvas.setActiveObject(zone);

	service.sendToBack();
	service.sendBackwards();
	service.bringForward();
	service.bringToFront();

	assertEquals(canvas.calls.map(call => call[0]), ['sendToBack', 'sendBackwards', 'bringForward', 'bringToFront']);
	assertEquals(canvas.calls.every(call => call[1] === zone), true);
});
