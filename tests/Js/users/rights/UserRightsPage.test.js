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
import {assertEquals}                 from "@std/assert";
import {stub}                         from "@std/testing/mock";
import {createDom}                    from "@support/dom.js";
import {replaceGlobals}               from "@support/globals.js";
import {UserRightsPage}               from "@js/users/rights/UserRightsPage.js";

// templates/users/rights.mustache of user 7: the playlists with roles and one unit as editor, the player without units
const UNIT_ROLES = '<option value="">None</option><option value="viewer">Viewer</option><option value="editor">Editor</option>';
const PAGE = `
	<section id="userRights" data-uid="7" data-confirm-remove-role="Remove the role and its units?">
		<section class="tab-content" data-module="playlists">
			<fieldset class="rights-roles">
				<label><input type="checkbox" class="rights-role" value="viewer"> Viewer</label>
				<label><input type="checkbox" class="rights-role" value="editor" checked> Editor</label>
			</fieldset>
			<fieldset class="rights-units">
				<input type="text" id="unit_playlists_search"><input type="hidden" id="unit_playlists">
				<p class="rights-no-units" hidden>No units</p>
				<ul class="rights-unit-list">
					<li data-unit-id="5">
						<span class="rights-unit-name">Lobby</span>
						<select class="rights-unit-role"><option value="">None</option><option value="viewer">Viewer</option><option value="editor" selected>Editor</option></select>
					</li>
				</ul>
				<template class="rights-unit-template">
					<li><span class="rights-unit-name"></span><select class="rights-unit-role">${UNIT_ROLES}</select></li>
				</template>
			</fieldset>
		</section>
		<section class="tab-content" data-module="player">
			<fieldset class="rights-roles">
				<label><input type="checkbox" class="rights-role" value="moduleadmin"> Module admin</label>
			</fieldset>
		</section>
	</section>`;

/**
 * Records the calls and answers with the results set by the test.
 */
class FakeServices
{
	calls  = [];
	role   = {success: true};
	shares = [];

	async setRole(UID, module, role, granted)
	{
		this.calls.push(['setRole', UID, module, role, granted]);
		return this.role;
	}

	async save(module, unitId, UID, role)
	{
		this.calls.push(['save', module, unitId, UID, role]);
		return this.shares.shift() ?? {success: true};
	}
}

/**
 * The autocomplete of the units: the edit field has the name of the chosen unit, the hidden field reports its id.
 */
class FakeAutocomplete
{
	created = [];
	inputs  = null;
	#document;

	constructor(document)
	{
		this.#document = document;
	}

	create(id, url)
	{
		this.created.push([id, url]);
		const autocomplete = this;
		return {
			initWithExistingFields: () => {},
			getHiddenIdElement:     () => this.#document.getElementById(id),
			getEditFieldElement:    () => this.#document.getElementById(id + '_search'),
			setInputFields:         (name, value) => { autocomplete.inputs = [name, value]; }
		};
	}

	choose(module, unitId, name)
	{
		this.#document.getElementById('unit_' + module + '_search').value = name;
		const hidden = this.#document.getElementById('unit_' + module);
		hidden.value = unitId;
		hidden.dispatchEvent(new Event('selectedFromDataList'));
	}
}

function createPage(confirmed = true)
{
	const dom          = createDom(PAGE);
	const reloads      = [];
	const globals      = replaceGlobals({location: {reload: () => reloads.push(true)}});
	const confirmStub  = stub(globalThis, 'confirm', () => confirmed);
	const services     = new FakeServices();
	const autocomplete = new FakeAutocomplete(dom.document);
	const errors       = [];
	new UserRightsPage(dom.document.getElementById('userRights'), services, services, {showError: (message) => errors.push(message)}, autocomplete).init();

	const tab = dom.document.querySelector('[data-module="playlists"]');
	return {
		document: dom.document, services, autocomplete, errors, reloads, confirmStub, tab,
		role:    (value) => tab.querySelector(`.rights-role[value="${value}"]`),
		units:   () => [...tab.querySelectorAll('.rights-unit-list li')].map(item => [item.dataset.unitId, item.querySelector('.rights-unit-name').textContent, item.querySelector('select').value]),
		noUnits: () => tab.querySelector('.rights-no-units').hidden,
		[Symbol.dispose]: () => { confirmStub.restore(); globals[Symbol.dispose](); dom[Symbol.dispose](); }
	};
}

async function flush()
{
	for (let i = 0; i < 5; i++)
		await new Promise(resolve => setTimeout(resolve, 0));
}

async function toggle(checkbox)
{
	checkbox.checked = !checkbox.checked;
	checkbox.dispatchEvent(new Event('change'));
	await flush();
}

