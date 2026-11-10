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

import {assertEquals}         from "@std/assert";
import {recorder}             from "@support/ComposerControllerFakes.js";
import {busOfTest}            from "@support/MultizoneEvents.js";
import {PropertiesController} from "@js/templates/canvas-composer/Controller/PropertiesController.js";

function createController()
{
	const make   = () => recorder(['activate', 'deactivate', 'deactivateAll']);
	const parts  = {global: make(), group: make(), selective: make(), text: make(), shadow: make()};
	const controller = new PropertiesController(parts.global, parts.group, parts.selective, parts.text, parts.shadow);

	return {
		controller, parts,
		// which part was activated, the deactivations are left out
		activated: () => Object.entries(parts).filter(([, part]) => part.calls.some(call => call[0] === 'activate')).map(([name]) => name),
		reset: () => Object.values(parts).forEach(part => part.calls.length = 0)
	};
}

Deno.test('the properties fit the kind of the clicked object', () =>
{
	using bus = busOfTest();
	const cases = [
		['textbox', ['global', 'selective', 'text', 'shadow']], ['i-text', ['global', 'selective', 'text', 'shadow']],
		['text', ['global', 'selective', 'text', 'shadow']],
		['rect', ['global', 'selective', 'shadow']], ['circle', ['global', 'selective', 'shadow']],
		['triangle', ['global', 'selective', 'shadow']], ['polygon', ['global', 'selective', 'shadow']],
		['image', ['global', 'shadow']], ['group', ['group']], ['activeselection', ['group']], ['path', []],
	];

	for (const [type, expected] of cases)
	{
		const page = createController();
		bus.send('mouseLeftUp', {target: {type}});
		assertEquals(page.activated(), expected, type);
	}
});

Deno.test('the group gets its object', () =>
{
	using bus  = busOfTest();
	const page = createController();
	const group = {type: 'group'};

	bus.send('mouseLeftUp', {target: group});

	assertEquals(page.parts.group.calls.find(call => call[0] === 'activate'), ['activate', group]);
});

Deno.test('every click closes the properties before, a click beside the objects only closes them', () =>
{
	using bus  = busOfTest();
	const page = createController();

	bus.send('mouseLeftUp', {target: null});

	assertEquals(page.parts.global.calls, [['deactivate']]);
	assertEquals(page.parts.group.calls, [['deactivate']]);
	assertEquals(page.parts.selective.calls, [['deactivateAll']]);
	assertEquals(page.parts.text.calls, [['deactivate']]);
	assertEquals(page.activated(), []);
});

Deno.test('an object selected without the mouse shows its properties too', () =>
{
	using bus  = busOfTest();
	const page = createController();

	bus.send('objectSelected', {target: {type: 'rect'}});

	assertEquals(page.activated(), ['global', 'selective', 'shadow']);
});

Deno.test('the properties before are closed only when an object was shown', () =>
{
	using _bus = busOfTest();
	const page = createController();

	page.controller.deactivatePrevious();
	assertEquals(page.parts.global.calls, []);

	page.controller.activateCurrent({type: 'image'});
	page.reset();
	page.controller.deactivatePrevious();
	assertEquals(page.parts.global.calls, [['deactivate']]);
});
