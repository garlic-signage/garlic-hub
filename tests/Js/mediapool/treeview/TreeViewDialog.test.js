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

import {assertEquals, assertThrows} from "@std/assert";
import {createDom}                  from "@support/dom.js";
import {TreeViewDialog}             from "@js/mediapool/treeview/TreeViewDialog.js";

// the dialog of templates/mediapool/overview.mustache
const PAGE = `
	<dialog id="editFolderDialog">
		<form method="dialog">
			<header>
				<span class="dialog-name"></span>
				<button id="closeEditDialog" class="close-button bi bi-x" aria-label="Close"></button>
			</header>
			<fieldset>
				<input type="text" id="folderName" name="folderName" required>
				<footer>
					<button id="save" value="submit" type="submit" class="primary">Save</button>
					<button id="cancel" value="cancel" formnovalidate autofocus>Cancel</button>
				</footer>
			</fieldset>
		</form>
	</dialog>`;

const LANG = {add_root_folder: 'New root folder', add_sub_folder: 'New sub folder', edit_folder: 'Edit folder'};

const ALL_RIGHTS = {create: true, edit: true, delete: true};

/**
 * Answers like the backend or fails with the given Error, as the TreeViewService does for a failed request.
 */
class FakeTreeViewService
{
	calls  = [];
	answer = {success: true, data: {id: 42, new_name: 'Lobby (2)'}};

	addNode(nodeId, name)
	{
		this.calls.push(['addNode', nodeId, name]);
		return this.#answer();
	}

	editNode(nodeId, name)
	{
		this.calls.push(['editNode', nodeId, name]);
		return this.#answer();
	}

	#answer()
	{
		return this.answer instanceof Error ? Promise.reject(this.answer) : Promise.resolve(this.answer);
	}
}

/**
 * The active node of the tree, null when none is active.
 */
class FakeDirectoryView
{
	activeNode;
	changes = [];

	constructor(activeNode)
	{
		this.activeNode = activeNode;
	}

	getActiveNodeTitle()
	{
		return this.activeNode === null ? "" : this.activeNode.title;
	}

	getActiveNodeId()
	{
		return this.activeNode === null ? 0 : this.activeNode.key;
	}

	getActiveNodeRights()
	{
		return this.activeNode === null ? 0 : this.activeNode.rights;
	}

	addRootChild(key, name)
	{
		this.changes.push(['addRootChild', key, name]);
	}

	addSubChild(key, name)
	{
		this.changes.push(['addSubChild', key, name]);
	}

	setActiveTitle(name)
	{
		this.changes.push(['setActiveTitle', name]);
	}
}

function createPage(activeNode = {key: 7, title: 'Lobby', rights: ALL_RIGHTS})
{
	const dom      = createDom(PAGE);
	const document = dom.document;
	const service  = new FakeTreeViewService();
	const errors   = [];
	const elements = {
		editFolderDialog: document.getElementById('editFolderDialog'),
		closeEditDialog:  document.getElementById('closeEditDialog')
	};

	return {
		document, service, errors,
		dialog:        new TreeViewDialog(elements, service, {showError: (message) => errors.push(message)}),
		directoryView: new FakeDirectoryView(activeNode),
		element:       elements.editFolderDialog,
		folderName:    document.getElementById('folderName'),
		[Symbol.dispose]: () => dom[Symbol.dispose]()
	};
}

function open(page, action)
{
	page.dialog.prepareShow(action, LANG);
	page.dialog.show(page.directoryView);
}

/**
 * Like the browser after a valid input: the form with method="dialog" closes the dialog with the value of the
 * pressed button, unless the submit is prevented.
 */
async function press(page, buttonId)
{
	// linkedom knows no button.value
	const value = page.document.getElementById(buttonId).getAttribute('value');
	const event = new Event('submit', {bubbles: true, cancelable: true});
	Object.defineProperty(event, 'submitter', {value: {value}});

	page.element.querySelector('form').dispatchEvent(event);
	if (!event.defaultPrevented)
		page.element.close(value);

	await new Promise(resolve => setTimeout(resolve, 0));
	return event;
}

async function submit(page, folderName)
{
	page.folderName.value = folderName;
	return await press(page, 'save');
}

function isOpen(page)
{
	return page.element.hasAttribute('open');
}

Deno.test('the dialog is named after the action', () =>
{
	using page = createPage();

	for (const action of ['add_root_folder', 'add_sub_folder', 'edit_folder'])
	{
		page.dialog.prepareShow(action, LANG);
		assertEquals(page.element.querySelector('.dialog-name').textContent, LANG[action]);
	}
});

Deno.test('a new folder starts with an empty name', () =>
{
	for (const action of ['add_root_folder', 'add_sub_folder', 'delete_folder'])
	{
		using page = createPage();
		page.folderName.value = 'from before';

		open(page, action);

		assertEquals(page.folderName.value, '', action);
		assertEquals(page.element.hasAttribute('open'), true, action);
	}
});

