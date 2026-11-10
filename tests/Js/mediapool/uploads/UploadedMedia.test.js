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
import {UploadedMedia} from "@js/mediapool/uploads/UploadedMedia.js";

/**
 * The list is static: every test starts with an empty one.
 */
function emptyList()
{
	UploadedMedia.take();
}

Deno.test('the ids are taken in the order of the uploads', () =>
{
	emptyList();

	UploadedMedia.add('abc');
	UploadedMedia.add('def');

	assertEquals(UploadedMedia.take(), ['abc', 'def']);
});

Deno.test('the list starts empty again after take()', () =>
{
	emptyList();
	UploadedMedia.add('abc');

	UploadedMedia.take();

	assertEquals(UploadedMedia.take(), []);
});

Deno.test('the taken ids do not change with the next uploads', () =>
{
	emptyList();
	UploadedMedia.add('abc');

	const taken = UploadedMedia.take();
	UploadedMedia.add('def');

	assertEquals(taken, ['abc']);
	assertEquals(UploadedMedia.take(), ['def']);
});

Deno.test('only a filled string is an id', () =>
{
	emptyList();

	for (const noId of ['', undefined, null, 5, {media_id: 'abc'}, ['abc']])
		UploadedMedia.add(noId);

	assertEquals(UploadedMedia.take(), []);
});
