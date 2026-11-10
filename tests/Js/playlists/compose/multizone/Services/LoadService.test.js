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
import {stub}           from "@std/testing/mock";
import {replaceGlobals} from "@support/globals.js";
import {LayoutSettings} from "@js/playlists/compose/multizone/Utils/LayoutSettings.js";
import {LoadService}    from "@js/playlists/compose/multizone/Services/LoadService.js";

class FakeViewportService
{
	calls = [];

	setDimensions(width, height) { this.calls.push(['setDimensions', width, height]); }
	scaleCanvas(zoom)            { this.calls.push(['scaleCanvas', zoom]); }
}

class FakeZoneService
{
	created = [];

	create(properties)
	{
		this.created.push({...properties});
	}
}

class FakePlaylistsService
{
	asked = [];

	async findNameById(playlistId)
	{
		this.asked.push(playlistId);
		return {5: 'Morning', 7: 'Evening'}[playlistId] ?? '';
	}
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

function zone(name, left, top, width, height, index = 0, options = {})
{
	return {zone_name: name, zone_left: left, zone_top: top, zone_width: width, zone_height: height, 'zone_z-index': index, ...options};
}

function createService(response)
{
	const requests = [];
	const globals  = replaceGlobals({fetch: async (url, options) => { requests.push([url, options]); return response; }});
	const settings = new LayoutSettings();
	const viewport = new FakeViewportService();
	const zones    = new FakeZoneService();
	const lists    = new FakePlaylistsService();

	return {
		requests, settings, viewport, zones, lists,
		service: new LoadService(settings, viewport, zones, lists),
		[Symbol.dispose]: () => globals[Symbol.dispose]()
	};
}

Deno.test('the layout of the playlist is asked from the server', async () =>
{
	using page = createService(answer({zones: null}));

	await page.service.load(12);

	assertEquals(page.requests[0][0], '/async/playlists/multizone/12');
	assertEquals(page.requests[0][1].method, 'GET');
});

Deno.test('a new playlist has a screen of full HD and no zones', async () =>
{
	for (const zones of [null, []])
	{
		using page = createService(answer({zones}));

		await page.service.load(12);

		assertEquals(page.viewport.calls, [['setDimensions', 1920, 1080], ['scaleCanvas', 70]], String(zones));
		assertEquals(page.zones.created, []);
	}
});

Deno.test('a layout has its resolution, zoom and unit, the zones in the order of their layers', async () =>
{
	using page = createService(answer({zones: {resolution: '1280x720', zoom: 50, export_unit: 'pixel', zones: [
		zone('Top', 0, 0, 1280, 100, 2), zone('Back', 0, 0, 1280, 720, 0), zone('Side', 0, 100, 300, 620, 1),
	]}}));

	await page.service.load(12);

	assertEquals(page.viewport.calls, [['setDimensions', '1280', '720'], ['scaleCanvas', 50]]);
	assertEquals([page.settings.zoom, page.settings.exportUnit], [50, 'pixel']);
	assertEquals(page.zones.created.map(created => created.zone_name), ['Back', 'Side', 'Top']);
	assertEquals(page.zones.created[1], {...zone('Side', 0, 100, 300, 620, 1), zone_playlist_name: ''});
});

Deno.test('a layout in percent is shown in pixel of its resolution', async () =>
{
	using page = createService(answer({zones: {resolution: '1920x1080', export_unit: 'percent', zones: [
		zone('Left', 0, 0, 25, 50), zone('Right', 25, 50, 75, 50, 1),
	]}}));

	await page.service.load(12);

	assertEquals(page.settings.exportUnit, 'percent');
	assertEquals(page.zones.created.map(created => [created.zone_left, created.zone_top, created.zone_width, created.zone_height]),
		[[0, 0, 480, 540], [480, 540, 1440, 540]]);
});

Deno.test('a layout without unit, zoom or zones keeps the settings', async () =>
{
	using page = createService(answer({zones: {resolution: '1080x1920'}}));

	await page.service.load(12);

	assertEquals([page.settings.exportUnit, page.settings.zoom], ['pixel', 70]);
	assertEquals(page.viewport.calls, [['setDimensions', '1080', '1920'], ['scaleCanvas', 70]]);
	assertEquals(page.zones.created, []);
});

Deno.test('the zones get the names of their playlists', async () =>
{
	using page = createService(answer({zones: {resolution: '1920x1080', zones: [
		zone('A', 0, 0, 10, 10, 0, {zone_playlist_id: 5}), zone('B', 0, 0, 10, 10, 1, {zone_playlist_id: 0}),
		zone('C', 0, 0, 10, 10, 2, {zone_playlist_id: 9}),
	]}}));

	await page.service.load(12);

	assertEquals(page.zones.created.map(created => created.zone_playlist_name), ['Morning', '', '']);
	assertEquals(page.lists.asked, [5, 9]);
});

Deno.test('the playlist of SmilControl becomes the playlist of the zone', async () =>
{
	using page = createService(answer({zones: {resolution: '1920x1080', zones: [zone('A', 0, 0, 10, 10, 0, {zone_smil_playlist_id: 7})]}}));

	await page.service.load(12);

	const [created] = page.zones.created;
	assertEquals([created.zone_playlist_id, created.zone_playlist_name], [7, 'Evening']);
	assertEquals('zone_smil_playlist_id' in created, false);
});

Deno.test('the old format in pixel takes the screen from the borders of the zones', async () =>
{
	using page = createService(answer({zones: [
		zone('Main', '0', '0', '1280', '720', 0), zone('Ticker', '0', '720', '1280', '80', 1),
	]}));

	await page.service.load(12);

	// 1280 x 800: the zoom fits 1280 x 720 in steps of 5
	assertEquals(page.viewport.calls, [['setDimensions', 1280, 800], ['scaleCanvas', 90]]);
	assertEquals(page.settings.exportUnit, 'pixel');
	assertEquals(page.zones.created.map(created => created.zone_name), ['Main', 'Ticker']);
});

Deno.test('the old format may be an object of zones', async () =>
{
	using page = createService(answer({zones: {a: zone('B', 0, 0, 640, 360, 1), b: zone('A', 0, 0, 1920, 1080, 0)}}));

	await page.service.load(12);

	assertEquals(page.zones.created.map(created => created.zone_name), ['A', 'B']);
	assertEquals(page.viewport.calls[0], ['setDimensions', 1920, 1080]);
});

Deno.test('the old format in percent is shown on a screen of full HD', async () =>
{
	using page = createService(answer({export_unit: '1', zones: [zone('Half', 0, 0, 50, 100)]}));

	await page.service.load(12);

	assertEquals(page.settings.exportUnit, 'percent');
	assertEquals(page.viewport.calls, [['setDimensions', 1920, 1080], ['scaleCanvas', 65]]);
	const [created] = page.zones.created;
	assertEquals([created.zone_width, created.zone_height], [960, 1080]);
});

Deno.test('an error of the server is shown and the editor starts empty', async () =>
{
	const answers = [
		[answer({success: false, error_message: 'Not allowed'}, 403), 'Not allowed'],
		[answer(undefined, 500), 'HTTP-Fehler! Status: 500'],
		[answer({error: 'No multizone playlist'}), 'No multizone playlist'],
	];

	for (const [response, message] of answers)
	{
		using page      = createService(response);
		using alertStub = stub(globalThis, 'alert');

		await page.service.load(12);

		assertEquals(alertStub.calls[0].args, [message]);
		assertEquals(page.viewport.calls, [['setDimensions', 1920, 1080], ['scaleCanvas', 70]]);
		assertEquals(page.zones.created, []);
	}
});

Deno.test('a failed request is shown and the editor starts empty', async () =>
{
	using _globals  = replaceGlobals({fetch: async () => { throw new TypeError('Failed to fetch'); }});
	using alertStub = stub(globalThis, 'alert');
	const zones     = new FakeZoneService();

	await new LoadService(new LayoutSettings(), new FakeViewportService(), zones, new FakePlaylistsService()).load(12);

	assertEquals(alertStub.calls[0].args, ['Failed to fetch']);
	assertEquals(zones.created, []);
});
