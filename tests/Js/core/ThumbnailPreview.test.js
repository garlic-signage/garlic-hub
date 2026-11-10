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

import {assertEquals, assertStrictEquals} from "@std/assert";
import {FakeTime}                         from "@std/testing/time";
import {createDom}                        from "@support/dom.js";
import {replaceGlobals}                   from "@support/globals.js";
import {ThumbnailPreview}                 from "@js/core/ThumbnailPreview.js";

const PAGE = `
	<div id="list">
		<ul id="inner">
			<li><img id="thumb" class="thumbnail" src="/thumbs/lobby.jpg"></li>
			<li><img id="empty" class="thumbnail" src=""></li>
			<li><img id="other" class="media-thumbnail" src="/thumbs/other.jpg"></li>
			<li><span id="text" class="thumbnail">Text</span></li>
		</ul>
	</div>
	<dialog id="dialog"><div id="dialogList"></div></dialog>`;

/**
 * linkedom has no layout: the window, the place of the thumbnails and the size of the preview are set here.
 * The window is 1000 x 800, the preview 200 x 100.
 */
function createPreview(containerId = 'list', thumbnailClass = undefined)
{
	const dom     = createDom(PAGE);
	const globals = replaceGlobals({window: {innerWidth: 1000, innerHeight: 800}});
	const document = dom.document;

	placeThumbnail(document.getElementById('thumb'), {left: 100, top: 300, width: 80, height: 60});

	const before  = new Set(document.querySelectorAll('.thumbnail-preview'));
	new ThumbnailPreview(document.getElementById(containerId), thumbnailClass).init();
	const preview = [...document.querySelectorAll('.thumbnail-preview')].find(element => !before.has(element));
	Object.defineProperty(preview, 'offsetWidth', {value: 200});
	Object.defineProperty(preview, 'offsetHeight', {value: 100});

	return {
		document, preview,
		[Symbol.dispose]: () => { globals[Symbol.dispose](); dom[Symbol.dispose](); }
	};
}

function placeThumbnail(thumbnail, {left, top, width, height})
{
	thumbnail.getBoundingClientRect = () => ({left, top, width, height, right: left + width, bottom: top + height});
}

function fire(page, id, type)
{
	const event = new Event(type, {bubbles: true, cancelable: true});
	page.document.getElementById(id).dispatchEvent(event);
	return event;
}

function isVisible(page)
{
	return page.preview.classList.contains('visible');
}

function position(page)
{
	return [page.preview.style.left, page.preview.style.top];
}

Deno.test('the preview is added to the body, inside a dialog to the dialog', () =>
{
	{
		using page = createPreview();
		assertStrictEquals(page.preview.parentElement, page.document.body);
		assertEquals(page.preview.getAttribute('alt'), '');
	}
	{
		using page = createPreview('dialogList');
		assertStrictEquals(page.preview.parentElement, page.document.getElementById('dialog'));
	}
});

Deno.test('hovering a thumbnail shows it right beside, vertically centred', () =>
{
	using page = createPreview();

	fire(page, 'thumb', 'mouseover');

	assertEquals(isVisible(page), true);
	assertEquals(page.preview.getAttribute('src'), '/thumbs/lobby.jpg');
	// right: 100 + 80 + 8, top: 300 + (60 - 100) / 2
	assertEquals(position(page), ['188px', '280px']);
});

Deno.test('without room on the right the preview goes to the left', () =>
{
	using page = createPreview();
	placeThumbnail(page.document.getElementById('thumb'), {left: 750, top: 300, width: 80, height: 60});

	fire(page, 'thumb', 'mouseover');

	// 750 - 200 - 8
	assertEquals(position(page)[0], '542px');
});

Deno.test('without room on both sides the preview keeps the gap to the left edge', () =>
{
	using page = createPreview();
	placeThumbnail(page.document.getElementById('thumb'), {left: 100, top: 300, width: 750, height: 60});

	fire(page, 'thumb', 'mouseover');

	assertEquals(position(page)[0], '8px');
});

