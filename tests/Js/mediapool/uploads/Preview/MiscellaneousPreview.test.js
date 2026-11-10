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


import {assertEquals}         from "@std/assert";
import {createDom}            from "@support/dom.js";
import {MiscellaneousPreview} from "@js/mediapool/uploads/Preview/MiscellaneousPreview.js";

function previewOf(name)
{
	return new MiscellaneousPreview({name: name, size: 1, type: ''}).createPreview();
}

Deno.test('data files show the database icon', () =>
{
	using _dom = createDom();

	for (const name of ['prices.csv', 'feed.json', 'feed.xml'])
	{
		const image = previewOf(name);
		assertEquals(image.tagName, 'IMG', name);
		assertEquals(image.getAttribute('src'), './images/icons/database.svg', name);
	}
});

Deno.test('other files show the file icon', () =>
{
	using _dom = createDom();

	for (const name of ['news.rss', 'news.atom', 'show.smil', 'app.apk', 'archive.zip', 'json'])
		assertEquals(previewOf(name).getAttribute('src'), './images/icons/file.svg', name);
});
