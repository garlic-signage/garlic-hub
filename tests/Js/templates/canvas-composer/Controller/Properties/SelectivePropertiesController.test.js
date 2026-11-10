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

import {assertEquals}                  from "@std/assert";
import {createDom}                     from "@support/dom.js";
import {PROPERTIES_PAGE}               from "@support/ComposerPropertiesFakes.js";
import {SelectivePropertiesView}       from "@js/templates/canvas-composer/Views/Properties/SelectivePropertiesView.js";
import {SelectivePropertiesController} from "@js/templates/canvas-composer/Controller/Properties/SelectivePropertiesController.js";

class FakeSelectivePropertiesService
{
	calls = [];

	getFillColor()       { return '#abcdef'; }
	setFillColor(color)  { this.calls.push(color); }
}

function createController()
{
	const dom     = createDom(PROPERTIES_PAGE);
	const service = new FakeSelectivePropertiesService();
	const controller = new SelectivePropertiesController(new SelectivePropertiesView(), service);
	return {field: dom.document.getElementById('fillColor'), service, controller, [Symbol.dispose]: () => dom[Symbol.dispose]()};
}

Deno.test('the fill colour of the object is shown', () =>
{
	using page = createController();

	page.controller.activate();

	assertEquals(page.field.value, '#abcdef');
	assertEquals(page.field.style.display, 'block');
});

Deno.test('the fill colour is hidden, also with all panels', () =>
{
	using page = createController();

	page.controller.activate();
	page.controller.deactivate();
	assertEquals(page.field.style.display, 'none');

	page.controller.activate();
	page.controller.deactivateAll();
	assertEquals(page.field.style.display, 'none');
});

Deno.test('the colour is set while it is chosen and when it is taken', () =>
{
	using page = createController();

	page.field.value = '#111111';
	page.field.dispatchEvent(new Event('input'));
	page.field.value = '#222222';
	page.field.dispatchEvent(new Event('change'));

	assertEquals(page.service.calls, ['#111111', '#222222']);
});
