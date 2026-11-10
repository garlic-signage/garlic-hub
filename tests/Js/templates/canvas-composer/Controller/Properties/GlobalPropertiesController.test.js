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
import {createDom}                  from "@support/dom.js";
import {PROPERTIES_PAGE}            from "@support/ComposerPropertiesFakes.js";
import {GlobalPropertiesView}       from "@js/templates/canvas-composer/Views/Properties/GlobalPropertiesView.js";
import {GlobalPropertiesController} from "@js/templates/canvas-composer/Controller/Properties/GlobalPropertiesController.js";

class FakeGlobalPropertiesService
{
	calls = [];

	getStrokeColor()        { return '#ff0000'; }
	getStrokeWidth()        { return 4; }
	getOpacity()            { return 70; }
	setOpacity(value)       { this.calls.push(['setOpacity', value]); }
	setStrokeColor(value)   { this.calls.push(['setStrokeColor', value]); }
	setStrokeWidth(value)   { this.calls.push(['setStrokeWidth', value]); }
	setPosition(position)   { this.calls.push(['setPosition', position]); }
}

function createController()
{
	const dom     = createDom(PROPERTIES_PAGE);
	const service = new FakeGlobalPropertiesService();
	const view    = new GlobalPropertiesView();
	const controller = new GlobalPropertiesController(view, service);
	return {document: dom.document, view, service, controller, [Symbol.dispose]: () => dom[Symbol.dispose]()};
}

function fire(page, id, type, value = undefined)
{
	const element = page.document.getElementById(id);
	if (value !== undefined)
		element.value = value;
	element.dispatchEvent(new Event(type));
}

Deno.test('the panel shows the values of the selected object', () =>
{
	using page = createController();

	page.controller.activate();

	assertEquals([page.view.getStrokeColorValue(), page.view.getStrokeWidthValue(), page.view.getOpacityValue()], ['#ff0000', '4', '70']);
	assertEquals(page.document.getElementById('opacityDisplay').textContent, '70');
	assertEquals(page.document.getElementById('globalProperties').style.display, 'flex');
});

Deno.test('the panel is hidden', () =>
{
	using page = createController();
	page.controller.activate();

	page.controller.deactivate();

	assertEquals(page.document.getElementById('globalProperties').style.display, 'none');
});

Deno.test('a changed opacity is set as whole number and shown beside the slider', () =>
{
	using page = createController();

	fire(page, 'opacity', 'change', '45.7');

	assertEquals(page.service.calls, [['setOpacity', 45]]);
	assertEquals(page.document.getElementById('opacityDisplay').textContent, '45');
});

Deno.test('the colour of the outline is set while it is chosen and when it is taken', () =>
{
	using page = createController();

	fire(page, 'strokeColor', 'input', '#00ff00');
	fire(page, 'strokeColor', 'change', '#0000ff');

	assertEquals(page.service.calls, [['setStrokeColor', '#00ff00'], ['setStrokeColor', '#0000ff']]);
});

Deno.test('a changed width of the outline is set and shown beside the slider', () =>
{
	using page = createController();

	fire(page, 'strokeWidth', 'change', '12');

	assertEquals(page.service.calls, [['setStrokeWidth', 12]]);
	assertEquals(page.document.getElementById('strokeWidthDisplay').textContent, '12');
});

Deno.test('every button of the alignment moves the object to its place', () =>
{
	using page = createController();
	const buttons = {alignLeft: 'left', alignCenter: 'center', alignRight: 'right', alignTop: 'top', alignMiddle: 'middle', alignBottom: 'bottom'};

	for (const id of Object.keys(buttons))
		fire(page, id, 'click');

	assertEquals(page.service.calls, Object.values(buttons).map(position => ['setPosition', position]));
});
