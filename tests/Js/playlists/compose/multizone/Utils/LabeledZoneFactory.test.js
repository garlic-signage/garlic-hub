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

import {assertEquals, assertInstanceOf} from "@std/assert";
import "@support/MultizoneFakeFabric.js";
import {LabeledZone}                    from "@js/playlists/compose/multizone/Utils/LabeledZone.js";
import {LabeledZoneFactory}             from "@js/playlists/compose/multizone/Utils/LabeledZoneFactory.js";

Deno.test('the factory makes labeled zones of the properties', () =>
{
	const zone = new LabeledZoneFactory().create({zone_name: 'Lobby', zone_width: 300, zone_height: 200, zone_bgcolor: '#ff0000'});

	assertInstanceOf(zone, LabeledZone);
	assertEquals([zone.label.text, zone.width, zone.height, zone.rect.fill], ['Lobby', 300, 200, '#ff0000']);
});

Deno.test('the defaults of the factory are taken unless the properties have their own', () =>
{
	const factory = new LabeledZoneFactory({fontSize: 30});

	assertEquals(factory.defaultOptions, {left: 0, top: 0, width: 200, height: 100, fill: '#222222', fontSize: 30});
	assertEquals(new LabeledZoneFactory().defaultOptions.fontSize, 20);
});
