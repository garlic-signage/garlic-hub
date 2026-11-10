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
import {assertEquals}   from "@std/assert";
import {createDom}      from "@support/dom.js";
import {PushHandler}    from "@js/player/datatable/ActionHandler/PushHandler.js";

/**
 * Records what the push shows: the calls of the wait overlay and the messages in their order.
 */
function createPush(pushPlaylist)
{
	const dom     = createDom('<li class="push">Push</li>');
	const steps   = [];
	const calls   = [];
	const service = {pushPlaylist: (playerId) => { calls.push(playerId); return pushPlaylist(); }};
	const handler = new PushHandler(
		{
			clearAllMessages: () => steps.push('clear'),
			showSuccess:      (message) => steps.push('success: ' + message),
			showError:        (message) => steps.push('error: ' + message)
		},
		service,
		{start: () => steps.push('start'), stop: () => steps.push('stop')},
		7
	);
	const item = dom.document.querySelector('.push');
	handler.addPushPlaylistListener(item);

	return {steps, calls, item, [Symbol.dispose]: () => dom[Symbol.dispose]()};
}

async function click(item)
{
	item.dispatchEvent(new Event('click'));
	for (let i = 0; i < 5; i++)
		await new Promise(resolve => setTimeout(resolve, 0));
}

Deno.test('the push waits for the player and shows its message', async () =>
{
	using push = createPush(() => Promise.resolve({success: true, message: 'Playlist sent to the player'}));

	await click(push.item);

	assertEquals(push.calls, [7]);
	assertEquals(push.steps, ['start', 'stop', 'clear', 'success: Playlist sent to the player']);
});

Deno.test('a refused push shows the error of the answer', async () =>
{
	using push = createPush(() => Promise.resolve({success: false, error_message: 'Player not reachable'}));

	await click(push.item);

	assertEquals(push.steps, ['start', 'stop', 'clear', 'error: Player not reachable']);
});

Deno.test('a failed request ends the waiting and shows its message', async () =>
{
	using push = createPush(() => Promise.reject(new Error('offline')));

	await click(push.item);

	assertEquals(push.steps, ['start', 'stop', 'error: offline']);
});

Deno.test('an error before the request ends the waiting too', async () =>
{
	using push = createPush(() => { throw new Error('broken'); });

	await click(push.item);

	assertEquals(push.steps, ['stop', 'error: broken']);
});
