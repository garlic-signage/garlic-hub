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

import {assertEquals}    from "@std/assert";
import "@support/MultizoneFakeFabric.js";
import {LabeledZone}     from "@js/playlists/compose/multizone/Utils/LabeledZone.js";

function zone(options = {})
{
	return new LabeledZone({zone_name: 'Lobby', zone_left: 10, zone_top: 20, zone_width: 300, zone_height: 200, ...options});
}

Deno.test('a zone is a rectangle with its name and its playlist in the middle', () =>
{
	const labeled = zone({zone_bgcolor: '#ffffff', zone_playlist_id: 5, zone_playlist_name: 'Morning'});

	// the stroke of 1 pixel is part of the size
	assertEquals([labeled.rect.left, labeled.rect.top, labeled.rect.width, labeled.rect.height], [10, 20, 299, 199]);
	assertEquals([labeled.left, labeled.top, labeled.width, labeled.height], [10, 20, 300, 200]);
	assertEquals(labeled.rect.fill, '#ffffff');
	assertEquals([labeled.label.text, labeled.label.left, labeled.label.top], ['Lobby', 159.5, 119.5]);
	assertEquals([labeled.playlist_name.text, labeled.playlist_name.top], ['Morning', 159.5]);
	assertEquals([labeled.zone_playlist_id, labeled.zone_playlist_name], [5, 'Morning']);
	assertEquals(labeled.name, 'Lobby');
	assertEquals(labeled.getType(), 'LabeledZone');
});

Deno.test('a zone can be moved and resized, not rotated, skewed or flipped', () =>
{
	const labeled = zone();

	assertEquals([labeled.lockRotation, labeled.lockSkewingX, labeled.lockSkewingY, labeled.lockScalingFlip], [true, true, true, true]);
	assertEquals(labeled.controlsVisibility, {mtr: false});
});

Deno.test('without values a zone has a default size, no color and no playlist', () =>
{
	const labeled = new LabeledZone({zone_name: 'Zone 1'});

	assertEquals([labeled.rect.left, labeled.rect.top, labeled.rect.width, labeled.rect.height], [0, 0, 200, 150]);
	assertEquals(labeled.rect.fill, 'transparent');
	assertEquals([labeled.playlist_name.text, labeled.zone_playlist_name, labeled.zone_playlist_id], ['--', '', 0]);
});

Deno.test('the values of the database are texts', () =>
{
	const labeled = zone({zone_left: '10', zone_top: '20', zone_width: '300', zone_height: '200'});

	assertEquals([labeled.rect.left, labeled.rect.top, labeled.rect.width, labeled.rect.height], [10, 20, 299, 199]);
});

Deno.test('every zone gets its own id', () =>
{
	const first  = zone();
	const second = zone();

	assertEquals(first.getId().startsWith('id-'), true);
	assertEquals(first.getId() === second.getId(), false);
	assertEquals(first.getId(), first.id);
});

Deno.test('the text is black on light colors and on transparent, white on dark ones', () =>
{
	const cases = {'#ffffff': 'black', '#ffff00': 'black', '#808080': 'black', '#000000': 'white', '#0000ff': 'white',
		'222222': 'white', 'transparent': 'black'};

	for (const [color, text] of Object.entries(cases))
		assertEquals(LabeledZone.getTextColorFunction(color), text, color);

	const dark = zone({zone_bgcolor: '#000000'});
	assertEquals([dark.label.fill, dark.playlist_name.fill], ['white', 'white']);
});

Deno.test('a new color changes the text color, too', () =>
{
	const labeled = zone({zone_bgcolor: '#ffffff'});

	labeled.changeBgColor('#000080');

	assertEquals([labeled.rect.fill, labeled.label.fill, labeled.playlist_name.fill], ['#000080', 'white', 'white']);
	assertEquals(labeled.dirty, true);
});

Deno.test('a new width or height is centred and keeps the size of the texts', () =>
{
	const labeled = zone();
	labeled.label.fontSize = 10;

	labeled.changeWidth(400);
	labeled.changeHeight(100);

	assertEquals([labeled.width, labeled.rect.width, labeled.rect.left], [400, 400, -200]);
	assertEquals([labeled.height, labeled.rect.height, labeled.rect.top], [100, 100, -50]);
	assertEquals([labeled.label.fontSize, labeled.playlist_name.fontSize], [32, 24]);
	assertEquals(labeled.dirty, true);
});

Deno.test('a zone scaled by the mouse gets the real size and the scale 1', () =>
{
	const labeled = zone();
	labeled.scaleX = 2;
	labeled.scaleY = 0.5;

	labeled.onScaling();

	assertEquals([labeled.width, labeled.height, labeled.scaleX, labeled.scaleY], [598, 99.5, 1, 1]);
	assertEquals([labeled.rect.width, labeled.rect.height, labeled.rect.left, labeled.rect.top], [598, 99.5, -299, -49.75]);
	assertEquals([labeled.rect.scaleX, labeled.rect.scaleY], [1, 1]);
	assertEquals(labeled.dirty, true);
});

Deno.test('a duplicate gets the place, size, color and playlist of the zone', () =>
{
	const labeled = zone({zone_bgcolor: '#ff0000', zone_playlist_id: 5, zone_playlist_name: 'Morning'});

	assertEquals(labeled.getPropertiesForDuplicate(), {
		zone_name: 'Lobby', zone_left: 10, zone_top: 20, zone_width: 300, zone_height: 200,
		zone_bgcolor: '#ff0000', zone_playlist_id: 5, zone_playlist_name: 'Morning'
	});
});

Deno.test('the export has whole pixel and the layer of the zone, no name of the playlist', () =>
{
	const labeled = zone({zone_bgcolor: '#ff0000', zone_playlist_id: 5, zone_playlist_name: 'Morning'});
	labeled.set({left: 10.4, top: 19.6, width: 300.5, height: 199.4});
	labeled.canvas = {getObjects: () => [{}, labeled]};

	assertEquals(labeled.getPropertiesForExport(), {
		'zone_name': 'Lobby', 'zone_left': 10, 'zone_top': 20, 'zone_width': 301, 'zone_height': 199,
		'zone_z-index': 1, 'zone_bgcolor': '#ff0000', 'zone_playlist_id': 5
	});
});

Deno.test('the renamed label is the name of the export', () =>
{
	const labeled = zone();
	labeled.canvas = {getObjects: () => [labeled]};

	labeled.item(1).text = 'Hall';

	assertEquals(labeled.getPropertiesForExport().zone_name, 'Hall');
	assertEquals(labeled.getPropertiesForDuplicate().zone_name, 'Hall');
});
