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
import {ShadowPropertiesView}       from "@js/templates/canvas-composer/Views/Properties/ShadowPropertiesView.js";
import {ShadowPropertiesController} from "@js/templates/canvas-composer/Controller/Properties/ShadowPropertiesController.js";

/**
 * A shadow of the selected object, null without one.
 */
class FakeShadowPropertiesService
{
	shadow = null;
	calls  = [];

	create()          { this.calls.push(['create']); this.shadow = {color: '#aaaaaa', offsetX: 20, offsetY: 20, blur: 10}; }
	delete()          { this.calls.push(['delete']); this.shadow = null; }
	hasShadow()       { return this.shadow !== null; }
	getColor()        { return this.shadow?.color || 'rgba(0, 0, 0, 0)'; }
	getOffsetX()      { return this.shadow?.offsetX || 0; }
	getOffsetY()      { return this.shadow?.offsetY || 0; }
	getBlur()         { return this.shadow?.blur || 0; }
	setColor(value)   { this.calls.push(['setColor', value]); }
	setOffsetX(value) { this.calls.push(['setOffsetX', value]); }
	setOffsetY(value) { this.calls.push(['setOffsetY', value]); }
	setBlur(value)    { this.calls.push(['setBlur', value]); }
}

function createController()
{
	const dom     = createDom(PROPERTIES_PAGE);
	const service = new FakeShadowPropertiesService();
	const view    = new ShadowPropertiesView();
	const controller = new ShadowPropertiesController(view, service);
	return {document: dom.document, view, service, controller, [Symbol.dispose]: () => dom[Symbol.dispose]()};
}

function values(page)
{
	return [page.view.getShadowOffsetXValue(), page.view.getShadowOffsetYValue(), page.view.getShadowBlurValue()];
}

Deno.test('an object with a shadow shows its values and can delete it', () =>
{
	using page = createController();
	page.service.shadow = {color: '#123456', offsetX: 3, offsetY: 4, blur: 5};

	page.controller.activate();

	assertEquals(values(page), ['3', '4', '5']);
	assertEquals(page.view.getShadowColorValue(), '#123456');
	assertEquals([page.view.deleteShadow.style.display, page.view.shadowBlur.disabled], ['block', false]);
});

Deno.test('an object without a shadow can only create one', () =>
{
	using page = createController();

	page.controller.activate();

	assertEquals(values(page), ['0', '0', '0']);
	assertEquals([page.view.createShadow.style.display, page.view.shadowBlur.disabled], ['block', true]);
});

Deno.test('a created shadow is shown with its values', () =>
{
	using page = createController();
	page.controller.activate();

	page.view.createShadow.dispatchEvent(new Event('click'));

	assertEquals(page.service.calls, [['create']]);
	assertEquals(values(page), ['20', '20', '10']);
	assertEquals(page.view.deleteShadow.style.display, 'block');
});

Deno.test('a deleted shadow leaves the fields locked', () =>
{
	using page = createController();
	page.service.shadow = {color: '#123456', offsetX: 3, offsetY: 4, blur: 5};
	page.controller.activate();

	page.view.deleteShadow.dispatchEvent(new Event('click'));

	assertEquals(page.service.calls, [['delete']]);
	assertEquals([page.view.createShadow.style.display, page.view.shadowColor.disabled], ['block', true]);
});

Deno.test('every field of the shadow sets its value while it is changed', () =>
{
	using page = createController();
	const fields = {shadowColor: ['setColor', '#654321'], shadowOffsetX: ['setOffsetX', '7'], shadowOffsetY: ['setOffsetY', '8'], shadowBlur: ['setBlur', '9']};

	for (const [id, [, value]] of Object.entries(fields))
	{
		const field = page.document.getElementById(id);
		field.value = value;
		field.dispatchEvent(new Event('input'));
	}

	assertEquals(page.service.calls, Object.values(fields));
});
