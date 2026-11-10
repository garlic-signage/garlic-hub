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
import {FontCollector} from "@js/templates/canvas-composer/Fonts/FontCollector.js";

/**
 * The FontLoader with a list of names; Lato is loaded already.
 */
class FakeFontLoader
{
	names  = ['Roboto', 'Lato', 'Open Sans'];
	loaded = [1];
	calls  = [];

	findFontListKey(name)
	{
		return this.names.indexOf(name);
	}

	isFontLoaded(id)
	{
		return this.loaded.includes(id);
	}

	async loadFontFaceAsync(id)
	{
		this.calls.push(id);
		this.loaded.push(id);
	}
}

Deno.test('the font of a text is collected once', () =>
{
	const collector = new FontCollector(new FakeFontLoader());

	collector.collectUsedFontsFromSelection({fontFamily: 'Roboto'});
	collector.collectUsedFontsFromSelection({fontFamily: 'Roboto', styles: {}});

	assertEquals(collector.used_fonts, ['Roboto']);
});

Deno.test('the fonts of the characters are collected from the ranges of fabric.js 6', () =>
{
	const collector = new FontCollector(new FakeFontLoader());

	collector.collectUsedFontsFromSelection({fontFamily: 'Roboto', styles: [
		{start: 0, end: 4, style: {fontFamily: 'Lato'}},
		{start: 4, end: 6, style: {fontWeight: 'bold'}},
		{start: 6, end: 9, style: {fontFamily: 'Roboto'}},
	]});

	assertEquals(collector.used_fonts, ['Roboto', 'Lato']);
});

Deno.test('the fonts of the characters are collected from the lines of older templates', () =>
{
	const collector = new FontCollector(new FakeFontLoader());

	collector.collectUsedFontsFromSelection({fontFamily: 'Roboto', styles: {
		0: {0: {fontFamily: 'Lato'}, 1: {fill: 'red'}},
		1: {3: {fontFamily: 'Open Sans'}, 4: null},
	}});

	assertEquals(collector.used_fonts, ['Roboto', 'Lato', 'Open Sans']);
});

Deno.test('empty names, other values and broken styles are left out', () =>
{
	const collector = new FontCollector(new FakeFontLoader());

	collector.collectUsedFontsFromSelection({fontFamily: '', styles: {0: null, 1: 'text', 2: {0: {fontFamily: 5}}}});
	collector.collectUsedFontsFromSelection({});
	collector.collectUsedFontsFromSelection({fontFamily: undefined, styles: null});

	assertEquals(collector.used_fonts, []);
});

Deno.test('only the known fonts which are not loaded yet are loaded, one after the other', async () =>
{
	const loader    = new FakeFontLoader();
	const collector = new FontCollector(loader);
	collector.used_fonts = ['Open Sans', 'Lato', 'Comic Sans', 'Roboto'];

	await collector.preloadUsedFonts();

	assertEquals(loader.calls, [2, 0]);
});
