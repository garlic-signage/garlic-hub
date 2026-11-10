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
import {LabeledZone}                from "@js/playlists/compose/multizone/Utils/LabeledZone.js";
import {ZonePropertiesService}      from "@js/playlists/compose/multizone/Services/ZonePropertiesService.js";

class FakePlaylistsService
{
	asked = [];
	names = {5: 'Morning'};

	async findNameById(playlistId)
	{
		this.asked.push(playlistId);
		return this.names[playlistId] ?? '';
	}
}

/**
 * The service with a selected zone, which is the active object of the canvas, too.
 */
function createService(options = {})
{
	const canvas    = new MultizoneFakeFabricWrapper();
	const playlists = new FakePlaylistsService();
	const service   = new ZonePropertiesService(canvas, playlists);
	const zone      = new LabeledZone({zone_name: 'Lobby', zone_left: 10, zone_top: 20, zone_width: 300, zone_height: 200,
		zone_bgcolor: '#ffffff', ...options});
	canvas.setActiveObject(zone);
	service.select(zone);

	return {canvas, playlists, service, zone};
}

Deno.test('the selected zone is the one which is changed', () =>
{
	const {service, zone} = createService();

	assertEquals(service.zone === zone, true);
	service.clear();
	assertEquals(service.zone, null);
});

Deno.test('a new name is set on the label and told', () =>
{
	using bus = busOfTest('zoneRenamed');
	const {canvas, service, zone} = createService();

	service.rename('Hall');

	assertEquals(zone.label.text, 'Hall');
	assertEquals(zone.dirty, true);
	assertEquals(bus.events, [['zoneRenamed', {id: zone.getId(), name: 'Hall'}]]);
	assertEquals([canvas.changes, canvas.renders], [1, 1]);
});

Deno.test('the same name changes nothing', () =>
{
	using bus = busOfTest('zoneRenamed');
	const {canvas, service} = createService();

	service.rename('Lobby');

	assertEquals(bus.events, []);
	assertEquals(canvas.changes, 0);
});

Deno.test('the place is taken as number, the same place changes nothing', () =>
{
	const {canvas, service, zone} = createService();

	service.setLeft('15');
	service.setTop('25');
	assertEquals([zone.left, zone.top], [15, 25]);
	assertEquals([canvas.changes, canvas.renders], [2, 2]);

	service.setLeft('15');
	service.setTop(25);
	assertEquals(canvas.changes, 2);
});

Deno.test('a new width and height resize the zone', () =>
{
	const {canvas, service, zone} = createService();

	service.setWidth('400');
	service.setHeight('100');

	assertEquals([zone.width, zone.height], [400, 100]);
	assertEquals([canvas.changes, canvas.renders], [2, 2]);
});

Deno.test('a new color and the transparency change the background', () =>
{
	const {canvas, service, zone} = createService();

	service.setColor('#000080');
	assertEquals([zone.rect.fill, zone.label.fill], ['#000080', 'white']);

	service.setTransparent(true);
	assertEquals([zone.rect.fill, zone.label.fill], ['transparent', 'black']);
	assertEquals(service.isTransparent(zone.rect.fill), true);

	service.setTransparent(false);
	assertEquals(zone.rect.fill, '#000000');
	assertEquals(service.isTransparent(zone.rect.fill), false);
	assertEquals(canvas.changes, 3);
});

Deno.test('a chosen playlist is shown in the zone', () =>
{
	const {canvas, service, zone} = createService();

	service.setPlaylist(5, 'Morning');

	assertEquals([zone.zone_playlist_id, zone.item(2).text, zone.dirty], [5, 'Morning', true]);
	assertEquals([canvas.changes, canvas.renders], [1, 1]);

	service.setPlaylist(5, 'Morning');
	assertEquals(canvas.changes, 1);
});

Deno.test('a removed playlist leaves the zone without playlist', () =>
{
	const {canvas, service, zone} = createService({zone_playlist_id: 5, zone_playlist_name: 'Morning'});

	service.removePlaylist();

	assertEquals(zone.zone_playlist_id, 0);
	assertEquals(canvas.changes, 1);
});

Deno.test('without a selected zone nothing is changed', () =>
{
	using bus = busOfTest('zoneRenamed');
	const {canvas, service, zone} = createService();
	service.clear();

	service.rename('Hall');
	service.setLeft(1);
	service.setTop(1);
	service.setWidth(1);
	service.setHeight(1);
	service.setColor('#000000');
	service.setTransparent(true);
	service.setPlaylist(5, 'Morning');
	service.removePlaylist();

	assertEquals([zone.label.text, zone.left, zone.width, zone.rect.fill, zone.zone_playlist_id], ['Lobby', 10, 300, '#ffffff', 0]);
	assertEquals([canvas.changes, canvas.renders], [0, 0]);
	assertEquals(bus.events, []);
});

Deno.test('the playlist of the zone is found with its name', async () =>
{
	const {playlists, service} = createService({zone_playlist_id: 5});

	assertEquals(await service.findPlaylist(), {id: 5, name: 'Morning'});
	assertEquals(playlists.asked, [5]);
});

Deno.test('a zone without playlist asks nobody', async () =>
{
	const {playlists, service} = createService();

	assertEquals(await service.findPlaylist(), {id: 0, name: ''});
	assertEquals(playlists.asked, []);
});

Deno.test('a color of rgba without the closing bracket is repaired', () =>
{
	const {service} = createService();

	assertEquals(service.fixRGBA('rgba(0,0,0,0.5'), 'rgba(0,0,0,0.5)');
	assertEquals(service.fixRGBA('rgba(0,0,0,0.5)'), 'rgba(0,0,0,0.5)');
	assertEquals(service.fixRGBA('#ff0000'), '#ff0000');
});
