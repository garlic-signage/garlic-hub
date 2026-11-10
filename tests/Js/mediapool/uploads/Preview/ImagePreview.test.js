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
import {createDom}                  from "@support/dom.js";
import {replaceGlobals}             from "@support/globals.js";
import {ImagePreview}               from "@js/mediapool/uploads/Preview/ImagePreview.js";

/**
 * Records the read files; load() answers like the browser when the file was read.
 */
class FakeFileReader
{
	static last = null;
	onload = null;
	read   = [];

	constructor()
	{
		FakeFileReader.last = this;
	}

	readAsDataURL(file)
	{
		this.read.push(file);
	}

	load(result)
	{
		this.onload({target: {result}});
	}
}

function createPage()
{
	const dom     = createDom();
	const globals = replaceGlobals({FileReader: FakeFileReader});
	FakeFileReader.last = null;
	return {[Symbol.dispose]: () => { globals[Symbol.dispose](); dom[Symbol.dispose](); }};
}

Deno.test('the formats of the browsers are shown as image of the read file', () =>
{
	for (const type of ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/svg+xml'])
	{
		using _page = createPage();
		const file  = {name: 'lobby', size: 1, type: type};

		const image = new ImagePreview(file).createPreview();
		assertEquals(image.tagName, 'IMG', type);
		assertEquals(FakeFileReader.last.read[0] === file, true, type);

		FakeFileReader.last.load('data:' + type + ';base64,AAAA');
		assertEquals(image.getAttribute('src'), 'data:' + type + ';base64,AAAA', type);
	}
});

Deno.test('other images are not supported and not read', () =>
{
	for (const type of ['image/tiff', 'image/bmp', 'image/heic'])
	{
		using _page = createPage();

		assertThrows(() => new ImagePreview({name: 'lobby', size: 1, type: type}).createPreview(), Error,
			'Unsupported file format: ' + type);
		assertEquals(FakeFileReader.last, null, type);
	}
});
