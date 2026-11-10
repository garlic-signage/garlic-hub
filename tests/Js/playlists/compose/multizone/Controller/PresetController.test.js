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

import {assertEquals}               from "@std/assert";
import {replaceGlobals}             from "@support/globals.js";
import {MultizoneFakeFabricWrapper} from "@support/MultizoneFakeFabricWrapper.js";
import {PresetController}           from "@js/playlists/compose/multizone/Controller/PresetController.js";

const PRESETS = [{name: 'Full', zones: [{left: 0, top: 0, width: 100, height: 100, bgcolor: '#000000'}]}];

class FakePresetView
{
	openButton = new EventTarget();
	opened     = [];
	closed     = 0;

	open(presets, width, height, labels, onSelect)
	{
		this.opened.push({presets, width, height, labels});
		this.select = onSelect;
	}

	close()
	{
		this.closed++;
	}
}

function createController({hasZones = false, confirmed = true} = {})
{
	const view    = new FakePresetView();
	const canvas  = new MultizoneFakeFabricWrapper();
	const applied = [];
	const asked   = [];
	let deactivated = 0;
	const globals = replaceGlobals({
		lang: {presets: 'Presets', close: 'Close', confirm_preset: 'Replace the zones?'},
		confirm: (question) => { asked.push(question); return confirmed; }
	});
	const presetService = {hasZones: () => hasZones, apply: (preset) => applied.push(preset)};
	new PresetController(view, presetService, {width: '1080', height: '1920'}, {deactivate: () => deactivated++}, canvas, PRESETS);

	return {
		view, canvas, applied, asked, deactivated: () => deactivated,
		[Symbol.dispose]: () => globals[Symbol.dispose]()
	};
}

Deno.test('the button opens the presets for the size of the screen', () =>
{
	using page = createController();

	page.view.openButton.dispatchEvent(new Event('click'));

	assertEquals(page.view.opened.length, 1);
	const {presets, width, height, labels} = page.view.opened[0];
	assertEquals(presets === PRESETS, true);
	assertEquals([width, height, labels], [1080, 1920, {title: 'Presets', close: 'Close'}]);
});

Deno.test('a preset on an empty canvas is put on it at once, as a change', () =>
{
	using page = createController();
	page.view.openButton.dispatchEvent(new Event('click'));

	page.view.select(PRESETS[0]);

	assertEquals(page.asked, []);
	assertEquals(page.view.closed, 1);
	assertEquals(page.applied, [PRESETS[0]]);
	assertEquals(page.deactivated(), 1);
	assertEquals([page.canvas.renders, page.canvas.hasChanged], [1, true]);
});

Deno.test('existing zones are replaced only after a question', () =>
{
	{
		using page = createController({hasZones: true, confirmed: true});
		page.view.openButton.dispatchEvent(new Event('click'));

		page.view.select(PRESETS[0]);

		assertEquals(page.asked, ['Replace the zones?']);
		assertEquals(page.applied.length, 1);
	}
	{
		using page = createController({hasZones: true, confirmed: false});
		page.view.openButton.dispatchEvent(new Event('click'));

		page.view.select(PRESETS[0]);

		assertEquals(page.applied, []);
		assertEquals([page.view.closed, page.canvas.hasChanged], [0, false]);
	}
});
