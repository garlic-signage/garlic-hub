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

import {assertEquals}                          from "@std/assert";
import {createDom}                             from "@support/dom.js";
import {replaceGlobals}                        from "@support/globals.js";
import {GlobalWorkerOptions, loaded, rendered} from "@support/FakePdfjs.js";
import {PdfPreview}                            from "@js/mediapool/uploads/Preview/PdfPreview.js";

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

	readAsArrayBuffer(file)
	{
		this.read.push(file);
	}

	async load(result)
	{
		await this.onload({target: {result}});
	}
}

/**
 * linkedom has no canvas: getContext() gives a recorded 2d context.
 */
function createPage()
{
	const dom       = createDom();
	const globals   = replaceGlobals({FileReader: FakeFileReader});
	const prototype = Object.getPrototypeOf(dom.document.createElement('canvas'));
	const original  = Object.getOwnPropertyDescriptor(prototype, 'getContext');
	const contexts  = [];
	Object.defineProperty(prototype, 'getContext', {configurable: true, value(type) {
		const context = {type};
		contexts.push(context);
		return context;
	}});
	loaded.length   = 0;
	rendered.length = 0;

	return {
		contexts,
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

Deno.test('pdf.js works with the worker of the web server', () =>
{
	assertEquals(GlobalWorkerOptions.workerSrc, '/js/external/pdf.worker.min.mjs');
});

Deno.test('the preview is a canvas, the file is read as array buffer', () =>
{
	using page = createPage();
	const file = {name: 'menu.pdf', size: 1, type: 'application/pdf'};

	const canvas = new PdfPreview(file).createPreview();

	assertEquals(canvas.tagName.toLowerCase(), 'canvas');
	assertEquals(page.contexts.map(context => context.type), ['2d']);
	assertEquals(FakeFileReader.last.read.length, 1);
	assertEquals(FakeFileReader.last.read[0] === file, true);
	assertEquals(loaded, []);
});

Deno.test('the first page of the read file is drawn in the size of the page', async () =>
{
	using page   = createPage();
	const canvas = new PdfPreview({name: 'menu.pdf', size: 1, type: 'application/pdf'}).createPreview();

	await FakeFileReader.last.load(new Uint8Array([37, 80, 68, 70]).buffer);

	assertEquals(loaded.length, 1);
	assertEquals([...loaded[0]], [37, 80, 68, 70]);
	assertEquals([canvas.width, canvas.height], [600, 800]);
	assertEquals(rendered.length, 1);
	assertEquals(rendered[0].canvasContext === page.contexts[0], true);
	assertEquals(rendered[0].viewport, {width: 600, height: 800});
});
