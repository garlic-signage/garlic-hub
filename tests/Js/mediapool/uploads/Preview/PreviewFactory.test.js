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

import {assertEquals, assertInstanceOf, assertThrows} from "@std/assert";
import {PreviewFactory}                               from "@js/mediapool/uploads/Preview/PreviewFactory.js";
import {ImagePreview}                                 from "@js/mediapool/uploads/Preview/ImagePreview.js";
import {VideoPreview}                                 from "@js/mediapool/uploads/Preview/VideoPreview.js";
import {AudioPreview}                                 from "@js/mediapool/uploads/Preview/AudioPreview.js";
import {PdfPreview}                                   from "@js/mediapool/uploads/Preview/PdfPreview.js";
import {WidgetPreview}                                from "@js/mediapool/uploads/Preview/WidgetPreview.js";
import {MiscellaneousPreview}                         from "@js/mediapool/uploads/Preview/MiscellaneousPreview.js";

function create(name, type = '')
{
	return new PreviewFactory().create({name, type, size: 1});
}

Deno.test('the kind of the file chooses the preview', () =>
{
	const cases = [
		['lobby.jpg', 'image/jpeg', ImagePreview], ['lobby.tiff', 'image/tiff', ImagePreview],
		['clip.mp4', 'video/mp4', VideoPreview], ['song.mp3', 'audio/mpeg', AudioPreview],
		['menu.pdf', 'application/pdf', PdfPreview],
		['clock', 'application/wgt', WidgetPreview], ['clock', 'application/widget', WidgetPreview],
	];

	for (const [name, type, preview] of cases)
		assertInstanceOf(create(name, type), preview, type);
});

Deno.test('widgets and data files are known by their extension, whatever the browser says', () =>
{
	assertInstanceOf(create('clock.wgt', 'application/octet-stream'), WidgetPreview);

	for (const extension of ['csv', 'json', 'xml', 'dmg', 'rss', 'atom', 'pkg', 'apk', 'zip', 'smil'])
		assertInstanceOf(create(`data.${extension}`), MiscellaneousPreview, extension);
});

Deno.test('the preview gets the file', () =>
{
	const file = {name: 'lobby.jpg', type: 'image/jpeg', size: 1};

	assertEquals(new PreviewFactory().create(file).getFile() === file, true);
});

Deno.test('other files are not supported', () =>
{
	assertThrows(() => create('notes.txt', 'text/plain'), Error, 'File type: text/plain is not supported');
	assertThrows(() => create('setup.exe', 'application/x-msdownload'), Error, 'is not supported');
});
