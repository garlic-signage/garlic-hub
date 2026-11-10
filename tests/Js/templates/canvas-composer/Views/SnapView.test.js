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

import {assertEquals}                 from "@std/assert";
import {assertSameElement, createDom} from "@support/dom.js";
import {SnapView}                     from "@js/templates/canvas-composer/Views/SnapView.js";

// the choice of the grid in templates/templates/canvas.mustache
const PAGE = `
	<select id="snapToGrid">
		<option value="0">No grid</option><option value="5">5 px</option><option value="10">10 px</option>
		<option value="20">20 px</option><option value="50">50 px</option>
	</select>`;

/**
 * The 2d context of the canvas: it notes the lines.
 */
class FakeContext
{
	lines   = [];
	strokes = 0;
	#from   = null;

	beginPath()   { this.#from = null; }
	moveTo(x, y)  { this.#from = [x, y]; }
	lineTo(x, y)  { this.lines.push([...this.#from, x, y]); }
	stroke()      { this.strokes++; }
}

function createView(gridSize)
{
	const dom     = createDom(PAGE);
	const context = new FakeContext();
	dom.document.getElementById('snapToGrid').value = gridSize;
	return {document: dom.document, context, view: new SnapView(context), [Symbol.dispose]: () => dom[Symbol.dispose]()};
}

Deno.test('the choice of the grid is read from the page', () =>
{
	using page = createView('20');

	assertSameElement(page.view.snapToGrid, page.document.getElementById('snapToGrid'));
	assertEquals(page.view.getSnapToGridValue(), '20');
});

Deno.test('the grid is drawn in light grey lines across the canvas', () =>
{
	using page = createView('50');

	page.view.drawGrid({width: 100, height: 100, zoom: 1});

	assertEquals([page.context.strokeStyle, page.context.lineWidth], ['#e0e0e0', 0.5]);
	assertEquals(page.context.lines, [
		[0, 0, 0, 100], [50, 0, 50, 100],
		[0, 0, 100, 0], [0, 50, 100, 50]
	]);
	assertEquals(page.context.strokes, 4);
});

Deno.test('the grid follows the zoom of the canvas', () =>
{
	using page = createView('20');

	page.view.drawGrid({width: 100, height: 30, zoom: 2.5});

	// 20 px of the template are 50 px on the canvas
	assertEquals(page.context.lines.map(line => line.slice(0, 2)), [[0, 0], [50, 0], [0, 0]]);
});
