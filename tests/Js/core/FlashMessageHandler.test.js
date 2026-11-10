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
import {FlashMessageHandler}        from "@js/core/FlashMessageHandler.js";

// the template of the layout
const PAGE = `
	<template id="flashMessageTemplate">
		<div class="message-bar"><span></span><button class="close-button">x</button></div>
	</template>
	<main id="messages"><p>Content</p></main>`;

function messages(document)
{
	return [...document.querySelectorAll('#messages .message-bar')].map(bar => [bar.className, bar.querySelector('span').textContent, bar.querySelector('.close-button') !== null]);
}

Deno.test('without the template there are no messages', () =>
{
	using _dom = createDom('<main></main>');

	assertThrows(() => new FlashMessageHandler(), Error, 'Message template not found');
});

Deno.test('every message comes at the top with its type, a success closes itself', () =>
{
	using dom = createDom(PAGE);
	const handler = new FlashMessageHandler('#messages');

	handler.showError('<b>Failed</b>');
	handler.showInformation('Note');
	handler.showSuccess('Saved');

	assertEquals(messages(dom.document), [
		['message-bar message-bar--success', 'Saved', false],
		['message-bar message-bar--information', 'Note', true],
		['message-bar message-bar--error', '<b>Failed</b>', true]
	]);
	assertEquals(dom.document.querySelector('#messages').lastElementChild.tagName, 'P');
});

Deno.test('all messages fade out and go', async () =>
{
	using dom = createDom(PAGE);
	const handler = new FlashMessageHandler('#messages');
	handler.showError('Failed');
	handler.showSuccess('Saved');

	handler.clearAllMessages();

	const bars = [...dom.document.querySelectorAll('#messages .message-bar')];
	assertEquals(bars.map(bar => bar.style.opacity), ['0', '0']);
	await new Promise(resolve => setTimeout(resolve, 350));
	assertEquals(messages(dom.document), []);
});

Deno.test('a message removed meanwhile is no error', async () =>
{
	using dom = createDom(PAGE);
	const handler = new FlashMessageHandler('#messages');
	handler.showError('Failed');

	handler.clearAllMessages();
	dom.document.querySelector('.message-bar').remove();

	await new Promise(resolve => setTimeout(resolve, 350));
	assertEquals(messages(dom.document), []);
});