async function choose(select, value)
{
	select.value = value;
	select.dispatchEvent(new Event('change'));
	await flush();
}

Deno.test('only a tab with units gets the search of the units of its module', () =>
{
	using page = createPage();

	assertEquals(page.autocomplete.created, [['unit_playlists', '/async/acl/playlists/find/']]);
});

Deno.test('a new role is saved and the page loaded again', async () =>
{
	using page = createPage();

	await toggle(page.role('viewer'));

	assertEquals(page.services.calls, [['setRole', '7', 'playlists', 'viewer', true]]);
	assertEquals(page.reloads.length, 1);
	assertEquals(page.confirmStub.calls.length, 0);
});

Deno.test('a role without units is removed without asking', async () =>
{
	using page = createPage();
	await choose(page.tab.querySelector('select'), 'viewer');
	page.services.calls = [];

	await toggle(page.role('editor'));

	assertEquals(page.confirmStub.calls.length, 0);
	assertEquals(page.services.calls, [['setRole', '7', 'playlists', 'editor', false]]);
});

Deno.test('a role with units is removed only after the question', async () =>
{
	using page = createPage(true);

	await toggle(page.role('editor'));

	assertEquals(page.confirmStub.calls[0].args, ['Remove the role and its units?']);
	assertEquals(page.services.calls, [['setRole', '7', 'playlists', 'editor', false]]);
	assertEquals(page.reloads.length, 1);
});

Deno.test('a role with units stays when the question is answered with no', async () =>
{
	using page = createPage(false);

	await toggle(page.role('editor'));

	assertEquals(page.role('editor').checked, true);
	assertEquals(page.services.calls, []);
	assertEquals(page.reloads, []);
});

Deno.test('a refused role is set back and shows the error', async () =>
{
	using page = createPage();
	page.services.role = {success: false, error_message: 'Not allowed'};

	await toggle(page.role('viewer'));

	assertEquals(page.role('viewer').checked, false);
	assertEquals(page.errors, ['Not allowed']);
	assertEquals(page.reloads, []);
});

Deno.test('a changed role of a unit is saved', async () =>
{
	using page = createPage();
	const select = page.tab.querySelector('select');

	await choose(select, 'viewer');

	assertEquals(page.services.calls, [['save', 'playlists', '5', '7', 'viewer']]);
	assertEquals(select.disabled, false);
	assertEquals(page.units(), [['5', 'Lobby', 'viewer']]);
});

Deno.test('a refused role of a unit goes back to the saved one', async () =>
{
	using page = createPage();
	const select = page.tab.querySelector('select');
	page.services.shares = [{success: true}, {success: false, error_message: 'Not allowed'}];
	await choose(select, 'viewer');

	await choose(select, 'editor');

	assertEquals(select.value, 'viewer');
	assertEquals(page.errors, ['Not allowed']);
});

Deno.test('a unit without role leaves the list, an empty list says so', async () =>
{
	using page = createPage();

	await choose(page.tab.querySelector('select'), '');

	assertEquals(page.units(), []);
	assertEquals(page.noUnits(), false);
});

Deno.test('a chosen unit is added with the first role and saved', async () =>
{
	using page = createPage();

	page.autocomplete.choose('playlists', '9', 'Kantine');
	await flush();

	assertEquals(page.autocomplete.inputs, ['', '']);
	assertEquals(page.services.calls, [['save', 'playlists', '9', '7', 'viewer']]);
	assertEquals(page.units(), [['9', 'Kantine', 'viewer'], ['5', 'Lobby', 'editor']]);
	assertEquals(page.noUnits(), true);
});

Deno.test('the role of an added unit can be changed', async () =>
{
	using page = createPage();
	page.autocomplete.choose('playlists', '9', 'Kantine');
	await flush();

	await choose(page.tab.querySelector('li[data-unit-id="9"] select'), 'editor');

	assertEquals(page.services.calls.at(-1), ['save', 'playlists', '9', '7', 'editor']);
});

Deno.test('a unit in the list is not added twice', async () =>
{
	using page = createPage();

	page.autocomplete.choose('playlists', '5', 'Lobby');
	await flush();

	assertEquals(page.services.calls, []);
	assertEquals(page.units(), [['5', 'Lobby', 'editor']]);
});

Deno.test('a refused unit leaves the list again and shows the error', async () =>
{
	using page = createPage();
	page.services.shares = [{success: false, error_message: 'Not allowed'}];

	page.autocomplete.choose('playlists', '9', 'Kantine');
	await flush();

	assertEquals(page.units(), [['5', 'Lobby', 'editor']]);
	assertEquals(page.errors, ['Not allowed']);
});
