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

import {assertEquals, assertInstanceOf} from "@std/assert";
import {assertSameElement, createDom}   from "@support/dom.js";
import {UnitShareDialog}                from "@js/core/UnitShareDialog.js";

const LABELS = {
	title: 'Share', close: 'Close', no_users: 'No users with a role', filter: 'Filter users',
	none: 'None', viewer: 'Viewer', editor: 'Editor'
};

const USERS = [
	{UID: 4, username: 'Jane',  roles: ['viewer', 'editor'], role: 'editor'},
	{UID: 5, username: 'jdoe',  roles: ['viewer'],           role: ''},
	{UID: 6, username: 'Admin', roles: ['viewer', 'editor'], role: 'viewer'},
];

/**
 * The answers are set by the test; a save can wait until the test resolves it.
 */
class FakeShareService
{
	loads       = [];
	saves       = [];
	loadAnswer  = {success: true, labels: LABELS, users: USERS};
	saveAnswers = [];
	waitForSave = null;

	load(module, unitId)
	{
		this.loads.push([module, unitId]);
		return Promise.resolve(this.loadAnswer);
	}

	async save(module, unitId, UID, role)
	{
		this.saves.push([module, unitId, UID, role]);
		if (this.waitForSave !== null)
			await this.waitForSave;

		return this.saveAnswers.shift() ?? {success: true};
	}
}

function createShare()
{
	const dom     = createDom('<ul><li id="share">Share</li></ul>');
	const service = new FakeShareService();
	const errors  = [];

	return {
		document: dom.document, service, errors,
		dialog: new UnitShareDialog('playlists', service, {showError: (message) => errors.push(message)}),
		[Symbol.dispose]: () => dom[Symbol.dispose]()
	};
}

function openDialogs(page)
{
	return [...page.document.body.querySelectorAll('dialog.unit-share')];
}

function select(page, UID)
{
	return page.document.getElementById('unit-share-' + UID);
}

function visibleUsers(page)
{
	return [...page.document.querySelectorAll('.unit-share-users li')]
		.filter(item => !item.hidden)
		.map(item => item.querySelector('label').textContent);
}

function filter(page, text)
{
	const input = page.document.querySelector('.unit-share-filter');
	input.value = text;
	input.dispatchEvent(new Event('input'));
}

async function choose(page, UID, role)
{
	select(page, UID).value = role;
	select(page, UID).dispatchEvent(new Event('change'));
	await new Promise(resolve => setTimeout(resolve, 0));
}

Deno.test('the menu entry is removed without the right to share', () =>
{
	using page = createShare();

	page.dialog.attachTo(page.document.getElementById('share'), 7, false);

	assertSameElement(page.document.getElementById('share'), null);
});

Deno.test('the menu entry opens the users of its unit', async () =>
{
	using page = createShare();
	page.dialog.attachTo(page.document.getElementById('share'), 7, true);

	page.document.getElementById('share').dispatchEvent(new Event('click'));
	await new Promise(resolve => setTimeout(resolve, 0));

	assertEquals(page.service.loads, [['playlists', 7]]);
	assertEquals(openDialogs(page).length, 1);
});

Deno.test('every user gets a select with none and the roles he may get', async () =>
{
	using page = createShare();

	await page.dialog.open(7);

	const [dialog] = openDialogs(page);
	assertEquals(dialog.hasAttribute('open'), true);
	assertEquals(dialog.querySelector('header span').textContent, 'Share');
	assertEquals(dialog.querySelector('header button').getAttribute('aria-label'), 'Close');
	assertEquals(dialog.querySelector('.unit-share-filter').placeholder, 'Filter users');

	const users = [...dialog.querySelectorAll('.unit-share-users li')].map(item => {
		const label   = item.querySelector('label');
		const options = [...item.querySelectorAll('option')].map(option => [option.textContent, option.value]);
		return [label.textContent, label.htmlFor, options, item.querySelector('select').value];
	});
	assertEquals(users, [
		['Jane',  'unit-share-4', [['None', ''], ['Viewer', 'viewer'], ['Editor', 'editor']], 'editor'],
		['jdoe',  'unit-share-5', [['None', ''], ['Viewer', 'viewer']],                       ''],
		['Admin', 'unit-share-6', [['None', ''], ['Viewer', 'viewer'], ['Editor', 'editor']], 'viewer'],
	]);
});

Deno.test('without users with a role the dialog says so', async () =>
{
	using page = createShare();
	page.service.loadAnswer = {success: true, labels: LABELS, users: []};

	await page.dialog.open(7);

	const [dialog] = openDialogs(page);
	assertEquals(dialog.querySelector('.unit-share-empty').textContent, 'No users with a role');
	assertSameElement(dialog.querySelector('.unit-share-filter'), null);
	assertSameElement(dialog.querySelector('.unit-share-users'), null);
});

Deno.test('a failed load shows the error and no dialog', async () =>
{
	using page = createShare();
	page.service.loadAnswer = {success: false, error_message: 'Not allowed'};

	await page.dialog.open(7);

	assertEquals(page.errors, ['Not allowed']);
	assertEquals(openDialogs(page), []);
});

Deno.test('opening again replaces the dialog before, closing removes it', async () =>
{
	using page = createShare();

	await page.dialog.open(7);
	await page.dialog.open(8);
	assertEquals(openDialogs(page).length, 1);

	openDialogs(page)[0].querySelector('header button').dispatchEvent(new Event('click'));
	assertEquals(openDialogs(page), []);
});

Deno.test('the filter finds the users by a part of the name, without case and spaces', async () =>
{
	using page = createShare();
	await page.dialog.open(7);

	filter(page, ' J ');
	assertEquals(visibleUsers(page), ['Jane', 'jdoe']);

	filter(page, 'ADM');
	assertEquals(visibleUsers(page), ['Admin']);

	filter(page, 'nobody');
	assertEquals(visibleUsers(page), []);

	filter(page, '  ');
	assertEquals(visibleUsers(page), ['Jane', 'jdoe', 'Admin']);
});

Deno.test('a chosen role is saved at once for the user and the unit', async () =>
{
	using page = createShare();
	await page.dialog.open(8);

	await choose(page, 5, 'viewer');
	await choose(page, 4, '');

	assertEquals(page.service.saves, [['playlists', 8, 5, 'viewer'], ['playlists', 8, 4, '']]);
	assertEquals(select(page, 5).value, 'viewer');
	assertEquals(page.errors, []);
});

Deno.test('the select is locked while it saves', async () =>
{
	using page = createShare();
	const save = Promise.withResolvers();
	page.service.waitForSave = save.promise;
	await page.dialog.open(7);

	await choose(page, 5, 'viewer');
	assertEquals(select(page, 5).disabled, true);

	save.resolve();
	await new Promise(resolve => setTimeout(resolve, 0));
	assertEquals(select(page, 5).disabled, false);
});

Deno.test('a failed save goes back to the role saved last and shows the error', async () =>
{
	using page = createShare();
	page.service.saveAnswers = [{success: true}, {success: false, error_message: 'Not allowed'}];
	await page.dialog.open(7);

	await choose(page, 6, 'editor');
	await choose(page, 6, '');

	assertEquals(select(page, 6).value, 'editor');
	assertEquals(select(page, 6).disabled, false);
	assertEquals(page.errors, ['Not allowed']);
});

Deno.test('create() builds the dialog for a module', () =>
{
	using _dom = createDom('<template id="flashMessageTemplate"><div></div></template>');

	assertInstanceOf(UnitShareDialog.create('templates'), UnitShareDialog);
});
