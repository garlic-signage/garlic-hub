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

import {assertEquals}              from "@std/assert";
import {createDom}                 from "@support/dom.js";
import {FakeActiveSelection, FakeGroup, FakeObject, FakeToggleButtonFactory, PROPERTIES_PAGE, useFakeFabric}
	from "@support/ComposerPropertiesFakes.js";
import {GroupPropertiesView}       from "@js/templates/canvas-composer/Views/Properties/GroupPropertiesView.js";
import {GroupPropertiesController} from "@js/templates/canvas-composer/Controller/Properties/GroupPropertiesController.js";

class FakeGroupPropertiesService
{
	active = null;
	calls  = [];

	_getActiveObject()          { return this.active; }
	toGroup(selection)          { this.calls.push(['toGroup', selection]); }
	toActiveSelection(group)    { this.calls.push(['toActiveSelection', group]); }
}

function createController()
{
	const dom     = createDom(PROPERTIES_PAGE);
	const fabric  = useFakeFabric();
	const factory = new FakeToggleButtonFactory();
	const service = new FakeGroupPropertiesService();
	const controller = new GroupPropertiesController(new GroupPropertiesView(factory), service);
	return {
		document: dom.document, service, controller, button: factory.buttons.get('object_group'),
		[Symbol.dispose]: () => { fabric[Symbol.dispose](); dom[Symbol.dispose](); }
	};
}

function click(page)
{
	page.document.getElementById('object_group').dispatchEvent(new Event('click'));
}

Deno.test('a selection of several objects offers to group them, a group to ungroup it', () =>
{
	using page = createController();

	page.controller.activate(new FakeActiveSelection());
	page.controller.activate(new FakeGroup());

	assertEquals(page.button.updates, [['display', 'group'], ['display', 'ungroup']]);
	assertEquals(page.button.shown, true);
});

Deno.test('the button is hidden', () =>
{
	using page = createController();
	page.controller.activate(new FakeGroup());

	page.controller.deactivate();

	assertEquals(page.button.shown, false);
});

Deno.test('a click groups the selected objects and offers to ungroup them', () =>
{
	using page  = createController();
	const selection = new FakeActiveSelection();
	page.service.active = selection;

	click(page);

	assertEquals(page.service.calls.length, 1);
	assertEquals(page.service.calls[0][0], 'toGroup');
	assertEquals(page.service.calls[0][1] === selection, true);
	assertEquals(page.button.updates, [['display', 'ungroup']]);
});

Deno.test('a click ungroups a group and offers to group it again', () =>
{
	using page = createController();
	const group = new FakeGroup();
	page.service.active = group;

	click(page);

	assertEquals(page.service.calls.map(call => call[0]), ['toActiveSelection']);
	assertEquals(page.service.calls[0][1] === group, true);
	assertEquals(page.button.updates, [['display', 'group']]);
});

Deno.test('a click with another object does nothing', () =>
{
	using page = createController();
	page.service.active = new FakeObject({type: 'rect'});

	click(page);

	assertEquals(page.service.calls, []);
	assertEquals(page.button.updates, []);
});
