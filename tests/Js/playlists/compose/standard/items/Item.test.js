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
import {assertSameElement, createDom} from "@support/dom.js";
import {Item}                         from "@js/playlists/compose/standard/items/Item.js";

// the item template of templates/playlists/compose.mustache, the export button and the flash messages of the layout
function page(edition = 'core')
{
	return `
	<input type="hidden" id="cms_edition" value="${edition}">
	<button id="playerExport" data-release="0"><span class="label">Export</span></button>
	<template id="flashMessageTemplate">
		<div class="message-bar"><span></span><button class="close-button"><i>&times;</i></button></div>
	</template>
	<template id="playlistItemTemplate">
		<li class="playlist-item" draggable="true" id="">
			<img class="thumbnail" src="" alt="" />
			<div class="properties">
				<div class="actions" data-item-id="">
					<span class="item-duration">00:00:00</span>
					<button class="bi bi-eye item-preview"></button>
					<a class="bi bi-link link-playlist button"></a>
					<button class="bi bi-alarm conditional-play"></button>
					<button class="bi bi-lightning edit-trigger"></button>
					<button class="bi bi-pencil edit-widget"></button>
					<a class="bi bi-pencil edit-template button"></a>
					<button class="bi bi-gear edit-settings"></button>
					<button class="bi bi-copy copy-item"> </button>
					<button class="bi bi-trash delete-item"></button>
				</div>
				<p class="item-name"></p>
			</div>
		</li>
	</template>
	<ul id="playlist"></ul>`;
}

function itemData(changes = {})
{
	return {
		item_id: 12, item_name: 'Lobby', item_type: 'mediapool', item_duration: 75, file_resource: 'abc',
		mimetype: 'image/jpeg', content_data: '', conditional: '', begin_trigger: '',
		urls: {thumbnail: '/thumb/abc.jpg'},
		...changes
	};
}

/**
 * Answers editItem with the saved value, or with the answer set by the test.
 */
class FakeItemsService
{
	calls          = [];
	editAnswer     = null;
	defaultSeconds = {success: true, item: {default_duration: 15}};

	async editItem(id, name, value)
	{
		this.calls.push(['editItem', id, name, value]);
		return this.editAnswer ?? {success: true, data: Number(value)};
	}

	async fetchDefaultSeconds(id)
	{
		this.calls.push(['fetchDefaultSeconds', id]);
		return this.defaultSeconds;
	}
}

/**
 * The editors of conditional play, trigger and widget: fetch answers with the set result, the dialog is recorded.
 */
class FakeEditorFactory
{
	created = [];
	answer  = {success: true};

	create()
	{
		const factory = this;
		const editor  = {
			calls: [],
			async fetch(itemId)            { this.calls.push(['fetch', itemId]); return factory.answer; },
			async fetchBeginTrigger(itemId) { this.calls.push(['fetchBeginTrigger', itemId]); return factory.answer; },
			initDialog(...args)            { this.calls.push(['initDialog', ...args]); }
		};
		this.created.push(editor);
		return editor;
	}
}

function createItem(changes = {}, {edition = 'core', master = false} = {})
{
	const dom      = createDom(page(edition));
	const service  = new FakeItemsService();
	const widgets  = new FakeEditorFactory();
	const plays    = new FakeEditorFactory();
	const triggers = new FakeEditorFactory();
	const item     = new Item(itemData(changes), service, widgets, plays, triggers);
	item.belongsToMasterPlaylist = master;
	dom.document.getElementById('playlist').appendChild(item.buildItemElement());

	return {
		document: dom.document, service, widgets, plays, triggers, item,
		element: dom.document.getElementById('itemId-12'),
		find: (selector) => dom.document.querySelector('#itemId-12 ' + selector),
		[Symbol.dispose]: () => dom[Symbol.dispose]()
	};
}

async function fire(element, type, properties = {})
{
	const event = new Event(type, {bubbles: true, cancelable: true});
	Object.assign(event, properties);
	element.dispatchEvent(event);
	await new Promise(resolve => setTimeout(resolve, 0));
	return event;
}

function errors(page)
{
	return [...page.document.querySelectorAll('.message-bar span')].map(span => span.textContent);
}

function isNotified(page)
{
	return page.document.getElementById('playerExport').classList.contains('notify-save');
}

function actions(page)
{
	return [...page.find('.actions').children].filter(element => element.tagName !== 'SPAN').map(element => element.className.split(' ')[2]);
}

