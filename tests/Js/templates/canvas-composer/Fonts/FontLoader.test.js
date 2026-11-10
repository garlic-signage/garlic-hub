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
import {stub}           from "@std/testing/mock";
import {createDom}      from "@support/dom.js";
import {replaceGlobals} from "@support/globals.js";
import {FontLoader}     from "@js/templates/canvas-composer/Fonts/FontLoader.js";

/**
 * The FontFace of the browser: load() answers with the error set by the test.
 */
class FakeFontFace
{
	static created = [];
	static error   = null;

	family;
	source;
	descriptors;

	constructor(family, source, descriptors)
	{
		this.family      = family;
		this.source      = source;
		this.descriptors = descriptors;
		FakeFontFace.created.push(this);
	}

	async load()
	{
		if (FakeFontFace.error !== null)
			throw FakeFontFace.error;
		return this;
	}
}

function fontsList()
{
	return [
		{name: 'Roboto', url: '/resources/fonts/Roboto.woff2', loaded: false},
		{name: 'Lato', url: '/resources/fonts/Lato.woff2', loaded: true},
	];
}

/**
 * document.fonts of the browser records the added fonts; the log of a loaded font is silenced.
 */
function createLoader(list = fontsList())
{
	const dom   = createDom();
	const added = [];
	Object.defineProperty(dom.document, 'fonts', {value: {add: (face) => added.push(face)}, configurable: true});
	FakeFontFace.created = [];
	FakeFontFace.error   = null;
	const globals = replaceGlobals({FontFace: FakeFontFace});
	const log     = stub(console, 'log');

	return {
		loader: new FontLoader(list), list, added,
		[Symbol.dispose]: () => { log.restore(); globals[Symbol.dispose](); dom[Symbol.dispose](); }
	};
}

Deno.test('a font is found in the list by its name', () =>
{
	using page = createLoader();

	assertEquals(page.loader.fontsList, page.list);
	assertEquals(page.loader.findFontListKey('Lato'), 1);
	assertEquals(page.loader.findFontListKey('Comic Sans'), -1);
	assertEquals([page.loader.isFontLoaded(0), page.loader.isFontLoaded(1)], [false, true]);
});

Deno.test('a font is loaded from its address and added to the document', async () =>
{
	using page = createLoader();

	await page.loader.loadFontFaceAsync(0);

	const [face] = FakeFontFace.created;
	assertEquals([face.family, face.source, face.descriptors], ['Roboto', 'url(/resources/fonts/Roboto.woff2)', {style: 'normal', weight: 'normal'}]);
	assertEquals(page.added.length, 1);
	assertEquals(page.added[0] === face, true);
	assertEquals(page.loader.isFontLoaded(0), true);
});

Deno.test('a font which cannot be loaded is logged and stays not loaded', async () =>
{
	using page      = createLoader();
	using errorStub = stub(console, 'error');
	const error     = new Error('NetworkError');
	FakeFontFace.error = error;

	await page.loader.loadFontFaceAsync(0);

	assertEquals(errorStub.calls[0].args, ['Font can not be loaded: Roboto', error]);
	assertEquals(page.added, []);
	assertEquals(page.loader.isFontLoaded(0), false);
});

Deno.test('an unknown font is logged without stopping the composer', async () =>
{
	using page      = createLoader();
	using errorStub = stub(console, 'error');

	await page.loader.loadFontFaceAsync(9);

	assertEquals(errorStub.calls[0].args[0], 'Font can not be loaded: undefined');
	assertEquals(page.added, []);
});

Deno.test('the callback comes after the font is loaded', async () =>
{
	using page  = createLoader();
	const order = [];

	await page.loader.loadFontFace(0, () => order.push(page.loader.isFontLoaded(0)));

	assertEquals(order, [true]);
	assertEquals(FakeFontFace.created.length, 1);
});

Deno.test('a loaded font is not loaded again, the callback comes at once', async () =>
{
	using page = createLoader();
	let called = 0;

	await page.loader.loadFontFace(1, () => called++);

	assertEquals(called, 1);
	assertEquals(FakeFontFace.created, []);
});
