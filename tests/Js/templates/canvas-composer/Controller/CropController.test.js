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

import {assertEquals}                   from "@std/assert";
import {createDom}                      from "@support/dom.js";
import {FakeFabricWrapper, imageObject} from "@support/ComposerControllerFakes.js";
import {busOfTest}                      from "@support/MultizoneEvents.js";
import {CropController}                 from "@js/templates/canvas-composer/Controller/CropController.js";

/**
 * The crop mode of the CropService: enter() starts it with the image, apply() and cancel() end it.
 */
class FakeCropService
{
	calls = [];
	image = null;

	isCropping()
	{
		return this.image !== null;
	}

	enter(image)
	{
		this.calls.push(['enter', image]);
		this.image = image;
	}

	apply()
	{
		this.calls.push(['apply']);
		this.image = null;
	}

	cancel()
	{
		this.calls.push(['cancel']);
		this.image = null;
	}
}

function createController()
{
	const dom     = createDom();
	const bus     = busOfTest();
	const service = new FakeCropService();
	const wrapper = new FakeFabricWrapper();
	new CropController(service, wrapper).init();

	return {
		document: dom.document, bus, service, wrapper,
		press: (key) =>
		{
			const event = new Event('keydown', {bubbles: true, cancelable: true});
			event.key   = key;
			dom.document.dispatchEvent(event);
			return event;
		},
		[Symbol.dispose]: () => { bus[Symbol.dispose](); dom[Symbol.dispose](); }
	};
}

/**
 * The service crops the given image: the calls before are forgotten.
 */
function cropping(page, image = imageObject())
{
	page.service.image = image;
	return image;
}

Deno.test('"crop image" of the menu crops the active object', () =>
{
	using page = createController();
	const image = imageObject();
	page.wrapper.activeObject = image;

	page.bus.send('cropImage');

	assertEquals(page.service.calls, [['enter', image]]);
});

Deno.test('a double click crops an image, other objects not', () =>
{
	using page = createController();
	const image = imageObject();

	page.wrapper.emit('mouse:dblclick', {target: {type: 'rect'}});
	page.wrapper.emit('mouse:dblclick', {target: image});

	assertEquals(page.service.calls, [['enter', image]]);
});

Deno.test('a double click on the cropped image keeps the cut', () =>
{
	using page = createController();
	const image = cropping(page);

	page.wrapper.emit('mouse:dblclick', {target: image});

	assertEquals(page.service.calls, [['apply']]);
});

Deno.test('a double click on another image while cropping keeps the cut and crops the other one', () =>
{
	using page  = createController();
	cropping(page);
	const other = imageObject();

	page.wrapper.emit('mouse:dblclick', {target: other});

	// the service ends the crop mode of the first image when it enters the second one
	assertEquals(page.service.calls, [['enter', other]]);
});

Deno.test('a step of the history keeps the cut first', () =>
{
	using page = createController();

	page.bus.send('beforeHistory');

	assertEquals(page.service.calls, [['apply']]);
});

Deno.test('the selection of something else keeps the cut, the cropped image itself not', () =>
{
	using page = createController();
	const image = cropping(page);

	page.wrapper.emit('selection:updated', {selected: [image]});
	page.wrapper.emit('selection:created', {selected: [image]});
	assertEquals(page.service.calls, []);

	page.wrapper.emit('selection:updated', {selected: [{type: 'rect'}]});
	assertEquals(page.service.calls, [['apply']]);
});

Deno.test('a selection of several objects or a cleared one keeps the cut', () =>
{
	{
		using page = createController();
		const image = cropping(page);
		page.wrapper.emit('selection:created', {selected: [image, {type: 'rect'}]});
		assertEquals(page.service.calls, [['apply']]);
	}
	{
		using page = createController();
		cropping(page);
		page.wrapper.emit('selection:cleared');
		assertEquals(page.service.calls, [['apply']]);
	}
});

Deno.test('a selection without crop mode changes nothing', () =>
{
	using page = createController();

	page.wrapper.emit('selection:updated', {selected: [{type: 'rect'}]});
	page.wrapper.emit('selection:created', {});

	assertEquals(page.service.calls, []);
});

Deno.test('Enter keeps the cut and Escape cancels it, both for the crop mode only', () =>
{
	{
		using page  = createController();
		cropping(page);
		const event = page.press('Enter');
		assertEquals(page.service.calls, [['apply']]);
		assertEquals(event.defaultPrevented, true);
	}
	{
		using page  = createController();
		cropping(page);
		const event = page.press('Escape');
		assertEquals(page.service.calls, [['cancel']]);
		assertEquals(event.defaultPrevented, true);
	}
});

Deno.test('other keys keep the cut and do what they do, the modifiers wait', () =>
{
	using page = createController();
	cropping(page);

	for (const key of ['Shift', 'Control', 'Alt', 'Meta'])
		page.press(key);
	assertEquals(page.service.calls, []);

	const event = page.press('Delete');
	assertEquals(page.service.calls, [['apply']]);
	assertEquals(event.defaultPrevented, false);
});

Deno.test('without crop mode the keys are not touched', () =>
{
	using page = createController();

	const event = page.press('Enter');
	page.press('Escape');

	assertEquals(page.service.calls, []);
	assertEquals(event.defaultPrevented, false);
});
