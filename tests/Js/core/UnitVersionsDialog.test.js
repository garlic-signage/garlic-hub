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
import {stub}                           from "@std/testing/mock";
import {assertSameElement, createDom}   from "@support/dom.js";
import {replaceGlobals}                 from "@support/globals.js";
import {UnitVersionsDialog}             from "@js/core/UnitVersionsDialog.js";

const LABELS = {
	title: 'Versions', close: 'Close', empty: 'No versions yet', restore: 'Restore', restore_confirm: 'Restore this version?'
};

const VERSIONS = [
	{version: 3, created_at: '2026-11-10 09:30', username: 'admin'},
	{version: 2, created_at: '2026-11-09 17:00', username: 'jdoe'},
];

/**
 * The answers are set by the test, the restore waits until the test resolves it.
 */
class FakeVersionsService
{
	loads       = [];
	restores    = [];
	loadAnswer  = {success: true, labels: LABELS, versions: VERSIONS};
	restoreAnswer = {success: true, redirect: '/playlists/compose/7'};

	load(module, unitId)
	{
		this.loads.push([module, unitId]);
		return Promise.resolve(this.loadAnswer);
	}

	restore(module, unitId, version)
	{
		this.restores.push([module, unitId, version]);
		return Promise.resolve(this.restoreAnswer);
	}
}

function createVersions()
{
	const dom      = createDom('<ul><li id="versions">Versions</li></ul>');
	const location = {href: '/playlists'};
	const globals  = replaceGlobals({window: {location: location}});
	const service  = new FakeVersionsService();
	const errors   = [];

	return {
		document: dom.document, location, service, errors,
		dialog: new UnitVersionsDialog('playlists', service, {showError: (message) => errors.push(message)}),
		[Symbol.dispose]: () => { globals[Symbol.dispose](); dom[Symbol.dispose](); }
	};
}

function openDialogs(page)
{
	return [...page.document.body.querySelectorAll('dialog.unit-versions')];
}

function versionRows(page)
{
	return [...page.document.querySelectorAll('.unit-versions-list li')].map(row => [
		row.querySelector('span').textContent, row.querySelector('button').textContent
	]);
}

function restoreButton(page, index)
{
	return page.document.querySelectorAll('.unit-versions-list button')[index];
}

/**
 * The click handler is async: it is done when its promises are settled.
 */
async function click(element)
{
	element.dispatchEvent(new Event('click'));
	await new Promise(resolve => setTimeout(resolve, 0));
}

Deno.test('the menu entry is removed while the versioning is off', () =>
{
	using page = createVersions();

	page.dialog.attachTo(page.document.getElementById('versions'), 7, false);

	assertSameElement(page.document.getElementById('versions'), null);
});

Deno.test('the menu entry opens the versions of its unit', async () =>
{
	using page = createVersions();
	page.dialog.attachTo(page.document.getElementById('versions'), 7, true);

	await click(page.document.getElementById('versions'));

	assertEquals(page.service.loads, [['playlists', 7]]);
	assertEquals(openDialogs(page).length, 1);
});

Deno.test('the dialog lists the versions with a restore button each', async () =>
{
	using page = createVersions();

	await page.dialog.open(7);

	const [dialog] = openDialogs(page);
	assertEquals(dialog.hasAttribute('open'), true);
	assertEquals(dialog.querySelector('header span').textContent, 'Versions');
	assertEquals(dialog.querySelector('header button').getAttribute('aria-label'), 'Close');
	assertEquals(versionRows(page), [['3 · 2026-11-10 09:30 · admin', 'Restore'], ['2 · 2026-11-09 17:00 · jdoe', 'Restore']]);
	assertSameElement(dialog.querySelector('.unit-versions-empty'), null);
});

Deno.test('without versions the dialog says so', async () =>
{
	using page = createVersions();
	page.service.loadAnswer = {success: true, labels: LABELS, versions: []};

	await page.dialog.open(7);

	const [dialog] = openDialogs(page);
	assertEquals(dialog.querySelector('.unit-versions-empty').textContent, 'No versions yet');
	assertSameElement(dialog.querySelector('.unit-versions-list'), null);
});

Deno.test('a failed load shows the error and no dialog', async () =>
{
	using page = createVersions();
	page.service.loadAnswer = {success: false, error_message: 'Not allowed'};

	await page.dialog.open(7);

	assertEquals(page.errors, ['Not allowed']);
	assertEquals(openDialogs(page), []);
});

Deno.test('opening again replaces the dialog before', async () =>
{
	using page = createVersions();

	await page.dialog.open(7);
	await page.dialog.open(8);

	assertEquals(openDialogs(page).length, 1);
	assertEquals(page.service.loads, [['playlists', 7], ['playlists', 8]]);
});

Deno.test('closing removes the dialog from the page', async () =>
{
	using page = createVersions();
	await page.dialog.open(7);

	await click(openDialogs(page)[0].querySelector('header button'));

	assertEquals(openDialogs(page), []);
});

Deno.test('a restore asks first and does nothing without consent', async () =>
{
	using page        = createVersions();
	using confirmStub = stub(globalThis, 'confirm', () => false);
	await page.dialog.open(7);

	await click(restoreButton(page, 1));

	assertEquals(confirmStub.calls[0].args, ['Restore this version?']);
	assertEquals(page.service.restores, []);
	assertEquals(restoreButton(page, 1).disabled, false);
});

Deno.test('a restored version leads where the server says', async () =>
{
	using page         = createVersions();
	using _confirmStub = stub(globalThis, 'confirm', () => true);
	await page.dialog.open(8);

	await click(restoreButton(page, 1));

	assertEquals(page.service.restores, [['playlists', 8, 2]]);
	assertEquals(page.location.href, '/playlists/compose/7');
	assertEquals(restoreButton(page, 1).disabled, true);
});

Deno.test('a failed restore shows the error and can be tried again', async () =>
{
	using page         = createVersions();
	using _confirmStub = stub(globalThis, 'confirm', () => true);
	page.service.restoreAnswer = {success: false, error_message: 'Version is gone'};
	await page.dialog.open(7);

	await click(restoreButton(page, 0));

	assertEquals(page.errors, ['Version is gone']);
	assertEquals(page.location.href, '/playlists');
	assertEquals(restoreButton(page, 0).disabled, false);
});

Deno.test('create() builds the dialog for a module', () =>
{
	using _dom = createDom('<template id="flashMessageTemplate"><div></div></template>');

	assertInstanceOf(UnitVersionsDialog.create('templates'), UnitVersionsDialog);
});
