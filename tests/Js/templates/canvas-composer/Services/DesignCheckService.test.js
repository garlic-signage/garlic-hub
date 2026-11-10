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

import {assertEquals}                    from "@std/assert";
import {composerFabric, composerWrapper} from "@support/ComposerServicesFakes.js";
import {replaceGlobals}                  from "@support/globals.js";
import {DesignCheckService}              from "@js/templates/canvas-composer/Services/DesignCheckService.js";

/**
 * The canvas renders the area of a text as pixels of one color (RGBA), the default is white.
 */
function canvasOf(background)
{
	const rendered = [];
	return {
		rendered,
		toCanvasElement(multiplier, area)
		{
			if (background instanceof Error)
				throw background;

			rendered.push(area);
			const pixels = new Uint8ClampedArray(16 * 4);
			for (let i = 0; i < pixels.length; i += 4)
				pixels.set(background, i);

			return {width: 4, height: 4, getContext: () => ({getImageData: () => ({data: pixels})})};
		}
	};
}

function text(options = {})
{
	return {type: 'textbox', text: 'Welcome', fontSize: 60, scaleY: 1, fill: '#000000', visible: true, ...options};
}

/**
 * A template of 1920 x 1080 with the objects, an item of 10 seconds.
 */
function createService(objects, {background = [255, 255, 255, 255], width = 1920, height = 1080, duration = 10} = {})
{
	const globals = replaceGlobals({fabric: composerFabric()});
	const canvas  = canvasOf(background);
	const wrapper = composerWrapper({
		getObjects: () => objects,
		getViewportBoundingRect: () => ({left: 5, top: 6, width: 0.4, height: 30})
	}, {fabricCanvas: canvas});

	return {
		canvas, wrapper,
		service: new DesignCheckService(wrapper, {width, height}, duration),
		[Symbol.dispose]: () => globals[Symbol.dispose]()
	};
}

function keys(hints)
{
	return hints.map(hint => hint.key);
}

Deno.test('a black text on white has no hints', () =>
{
	using page = createService([text()]);

	assertEquals(page.service.check(), []);
});

Deno.test('a text smaller than a thirtieth of the shorter side is too small, also on a turned screen', () =>
{
	for (const [width, height] of [[1920, 1080], [1080, 1920]])
	{
		const small = text({fontSize: 35});
		using page  = createService([small, text({fontSize: 36})], {width, height});

		const hints = page.service.check();

		assertEquals(hints.length, 1, `${width} x ${height}`);
		assertEquals(hints[0].object === small, true);
		assertEquals([hints[0].key, hints[0].values], ['design_small_font', {size: 35, min: 36}]);
	}
});

Deno.test('the size of a scaled text counts', () =>
{
	using page = createService([text({fontSize: 20, scaleY: 2})]);

	assertEquals(page.service.check(), []);
});

Deno.test('a grey text on white has too little contrast', () =>
{
	using page = createService([text({fill: '#999999', fontSize: 40})]);

	const [hint] = page.service.check();

	assertEquals([hint.key, hint.values], ['design_low_contrast', {ratio: '2.8', min: 4.5}]);
});

Deno.test('a large text needs less contrast', () =>
{
	// #808080 on white: 3.9, enough for a text of at least a twentieth of the shorter side (54)
	{
		using page = createService([text({fill: '#808080', fontSize: 54})]);
		assertEquals(page.service.check(), []);
	}
	{
		using page = createService([text({fill: '#808080', fontSize: 53})]);
		assertEquals(page.service.check().map(hint => hint.values), [{ratio: '3.9', min: 4.5}]);
	}
});

Deno.test('the background is rendered without the text in the area of the text on the canvas', () =>
{
	const label = text();
	using page  = createService([label]);
	let visibleWhileRendered = null;
	const render = page.canvas.toCanvasElement;
	page.canvas.toCanvasElement = (...args) => { visibleWhileRendered = label.visible; return render(...args); };

	page.service.check();

	assertEquals(visibleWhileRendered, false);
	assertEquals(label.visible, true);
	// at least one pixel of the width of the area
	assertEquals(page.canvas.rendered, [{left: 5, top: 6, width: 1, height: 30}]);
	assertEquals(page.wrapper.calls.find(call => call[0] === 'getViewportBoundingRect')[1] === label, true);
});

Deno.test('a transparent background counts as white', () =>
{
	using page = createService([text({fill: '#ffffff'})], {background: [0, 0, 0, 0]});

	assertEquals(keys(page.service.check()), ['design_low_contrast']);
});

Deno.test('a white text on a dark background has enough contrast', () =>
{
	using page = createService([text({fill: '#ffffff'})], {background: [20, 20, 20, 255]});

	assertEquals(page.service.check(), []);
});

Deno.test('a contrast which can not be measured gives no hint', () =>
{
	{
		// a gradient
		using page = createService([text({fill: {type: 'linear'}})]);
		assertEquals(page.service.check(), []);
	}
	{
		// an image of another origin taints the canvas
		const label = text({fill: '#eeeeee'});
		using page  = createService([label], {background: new Error('tainted')});
		assertEquals(page.service.check(), []);
		assertEquals(label.visible, true);
	}
});

Deno.test('a placeholder which was not replaced is a hint, a hidden one not', () =>
{
	const placeholder = {type: 'image', placeholder: true};
	using page = createService([placeholder, {type: 'image', placeholder: true, visible: false}, {type: 'image', placeholder: false}]);

	const hints = page.service.check();

	assertEquals(hints.map(hint => [hint.key, hint.object === placeholder, hint.values]), [['design_placeholder', true, {}]]);
});

Deno.test('the texts need more time to read than the item shows them', () =>
{
	// 2 seconds to look and 30 words at three a second: 12 seconds
	const words = Array.from({length: 15}, (_, i) => 'word' + i).join(' ');
	using page  = createService([text({text: words}), text({text: words})], {duration: 11});

	const hints = page.service.check();

	assertEquals(hints.map(hint => [hint.key, hint.object, hint.values]), [['design_reading_time', null, {needed: 12, duration: 11}]]);
});

Deno.test('enough time for the texts is no hint', () =>
{
	const words = Array.from({length: 30}, (_, i) => 'word' + i).join('  \n ');
	using page  = createService([text({text: words})], {duration: 12});

	assertEquals(page.service.check(), []);
});

Deno.test('hidden and empty texts are not checked', () =>
{
	using page = createService([text({fontSize: 5, visible: false}), text({fontSize: 5, text: '   '})], {duration: 0});

	assertEquals(page.service.check(), []);
	assertEquals(page.canvas.rendered, []);
});

Deno.test('text, i-text and textbox are texts', () =>
{
	assertEquals(['text', 'i-text', 'textbox', 'image', 'rect'].map(type => DesignCheckService.isText({type})),
		[true, true, true, false, false]);
});