Deno.test('the item shows its thumbnail, name and duration', () =>
{
	using page = createItem();

	assertEquals(page.element.dataset.mediaId, 'abc');
	assertEquals([page.find('img').getAttribute('src'), page.find('img').getAttribute('alt')], ['/thumb/abc.jpg', 'Lobby']);
	assertEquals(page.find('.item-name').textContent, 'Lobby');
	assertEquals([page.find('.item-duration').textContent, page.find('.item-duration').dataset.seconds], ['00:01:15', '75']);
	assertEquals(page.find('.actions').getAttribute('data-item-id'), '12');
});

Deno.test('a medium of the core edition has preview, conditional play and delete', () =>
{
	using page = createItem();

	assertEquals(actions(page), ['item-preview', 'conditional-play', 'delete-item']);
	assertSameElement(page.item.previewAction, page.find('.item-preview'));
	assertSameElement(page.item.conditionalPlayAction, page.find('.conditional-play'));
	assertSameElement(page.item.deleteItemAction, page.find('.delete-item'));
	assertEquals([page.item.editTriggerAction, page.item.editSettingsAction, page.item.copyItemAction], [null, null, null]);
});

Deno.test('a nested playlist links to its composer', () =>
{
	using page = createItem({item_type: 'playlist', file_resource: '9'});

	assertEquals(page.find('.link-playlist').getAttribute('href'), '/playlists/compose/9');
	assertSameElement(page.item.linkPlaylistAction, page.find('.link-playlist'));
	assertEquals(page.find('.item-name').hasAttribute('contenteditable'), false);
});

Deno.test('a template links to the composer of the item', () =>
{
	using page = createItem({item_type: 'template'});

	assertEquals(page.find('.edit-template').getAttribute('href'), '/playlist-item/composer/12');
	assertSameElement(page.item.editTemplateAction, page.find('.edit-template'));
});

Deno.test('the trigger is only in a master playlist', () =>
{
	{
		using page = createItem({}, {master: true});
		assertEquals(actions(page), ['item-preview', 'conditional-play', 'edit-trigger', 'delete-item']);
	}
	{
		using page = createItem({}, {master: false});
		assertEquals(actions(page).includes('edit-trigger'), false);
	}
});

Deno.test('conditional play and trigger with values are marked', () =>
{
	for (const [value, marked] of [['', false], ['a:0:{}', false], [undefined, false], ['a:1:{s:4:"date";}', true]])
	{
		using page = createItem({conditional: value, begin_trigger: value}, {master: true});

		assertEquals(page.find('.conditional-play').classList.contains('icon-values-setted'), marked, String(value));
		assertEquals(page.find('.edit-trigger').classList.contains('icon-values-setted'), marked, String(value));
	}
});

Deno.test('a widget with content data can be edited', () =>
{
	{
		using page = createItem({mimetype: 'application/widget', content_data: 'a:1:{}'});
		assertSameElement(page.item.editWidgetAction, page.find('.edit-widget'));
	}
	{
		using page = createItem({mimetype: 'application/widget', content_data: ''});
		assertEquals([page.item.editWidgetAction, page.find('.edit-widget')], [null, null]);
	}
});

Deno.test('the other editions have settings and copy nested playlists and templates', () =>
{
	{
		using page = createItem({item_type: 'template'}, {edition: 'enterprise'});
		assertSameElement(page.item.editSettingsAction, page.find('.edit-settings'));
		assertSameElement(page.item.copyItemAction, page.find('.copy-item'));
		assertEquals(page.item.conditionalPlayAction, null);
	}
	{
		using page = createItem({item_type: 'mediapool'}, {edition: 'enterprise'});
		assertEquals([page.item.copyItemAction, page.find('.copy-item')], [null, null]);
	}
});

Deno.test('the editors of conditional play, trigger and widget open their dialog after loading', async () =>
{
	using page = createItem({mimetype: 'application/widget', content_data: 'a:1:{}'}, {master: true});

	await fire(page.find('.conditional-play'), 'click');
	await fire(page.find('.edit-trigger'), 'click');
	await fire(page.find('.edit-widget'), 'click');

	const [play]    = page.plays.created;
	const [trigger] = page.triggers.created;
	const [widget]  = page.widgets.created;
	assertEquals(play.calls[0], ['fetch', 12]);
	assertSameElement(play.calls[1][1], page.find('.conditional-play'));
	assertEquals(trigger.calls[0], ['fetchBeginTrigger', 12]);
	assertSameElement(trigger.calls[1][1], page.find('.edit-trigger'));
	assertEquals(widget.calls, [['fetch', 12], ['initDialog']]);
});