Deno.test('the edit starts with the name of the active folder', () =>
{
	using page = createPage();

	open(page, 'edit_folder');

	assertEquals(page.folderName.value, 'Lobby');
});

Deno.test('an unknown action is not shown', () =>
{
	using page = createPage();
	page.dialog.prepareShow('move_folder', {});

	assertThrows(() => page.dialog.show(page.directoryView), Error, 'Unknown action for show');
	assertEquals(page.element.hasAttribute('open'), false);
});

Deno.test('a root folder is added without an active folder and shown with the name of the backend', async () =>
{
	using page = createPage(null);
	open(page, 'add_root_folder');

	await submit(page, 'Lobby');

	assertEquals(page.service.calls, [['addNode', 0, 'Lobby']]);
	assertEquals(page.directoryView.changes, [['addRootChild', 42, 'Lobby (2)']]);
	assertEquals(page.errors, []);
	assertEquals(isOpen(page), false);
});

Deno.test('a sub folder is added to the active folder', async () =>
{
	using page = createPage();
	open(page, 'add_sub_folder');

	await submit(page, 'Lobby');

	assertEquals(page.service.calls, [['addNode', 7, 'Lobby']]);
	assertEquals(page.directoryView.changes, [['addSubChild', 42, 'Lobby (2)']]);
	assertEquals(isOpen(page), false);
});

Deno.test('the active folder is renamed', async () =>
{
	using page = createPage();
	open(page, 'edit_folder');

	await submit(page, 'Hall');

	assertEquals(page.service.calls, [['editNode', 7, 'Hall']]);
	assertEquals(page.directoryView.changes, [['setActiveTitle', 'Lobby (2)']]);
	assertEquals(isOpen(page), false);
});

Deno.test('cancel and the close button save nothing', async () =>
{
	using page = createPage();

	open(page, 'add_sub_folder');
	page.folderName.value = 'Lobby';
	assertEquals((await press(page, 'cancel')).defaultPrevented, false);
	assertEquals(isOpen(page), false);

	open(page, 'add_sub_folder');
	page.document.getElementById('closeEditDialog').dispatchEvent(new Event('click'));
	await new Promise(resolve => setTimeout(resolve, 0));
	assertEquals(isOpen(page), false);

	assertEquals(page.service.calls, []);
});

Deno.test('closing the dialog does not save, also after a saved folder', async () =>
{
	using page = createPage();
	open(page, 'add_sub_folder');
	await submit(page, 'Lobby');

	// Escape closes without a new return value, the one of the last close stays
	open(page, 'add_sub_folder');
	page.element.returnValue = 'submit';
	page.element.close('submit');
	await new Promise(resolve => setTimeout(resolve, 0));

	assertEquals(page.service.calls, [['addNode', 7, 'Lobby']]);
});

Deno.test('without an active folder only a root folder can be added', async () =>
{
	for (const action of ['add_sub_folder', 'edit_folder'])
	{
		using page = createPage(null);
		open(page, action);

		await submit(page, 'Lobby');

		assertEquals(page.errors, ['no node selected'], action);
		assertEquals(page.service.calls, [], action);
		assertEquals(isOpen(page), true, action);
	}
});

Deno.test('each action needs its own right on the active folder', async () =>
{
	const cases = [
		['add_sub_folder', {create: false, edit: true, delete: true}, 'Missing create right for this node.'],
		['edit_folder', {create: true, edit: false, delete: true}, 'Missing edit right for this node.'],
		['add_sub_folder', {create: false, edit: false, delete: false}, 'There are no rights for this node.'],
	];

	for (const [action, rights, error] of cases)
	{
		using page = createPage({key: 7, title: 'Lobby', rights: rights});
		open(page, action);

		await submit(page, 'Lobby');

		assertEquals(page.errors, [error], action);
		assertEquals(page.service.calls, [], action);
		assertEquals(page.directoryView.changes, [], action);
		assertEquals(isOpen(page), true, action);
	}
});

Deno.test('a root folder needs no rights on the active folder', async () =>
{
	using page = createPage({key: 7, title: 'Lobby', rights: {create: false, edit: false, delete: false}});
	open(page, 'add_root_folder');

	await submit(page, 'Lobby');

	assertEquals(page.service.calls, [['addNode', 0, 'Lobby']]);
});

Deno.test('a refused request shows the error, the dialog stays open with the input', async () =>
{
	using page = createPage();
	page.service.answer = new Error('The name exists already');
	open(page, 'edit_folder');

	const event = await submit(page, 'Hall');

	assertEquals(event.defaultPrevented, true);
	assertEquals(page.errors, ['The name exists already']);
	assertEquals(page.directoryView.changes, []);
	assertEquals(isOpen(page), true);
	assertEquals(page.folderName.value, 'Hall');
});

Deno.test('the delete is no action of the dialog', async () =>
{
	using page = createPage();
	open(page, 'delete_folder');

	await submit(page, 'Lobby');

	assertEquals(page.errors, ['Unknown action']);
	assertEquals(page.service.calls, []);
});
