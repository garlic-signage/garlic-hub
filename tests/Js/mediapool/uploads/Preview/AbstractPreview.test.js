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


import {assertEquals, assertThrows} from "@std/assert";
import {AbstractPreview}            from "@js/mediapool/uploads/Preview/AbstractPreview.js";

Deno.test('the file is kept for the previews', () =>
{
	const file = {name: 'lobby.jpg', size: 1, type: 'image/jpeg'};

	assertEquals(new AbstractPreview(file).getFile() === file, true);
});

Deno.test('the metadata show name, size in MB with two decimals and type', () =>
{
	const preview = new AbstractPreview({name: 'lobby.mp4', size: 3.5 * 1024 * 1024, type: 'video/mp4'});

	assertEquals(preview.extractMetadata(), {name: 'lobby.mp4', size: '3.50 MB', type: 'video/mp4'});
});

Deno.test('the size is rounded to two decimals', () =>
{
	const sizes = {0: '0.00 MB', 1024: '0.00 MB', 10486: '0.01 MB', 1572864: '1.50 MB', 1073741824: '1024.00 MB'};

	for (const [size, text] of Object.entries(sizes))
		assertEquals(new AbstractPreview({size: Number(size)}).calculateMegaBytes(), text, size);
});

Deno.test('the subclasses must create the preview', () =>
{
	assertThrows(() => new AbstractPreview({}).createPreview(), Error, 'createPreview must be implemented');
});