Deno.test('the preview stays inside the window at the top and the bottom', () =>
{
	using page = createPreview();
	const thumb = page.document.getElementById('thumb');

	placeThumbnail(thumb, {left: 100, top: 0, width: 80, height: 20});
	fire(page, 'thumb', 'mouseover');
	assertEquals(position(page)[1], '8px');

	placeThumbnail(thumb, {left: 100, top: 780, width: 80, height: 20});
	fire(page, 'thumb', 'mouseover');
	// 800 - 100 - 8
	assertEquals(position(page)[1], '692px');
});

Deno.test('only images of the thumbnail class with a source are shown', () =>
{
	using page = createPreview();

	for (const id of ['empty', 'other', 'text', 'inner'])
	{
		fire(page, id, 'mouseover');
		assertEquals(isVisible(page), false, id);
	}
});

Deno.test('the class of the thumbnails can be chosen', () =>
{
	using page = createPreview('list', 'media-thumbnail');

	fire(page, 'thumb', 'mouseover');
	assertEquals(isVisible(page), false);

	fire(page, 'other', 'mouseover');
	assertEquals(page.preview.getAttribute('src'), '/thumbs/other.jpg');
});

Deno.test('no preview while something is dragged', () =>
{
	using page = createPreview();
	page.document.body.classList.add('gu-unselectable');

	fire(page, 'thumb', 'mouseover');

	assertEquals(isVisible(page), false);
});

Deno.test('leaving the thumbnail hides the preview, leaving another element not', () =>
{
	using page = createPreview();
	fire(page, 'thumb', 'mouseover');

	fire(page, 'text', 'mouseout');
	assertEquals(isVisible(page), true);

	fire(page, 'thumb', 'mouseout');
	assertEquals(isVisible(page), false);
});

Deno.test('a click to drag and a scroll hide the preview', () =>
{
	using page = createPreview();

	fire(page, 'thumb', 'mouseover');
	fire(page, 'thumb', 'mousedown');
	assertEquals(isVisible(page), false);

	fire(page, 'thumb', 'mouseover');
	// linkedom has no capture phase: the scroll of an inner list cannot be tested, only the one of the container
	page.document.getElementById('list').dispatchEvent(new Event('scroll'));
	assertEquals(isVisible(page), false);
});

Deno.test('a finger on the thumbnail shows the preview until it is lifted or moves', () =>
{
	using time = new FakeTime();
	using page = createPreview();

	fire(page, 'thumb', 'touchstart');
	assertEquals(isVisible(page), true);
	fire(page, 'thumb', 'touchmove');
	assertEquals(isVisible(page), false);

	fire(page, 'thumb', 'touchstart');
	fire(page, 'thumb', 'touchend');
	assertEquals(isVisible(page), false);

	fire(page, 'thumb', 'touchstart');
	fire(page, 'thumb', 'touchcancel');
	assertEquals(isVisible(page), false);
});

Deno.test('the mouse events the browser emulates after a touch are ignored for 500 ms', () =>
{
	using time = new FakeTime();
	using page = createPreview();

	fire(page, 'thumb', 'touchstart');
	fire(page, 'thumb', 'touchend');
	fire(page, 'thumb', 'mouseover');
	assertEquals(isVisible(page), false);

	time.tick(499);
	fire(page, 'thumb', 'mouseover');
	assertEquals(isVisible(page), false);

	time.tick(1);
	fire(page, 'thumb', 'mouseover');
	assertEquals(isVisible(page), true);
});

Deno.test('the long press menu of the browser is suppressed only on a thumbnail during a touch', () =>
{
	using time = new FakeTime();
	using page = createPreview();

	assertEquals(fire(page, 'thumb', 'contextmenu').defaultPrevented, false);

	fire(page, 'thumb', 'touchstart');
	assertEquals(fire(page, 'thumb', 'contextmenu').defaultPrevented, true);
	assertEquals(fire(page, 'text', 'contextmenu').defaultPrevented, false);
});
