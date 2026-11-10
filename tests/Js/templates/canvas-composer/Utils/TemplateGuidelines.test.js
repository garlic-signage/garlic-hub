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
import {replaceGlobals} from "@support/globals.js";

/**
 * AligningGuidelines of the extensions of fabric.js: it notes what it was asked for.
 */
class FakeAligningGuidelines
{
	calls = [];

	constructor(canvas, options)
	{
		this.canvas  = canvas;
		this.options = options;
	}

	moving(event)       { this.calls.push(['moving', event]); }
	beforeRender()      { this.calls.push(['beforeRender']); }
	afterRender()       { this.calls.push(['afterRender']); }
	getObjectsByTarget() { return new Set(['other object']); }
}

class FakeRect
{
	constructor(options)
	{
		Object.assign(this, options);
	}
}

// the class extends the one of the extensions when its module is loaded
let TemplateGuidelines;
{
	using _globals = replaceGlobals({fabricExtensions: {AligningGuidelines: FakeAligningGuidelines}});
	({TemplateGuidelines} = await import("@js/templates/canvas-composer/Utils/TemplateGuidelines.js?test=guidelines"));
}

/**
 * The canvas of 960 x 540 px at a zoom of 0.5: the template is 1920 x 1080.
 */
function createCanvas(contextTop = {})
{
	return {contextTop, getZoom: () => 0.5, getWidth: () => 960, getHeight: () => 540};
}

Deno.test('the guidelines are orange and snap within 6 px', () =>
{
	const guidelines = new TemplateGuidelines(createCanvas());

	assertEquals(guidelines.options, {color: 'rgba(217, 119, 6, 0.9)', margin: 6});
});

Deno.test('a moved object snaps, an image in the crop mode does not', () =>
{
	const guidelines = new TemplateGuidelines(createCanvas());
	const moved      = {target: {type: 'rect'}};

	guidelines.moving(moved);
	guidelines.moving({target: {isCropping: true}});
	guidelines.moving({target: {isCropping: false}});

	assertEquals(guidelines.calls.map(call => call[0]), ['moving', 'moving']);
	assertEquals(guidelines.calls[0][1] === moved, true);
});

Deno.test('the lines are drawn only on a canvas with its top layer', () =>
{
	const shown  = new TemplateGuidelines(createCanvas({}));
	// while the canvas renders into a picture
	const hidden = new TemplateGuidelines(createCanvas(null));

	for (const guidelines of [shown, hidden])
	{
		guidelines.beforeRender();
		guidelines.afterRender();
	}

	assertEquals(shown.calls, [['beforeRender'], ['afterRender']]);
	assertEquals(hidden.calls, []);
});

Deno.test('the template itself is a frame to snap to, in its own size', () =>
{
	using _globals   = replaceGlobals({fabric: {Rect: FakeRect}});
	const guidelines = new TemplateGuidelines(createCanvas());

	const [other, frame] = [...guidelines.getObjectsByTarget({})];

	assertEquals(other, 'other object');
	assertEquals({...frame}, {left: 0, top: 0, strokeWidth: 0, width: 1920, height: 1080});
});
