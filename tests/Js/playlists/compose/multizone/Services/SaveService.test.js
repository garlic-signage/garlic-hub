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
import {createDom}      from "@support/dom.js";
import {replaceGlobals} from "@support/globals.js";
import {LayoutSettings} from "@js/playlists/compose/multizone/Utils/LayoutSettings.js";
import {SaveService}    from "@js/playlists/compose/multizone/Services/SaveService.js";

const CSRF_META = '<meta name="csrf-token" content="the-token">';

/**
 * A zone as the canvas exports it.
 */
function zone(name, left, top, width, height, index)
{
	return {getPropertiesForExport: () => ({
		'zone_name': name, 'zone_left': left, 'zone_top': top, 'zone_width': width, 'zone_height': height,
		'zone_z-index': index, 'zone_bgcolor': '#000000', 'zone_playlist_id': 0
	})};
}

/**
 * The answer of the server as fetch() gives it; the body is no JSON when it is undefined.
 */
function answer(body, status = 200)
{
	return {
		ok: status < 400, status,
		json: async () => { if (body === undefined) throw new SyntaxError('no JSON'); return body; }
	};
}

function createService(response, {unit = 'pixel', zones = [zone('Main', 0, 0, 1440, 1080, 0), zone('Side', 1440, 0, 480, 1080, 1)]} = {})
{
	const dom      = createDom(CSRF_META);
	const requests = [];
	const overlays = [];
	const globals  = replaceGlobals({fetch: async (url, options) =>
	{
		// the overlay waits while the request runs
		overlays.push(dom.document.getElementById('waitOverlay') !== null);
		requests.push([url, options]);
		if (response instanceof Error)
			throw response;
		return response;
	}});
	const settings = new LayoutSettings();
	settings.exportUnit = unit;
	settings.zoom       = 50;

	return {
		document: dom.document, requests, overlays,
		service: new SaveService(settings, {width: 1920, height: 1080}, {zones: () => zones}, 12),
		bodyOf: (index = 0) => JSON.parse(requests[index][1].body),
		[Symbol.dispose]: () => { globals[Symbol.dispose](); dom[Symbol.dispose](); }
	};
}

Deno.test('the layout is posted with resolution, zoom, unit, zones and the CSRF token', async () =>
{
	using page = createService(answer({success: true}));

	assertEquals(await page.service.save(), {success: true});

	const [url, options] = page.requests[0];
	assertEquals([url, options.method], ['/async/playlists/multizone/12', 'POST']);
	const body = page.bodyOf();
	assertEquals([body.resolution, body.zoom, body.export_unit, body.csrf_token], ['1920x1080', 50, 'pixel', 'the-token']);
	assertEquals(body.zones.map(saved => [saved.zone_name, saved.zone_left, saved.zone_width, saved['zone_z-index']]),
		[['Main', 0, 1440, 0], ['Side', 1440, 480, 1]]);
});

Deno.test('in percent the zones are converted by the resolution', async () =>
{
	using page = createService(answer({success: true}), {unit: 'percent'});

	await page.service.save();

	assertEquals(page.bodyOf().zones.map(saved => [saved.zone_left, saved.zone_top, saved.zone_width, saved.zone_height]),
		[[0, 0, 75, 100], [75, 0, 25, 100]]);
	assertEquals(page.bodyOf().export_unit, 'percent');
});

Deno.test('a layout without zones is saved too', async () =>
{
	using page = createService(answer({success: true}), {zones: []});

	await page.service.save();

	assertEquals(page.bodyOf().zones, []);
});

Deno.test('an overlay waits while the layout is saved', async () =>
{
	using page = createService(answer({success: true}));

	await page.service.save();

	assertEquals(page.overlays, [true]);
	assertEquals(page.document.getElementById('waitOverlay'), null);
});

Deno.test('the answer of the server is returned, also a refusal', async () =>
{
	using page = createService(answer({success: false, error_message: 'Not allowed'}, 403));

	assertEquals(await page.service.save(), {success: false, error_message: 'Not allowed'});
});

Deno.test('an answer without JSON is judged by its status', async () =>
{
	{
		using page = createService(answer(undefined, 204));
		assertEquals(await page.service.save(), {success: true});
	}
	{
		using page = createService(answer(undefined, 500));
		assertEquals(await page.service.save(), {success: false, error_message: 'HTTP-Error: 500'});
	}
	{
		using page = createService(answer({saved: 1}, 502));
		assertEquals(await page.service.save(), {success: false, error_message: 'HTTP-Error: 502'});
	}
});

Deno.test('a failed request is returned as error and removes the overlay', async () =>
{
	using page = createService(new TypeError('Failed to fetch'));

	assertEquals(await page.service.save(), {success: false, error_message: 'Failed to fetch'});
	assertEquals(page.document.getElementById('waitOverlay'), null);
});
