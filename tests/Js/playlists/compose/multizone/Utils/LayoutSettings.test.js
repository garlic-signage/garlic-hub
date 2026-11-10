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

import {assertEquals}   from "@std/assert";
import {LayoutSettings} from "@js/playlists/compose/multizone/Utils/LayoutSettings.js";

Deno.test('a new layout is in pixel with a zoom of 70 percent', () =>
{
	const settings = new LayoutSettings();

	assertEquals([settings.exportUnit, settings.zoom], ['pixel', 70]);
	assertEquals([LayoutSettings.UNIT_PIXEL, LayoutSettings.UNIT_PERCENT], ['pixel', 'percent']);
});
