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

import {assertEquals} from "@std/assert";
import {createDom}    from "@support/dom.js";
import {TriggerForm}  from "@js/playlists/compose/standard/editors/TriggerForm.js";

// the containers and buttons of the begin trigger editor the server sends
const PAGE = `
	<button id="addWallclock"></button><div id="wallclocksContainer"></div>
	<button id="addAccesskey"></button><div id="accesskeysContainer"></div>
	<button id="addTouch"></button><div id="touchesContainer"></div>
	<button id="addNotify"></button><div id="notifiesContainer"></div>`;

const KINDS = {
	wallclock: {button: 'addWallclock', container: 'wallclocksContainer', add: 'addWallclockEditor'},
	accesskey: {button: 'addAccesskey', container: 'accesskeysContainer', add: 'addAccesskeyEditor'},
	touch:     {button: 'addTouch',     container: 'touchesContainer',    add: 'addTouchEditor'},
	notify:    {button: 'addNotify',    container: 'notifiesContainer',   add: 'addNotifyEditor'},
};

/**
 * The TypeFactory of types/: an editor knows its kind.
 */
class FakeTypeFactory
{
	created = [];

	create(kind)
	{
		this.created.push(kind);
		return {kind};
	}
}

/**
 * The EditorsManager of types/: every added editor becomes an element, the values are set by the test.
 */
class FakeEditorsManager
{
	triggerData        = null;
	touchableMedialist = null;
	added              = [];
	values             = {wallclocks: [], accesskeys: [], touches: [], notifies: []};
	#document;

	constructor(document)
	{
		this.#document = document;
	}

	addWallclockEditor(editor) { return this.#add('addWallclockEditor', editor); }
	addAccesskeyEditor(editor) { return this.#add('addAccesskeyEditor', editor); }
	addTouchEditor(editor)     { return this.#add('addTouchEditor', editor); }
	addNotifyEditor(editor)    { return this.#add('addNotifyEditor', editor); }

	collectWallclockValues() { return this.values.wallclocks; }
	collectAccesskeyValues() { return this.values.accesskeys; }
	collectTouchValues()     { return this.values.touches; }
	collectNotifyValues()    { return this.values.notifies; }

	#add(method, editor)
	{
		this.added.push([method, editor.kind]);
		const element = this.#document.createElement('div');
		element.className = 'editor-' + editor.kind;
		return element;
	}
}

function createPage()
{
	const dom      = createDom(PAGE);
	const document = dom.document;
	const types    = new FakeTypeFactory();
	const manager  = new FakeEditorsManager(document);

	return {
		document, types, manager,
		form:     new TriggerForm(types, manager),
		editors:  (container) => document.getElementById(container).querySelectorAll('div').length,
		[Symbol.dispose]: () => dom[Symbol.dispose]()
	};
}

Deno.test('the manager gets the triggers and the media to touch', () =>
{
	using page = createPage();
	const triggers = {wallclocks: []};

	page.form.init(triggers, {7: 'Button'});

	assertEquals(page.manager.triggerData === triggers, true);
	assertEquals(page.manager.touchableMedialist, {7: 'Button'});
});

Deno.test('every saved trigger gets its editor in its container', () =>
{
	using page = createPage();

	page.form.init({wallclocks: [{}, {}], accesskeys: [{}], touches: [{}], notifies: [{}, {}, {}]}, {});

	assertEquals(page.types.created, ['wallclock', 'wallclock', 'accesskey', 'touch', 'notify', 'notify', 'notify']);
	assertEquals(Object.values(KINDS).map(kind => page.editors(kind.container)), [2, 1, 1, 3]);
});

Deno.test('without saved triggers there are no editors', () =>
{
	using page = createPage();

	page.form.init({}, {});

	assertEquals(page.types.created, []);
	assertEquals(page.manager.added, []);
});

Deno.test('every add button adds an editor of its kind to its container', () =>
{
	using page = createPage();
	page.form.init({}, {});

	for (const [kind, {button, container, add}] of Object.entries(KINDS))
	{
		page.document.getElementById(button).dispatchEvent(new Event('click'));

		assertEquals(page.manager.added.at(-1), [add, kind], kind);
		assertEquals(page.editors(container), 1, kind);
		assertEquals(page.document.getElementById(container).firstElementChild.className, 'editor-' + kind, kind);
	}
});

Deno.test('only the kinds with values are saved', () =>
{
	using page = createPage();
	page.form.init({}, {});
	page.manager.values.wallclocks = [{iso_date: '2026-01-01T08:00'}];
	page.manager.values.notifies   = {0: {notify: 'open'}};

	assertEquals(page.form.collectValues(), {wallclocks: [{iso_date: '2026-01-01T08:00'}], notifies: {0: {notify: 'open'}}});
});

Deno.test('without any trigger nothing is saved', () =>
{
	using page = createPage();
	page.form.init({}, {});

	assertEquals(page.form.collectValues(), {});
});
