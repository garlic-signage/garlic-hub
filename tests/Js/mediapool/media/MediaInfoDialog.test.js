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

import {assertEquals}    from "@std/assert";
import {createDom}       from "@support/dom.js";
import {MediaInfoDialog} from "@js/mediapool/media/MediaInfoDialog.js";

// the dialog of templates/mediapool/overview.mustache
const PAGE = `
	<dialog id="editInfoDialog">
		<button id="closeInfoMediaDialog"></button>
		<ul>
			<li><span class="media-filename"></span></li>
			<li><span class="media-mimetype"></span></li>
			<li><span class="media-dimensions"></span></li>
			<li><span class="media-duration"></span></li>
			<li><span class="media-owner"></span></li>
			<li><span class="media-uploaded-at"></span></li>
			<li><span class="media-filesize"></span></li>
			<li><span class="media-description"></span></li>
			<li><span class="media-tags"></span></li>
			<li><span class="stock-name"></span></li>
			<li><a class="stock-page-url"></a></li>
			<li><span class="stock-description"></span></li>
			<li><span class="stock-username"></span></li>
			<li><span class="stock-realname"></span></li>
			<li><span class="stock-user-url"></span></li>
		</ul>
		<button id="closeInfoDialog"></button>
	</dialog>`;

const STOCK_ROWS = ['stock-page-url', 'stock-description', 'stock-username', 'stock-realname', 'stock-user-url'];

/**
 * A picture of a stock platform: every row has a value.
 */
const STOCK_PICTURE = {
	filename: 'lobby.jpg', mimetype: 'image/jpeg', username: 'admin', upload_time: '2026-11-10 09:30:00',
	media_description: 'The lobby', tags: ['lobby', 'hall'],
	metadata: {
		size: 1536, dimensions: {width: 1920, height: 1080}, origin: 'Pexels',
		page_url: 'https://www.pexels.com/photo/1', description: 'A bright lobby',
		user: {username: 'jdoe', name: 'Jane Doe', url: 'https://www.pexels.com/@jdoe'}
	}
};

/**
 * An uploaded video without stock data, tags and dimensions.
 */
const OWN_VIDEO = {
	filename: 'intro.mp4', mimetype: 'video/mp4', username: 'admin', upload_time: '2026-11-10 10:00:00',
	media_description: '', tags: [],
	metadata: {size: 5 * 1024 ** 2, duration: 72.48, origin: ''}
};

class FakeMediaService
{
	requested = [];
	media;
	error = null;

	getMediaById(mediaId)
	{
		this.requested.push(mediaId);
		return this.error === null ? Promise.resolve(this.media) : Promise.reject(this.error);
	}
}

function createInfoDialog()
{
	const dom    = createDom(PAGE);
	const dialog = dom.document.getElementById('editInfoDialog');
	const state  = {opened: 0, closed: []};
	// linkedom has no dialog methods
	dialog.showModal = () => state.opened++;
	dialog.close     = (value) => state.closed.push(value);

	const mediaService = new FakeMediaService();
	const errors       = [];
	const infoDialog   = new MediaInfoDialog(mediaService, {showError: (message) => errors.push(message)});

	return {
		document: dom.document, dialog, state, mediaService, errors, infoDialog,
		[Symbol.dispose]: () => dom[Symbol.dispose]()
	};
}

/**
 * Shows the medium and waits until the answer of the service is in the dialog.
 */
async function show(page, media)
{
	page.mediaService.media = media;
	const item = page.document.createElement('article');
	item.setAttribute('data-media-id', 'abc');

	page.infoDialog.show(item);
	await new Promise(resolve => setTimeout(resolve, 0));
}

function text(page, selector)
{
	return page.dialog.querySelector(selector).textContent;
}

function hiddenRows(page)
{
	return [...page.dialog.querySelectorAll('li')]
		.filter(row => row.style.display === 'none')
		.map(row => row.firstElementChild.className);
}

Deno.test('a picture of a stock platform shows every row', async () =>
{
	using page = createInfoDialog();

	await show(page, STOCK_PICTURE);

	assertEquals(page.mediaService.requested, ['abc']);
	assertEquals(text(page, '.media-filename'), 'lobby.jpg');
	assertEquals(text(page, '.media-mimetype'), 'image/jpeg');
	assertEquals(text(page, '.media-dimensions'), '1920x1080');
	assertEquals(text(page, '.media-owner'), 'admin');
	assertEquals(text(page, '.media-uploaded-at'), '2026-11-10 09:30:00');
	assertEquals(text(page, '.media-filesize'), '1.50 KB');
	assertEquals(text(page, '.media-description'), 'The lobby');
	assertEquals(text(page, '.media-tags'), 'lobby, hall');
	assertEquals(text(page, '.stock-name'), 'Pexels');
	assertEquals(page.dialog.querySelector('.stock-page-url').getAttribute('href'), 'https://www.pexels.com/photo/1');
	assertEquals(text(page, '.stock-description'), 'A bright lobby');
	assertEquals(text(page, '.stock-username'), 'jdoe');
	assertEquals(text(page, '.stock-realname'), 'Jane Doe');
	assertEquals(text(page, '.stock-user-url'), 'https://www.pexels.com/@jdoe');
	assertEquals(hiddenRows(page), ['media-duration']);
	assertEquals(page.state.opened, 1);
});

Deno.test('an own video hides the rows without values and rounds its duration', async () =>
{
	using page = createInfoDialog();

	await show(page, OWN_VIDEO);

	assertEquals(text(page, '.media-duration'), '00:01:12');
	assertEquals(text(page, '.media-filesize'), '5.00 MB');
	assertEquals(hiddenRows(page), ['media-dimensions', 'media-tags', 'stock-name', ...STOCK_ROWS]);
	assertEquals(page.state.opened, 1);
});

Deno.test('empty dimensions, a zero duration and a stock user without data are hidden', async () =>
{
	using page = createInfoDialog();
	const media = structuredClone(STOCK_PICTURE);
	media.metadata.dimensions = {};
	media.metadata.duration   = 0;
	media.metadata.user       = {username: '', name: '', url: ''};

	await show(page, media);

	assertEquals(hiddenRows(page), ['media-dimensions', 'media-duration', 'stock-username', 'stock-realname', 'stock-user-url']);
});

Deno.test('a medium without origin hides the row of the origin', async () =>
{
	using page = createInfoDialog();
	const media = structuredClone(OWN_VIDEO);
	delete media.metadata.origin;

	await show(page, media);

	assertEquals(hiddenRows(page).includes('stock-name'), true);
});

Deno.test('the rows hidden for one medium are shown again for the next', async () =>
{
	using page = createInfoDialog();

	await show(page, OWN_VIDEO);
	await show(page, STOCK_PICTURE);

	assertEquals(hiddenRows(page), ['media-duration']);
	assertEquals(page.state.opened, 2);
});

Deno.test('a failed request shows the error and no dialog', async () =>
{
	using page = createInfoDialog();
	page.mediaService.error = new Error('Not allowed');

	await show(page, null);

	assertEquals(page.errors, ['Not allowed']);
	assertEquals(page.state.opened, 0);
});

Deno.test('both buttons close the dialog', () =>
{
	using page = createInfoDialog();

	page.document.getElementById('closeInfoMediaDialog').dispatchEvent(new Event('click'));
	page.document.getElementById('closeInfoDialog').dispatchEvent(new Event('click'));

	assertEquals(page.state.closed, ['cancel', 'cancel']);
});
