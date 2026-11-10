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

import {assertEquals}  from "@std/assert";
import {PresetService} from "@js/playlists/compose/multizone/Services/PresetService.js";

class FakeZoneService
{
	created = [];
	removed = 0;
	#zones  = [];

	constructor(zones = [])
	{
		this.#zones = zones;
	}

	zones()     { return this.#zones; }
	removeAll() { this.removed++; this.#zones = []; }

	create(properties)
	{
		this.created.push(properties);
	}
}

function createService(width, height, zones = [])
{
	const zoneService = new FakeZoneService(zones);
	return {zoneService, service: new PresetService({width, height}, zoneService)};
}

Deno.test('the canvas has zones or not', () =>
{
	assertEquals(createService(1920, 1080).service.hasZones(), false);
	assertEquals(createService(1920, 1080, [{}]).service.hasZones(), true);
});

Deno.test('a preset replaces the zones by its zones in pixel', () =>
{
	const {zoneService, service} = createService(1920, 1080, [{}]);

	service.apply({zones: [
		{left: 0, top: 0, width: 75, height: 100, bgcolor: '#111111'},
		{left: 75, top: 0, width: 25, height: 100, bgcolor: '#222222'},
	]});

	assertEquals(zoneService.removed, 1);
	assertEquals(zoneService.created, [
		{zone_name: 'Zone 1', zone_left: 0, zone_top: 0, zone_width: 1440, zone_height: 1080, zone_bgcolor: '#111111'},
		{zone_name: 'Zone 2', zone_left: 1440, zone_top: 0, zone_width: 480, zone_height: 1080, zone_bgcolor: '#222222'},
	]);
});

Deno.test('neighbouring zones of a third touch without a gap and fill the screen', () =>
{
	const {zoneService, service} = createService('1366', '768');
	const third = 100 / 3;

	service.apply({zones: [0, 1, 2].map(i => ({left: i * third, top: 0, width: third, height: 100, bgcolor: '#000000'}))});

	const zones = zoneService.created;
	assertEquals(zones.map(zone => [zone.zone_left, zone.zone_width]), [[0, 455], [455, 456], [911, 455]]);
	assertEquals(zones[1].zone_left, zones[0].zone_left + zones[0].zone_width);
	assertEquals(zones[2].zone_left + zones[2].zone_width, 1366);
});
