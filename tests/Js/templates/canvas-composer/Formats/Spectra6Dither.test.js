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

import {assertEquals, assertRejects} from "@std/assert";
import {createDom}                   from "@support/dom.js";
import {replaceGlobals}              from "@support/globals.js";
import {Spectra6Dither}              from "@js/templates/canvas-composer/Formats/Spectra6Dither.js";

/**
 * The image of the browser: it loads when its source is set, with the size of the pixels the test gives.
 */
class FakeImage
{
	static pixels = {width: 0, height: 0, data: []};
	static fails  = false;
	onload  = null;
	onerror = null;
	width   = 0;
	height  = 0;

	set src(value)
	{
		this.width  = FakeImage.pixels.width;
		this.height = FakeImage.pixels.height;
		setTimeout(() => FakeImage.fails ? this.onerror(new Event('error')) : this.onload(), 0);
	}
}

/**
 * linkedom has no canvas: its 2d context gives the pixels of the image as RGBA.
 */
function createPage(width, height, rgb)
{
	const dom       = createDom();
	const globals   = replaceGlobals({Image: FakeImage});
	const prototype = Object.getPrototypeOf(dom.document.createElement('canvas'));
	const original  = Object.getOwnPropertyDescriptor(prototype, 'getContext');
	const drawn     = [];
	FakeImage.fails  = false;
	FakeImage.pixels = {width, height, data: rgb.flatMap(([r, g, b]) => [r, g, b, 255])};
	Object.defineProperty(prototype, 'getContext', {configurable: true, value(type)
	{
		const canvas = this;
		return {
			type,
			drawImage: (image, x, y) => drawn.push([image.width, image.height, x, y, canvas.width, canvas.height]),
			getImageData: (x, y, w, h) => ({width: w, height: h, data: new Uint8ClampedArray(FakeImage.pixels.data)})
		};
	}});

	return {
		drawn,
		[Symbol.dispose]: () =>
		{
			if (original === undefined)
				delete prototype.getContext;
			else
				Object.defineProperty(prototype, 'getContext', original);
			globals[Symbol.dispose]();
			dom[Symbol.dispose]();
		}
	};
}

/**
 * The bytes of the BMP of a data URL.
 */
function bytesOf(dataUrl)
{
	const [header, base64] = dataUrl.split(',');
	assertEquals(header, 'data:image/bmp;base64');
	return Uint8Array.from(atob(base64), char => char.charCodeAt(0));
}

function header(bytes)
{
	const view = new DataView(bytes.buffer);
	return {
		magic: String.fromCharCode(bytes[0], bytes[1]), fileSize: view.getUint32(2, true), offset: view.getUint32(10, true),
		dibSize: view.getUint32(14, true), width: view.getInt32(18, true), height: view.getInt32(22, true),
		planes: view.getUint16(26, true), bits: view.getUint16(28, true), imageSize: view.getUint32(34, true)
	};
}

/**
 * The pixels of the BMP as RGB, the rows are filled up to 4 bytes.
 */
function pixelsOf(bytes, width, height)
{
	const rowSize = Math.floor((24 * width + 31) / 32) * 4;
	const pixels  = [];
	for (let y = 0; y < height; y++)
		for (let x = 0; x < width; x++)
		{
			const index = 54 + y * rowSize + x * 3;
			pixels.push([bytes[index + 2], bytes[index + 1], bytes[index]]);
		}
	return pixels;
}

Deno.test('the palette has the six colors of the display', () =>
{
	assertEquals(Spectra6Dither.PALETTE, [[0, 0, 0], [255, 255, 255], [255, 0, 0], [0, 255, 0], [0, 0, 255], [255, 255, 0]]);
});

Deno.test('the image becomes a 24 bit BMP from the top down in its size', async () =>
{
	using page = createPage(2, 1, [[255, 0, 0], [0, 0, 255]]);

	const bytes = bytesOf(await new Spectra6Dither().convert('data:image/png;base64,AAAA'));

	// a row of 2 pixels has 6 bytes and is filled up to 8
	assertEquals(header(bytes), {magic: 'BM', fileSize: 62, offset: 54, dibSize: 40, width: 2, height: -1, planes: 1, bits: 24, imageSize: 8});
	assertEquals(bytes.length, 62);
	assertEquals(page.drawn, [[2, 1, 0, 0, 2, 1]]);
});

Deno.test('the colors of the palette stay, stored as BGR', async () =>
{
	const palette = Spectra6Dither.PALETTE;
	using _page   = createPage(6, 1, palette);

	const bytes = bytesOf(await new Spectra6Dither().convert('data:image/png;base64,AAAA'));

	assertEquals(pixelsOf(bytes, 6, 1), palette);
	// the third pixel is red: blue, green, red
	assertEquals([...bytes.slice(54 + 6, 54 + 9)], [0, 0, 255]);
});

Deno.test('every pixel gets the nearest color of the palette', async () =>
{
	using _page = createPage(1, 3, [[200, 30, 20], [20, 20, 230], [240, 230, 40]]);

	const bytes = bytesOf(await new Spectra6Dither().convert('data:image/png;base64,AAAA'));

	// one pixel per row: the error goes only down to the next row
	assertEquals(pixelsOf(bytes, 1, 3).every(pixel => Spectra6Dither.PALETTE.some(color => color.join() === pixel.join())), true);
	assertEquals(pixelsOf(bytes, 1, 3)[0], [255, 0, 0]);
});

Deno.test('a grey area becomes a pattern of black and white', async () =>
{
	const grey  = Array.from({length: 16}, () => [128, 128, 128]);
	using _page = createPage(4, 4, grey);

	const pixels = pixelsOf(bytesOf(await new Spectra6Dither().convert('data:image/png;base64,AAAA')), 4, 4);

	const black = pixels.filter(pixel => pixel.join() === '0,0,0').length;
	const white = pixels.filter(pixel => pixel.join() === '255,255,255').length;
	assertEquals(black + white, 16);
	assertEquals(black > 4 && white > 4, true);
});

Deno.test('an image which cannot be loaded fails the conversion', async () =>
{
	using _page = createPage(1, 1, [[0, 0, 0]]);
	FakeImage.fails = true;

	await assertRejects(() => new Spectra6Dither().convert('data:image/png;base64,broken'));
});