Deno.test('an editor which could not be loaded shows the error and no dialog', async () =>
{
	using page = createItem({mimetype: 'application/widget', content_data: 'a:1:{}'}, {master: true});
	for (const factory of [page.plays, page.triggers, page.widgets])
		factory.answer = {success: false, error_message: 'Not allowed'};

	await fire(page.find('.conditional-play'), 'click');
	await fire(page.find('.edit-trigger'), 'click');
	await fire(page.find('.edit-widget'), 'click');

	assertEquals(errors(page), ['Not allowed', 'Not allowed', 'Not allowed']);
	for (const factory of [page.plays, page.triggers, page.widgets])
		assertEquals(factory.created[0].calls.length, 1);
});

Deno.test('the name of a medium is saved with Enter', async () =>
{
	using page = createItem();
	const name = page.find('.item-name');
	assertEquals(name.contentEditable, true);

	await fire(name, 'focus');
	name.textContent = 'Hall';
	const enter = await fire(name, 'keydown', {key: 'Enter'});
	await fire(name, 'blur');

	assertEquals(enter.defaultPrevented, true);
	assertEquals(page.service.calls, [['editItem', 12, 'item_name', 'Hall']]);
	assertEquals(isNotified(page), true);
});

Deno.test('the name of a medium is saved when the field is left', async () =>
{
	using page = createItem();
	const name = page.find('.item-name');

	await fire(name, 'focus');
	name.textContent = 'Hall';
	await fire(name, 'blur');
	await fire(name, 'blur');

	assertEquals(page.service.calls, [['editItem', 12, 'item_name', 'Hall']]);
});

Deno.test('other keys do not save the name', async () =>
{
	using page = createItem();
	const name = page.find('.item-name');

	await fire(name, 'focus');
	await fire(name, 'keydown', {key: 'a'});

	assertEquals(page.service.calls, []);
});

/**
 * A click on the duration opens the field with the seconds and the button to reset them.
 */
async function openDuration(page)
{
	await fire(page.find('.item-duration'), 'click');
	return {field: page.find('.item-duration-input input'), reset: page.find('.duration-reset')};
}

Deno.test('a click on the duration opens a field with the seconds', async () =>
{
	using page = createItem();

	const {field, reset} = await openDuration(page);

	assertEquals([field.type, field.value], ['number', '75']);
	assertEquals(reset.classList.contains('bi-arrow-clockwise'), true);
	assertEquals(page.find('.item-duration'), null);
});

Deno.test('the duration is saved with Enter and shown again', async () =>
{
	using page = createItem();
	const {field} = await openDuration(page);

	field.value = '90';
	await fire(field, 'keydown', {key: 'Enter'});

	assertEquals(page.service.calls, [['editItem', 12, 'item_duration', '90']]);
	assertEquals(page.find('.item-duration-input'), null);
	assertEquals([page.find('.item-duration').textContent, page.find('.item-duration').dataset.seconds], ['00:01:30', '90']);
	assertEquals(isNotified(page), true);
});

Deno.test('the duration is saved when the field is left', async () =>
{
	using page = createItem();
	const {field} = await openDuration(page);

	field.value = '30';
	await fire(field, 'blur');

	assertEquals(page.service.calls, [['editItem', 12, 'item_duration', '30']]);
	assertEquals(page.find('.item-duration').textContent, '00:00:30');
});

Deno.test('a duration which could not be saved shows the error and keeps the field', async () =>
{
	using page = createItem();
	page.service.editAnswer = {success: false, error_message: 'Too short'};
	const {field} = await openDuration(page);

	field.value = '0';
	await fire(field, 'blur');

	assertEquals(errors(page), ['Too short']);
	assertEquals(page.find('.item-duration-input input') !== null, true);
	assertEquals(isNotified(page), false);
});

Deno.test('the reset takes the default duration of the medium', async () =>
{
	using page = createItem();
	const {reset} = await openDuration(page);

	const mousedown = await fire(reset, 'mousedown');
	await fire(reset, 'click');

	assertEquals(mousedown.defaultPrevented, true);
	assertEquals(page.service.calls, [['fetchDefaultSeconds', 12], ['editItem', 12, 'item_duration', '15']]);
	assertEquals(page.find('.item-duration').textContent, '00:00:15');
});

Deno.test('a default duration which could not be loaded shows the error', async () =>
{
	using page = createItem();
	page.service.defaultSeconds = {success: false, error_message: 'Not found'};
	const {reset} = await openDuration(page);

	await fire(reset, 'click');

	assertEquals(errors(page), ['Not found']);
	assertEquals(page.service.calls, [['fetchDefaultSeconds', 12]]);
});
