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

import {assertEquals, assertRejects, assertStrictEquals} from "@std/assert";
import {createDom}                                       from "@support/dom.js";
import {FakeFetchClient}                                 from "@support/FakeFetchClient.js";
import {TreeViewService}                                 from "@js/mediapool/treeview/TreeViewService.js";

const CSRF_META = '<meta name="csrf-token" content="the-token">';

// every request of the tree: the call, the address, the method and the body without the token
const REQUESTS = {
	addNode:    [(service) => service.addNode(5, 'Lobby'), '/async/mediapool/node', 'POST', {node_id: 5, name: 'Lobby'}],
	editNode:   [(service) => service.editNode(5, 'Hall'), '/async/mediapool/node', 'PATCH', {node_id: 5, name: 'Hall'}],
	deleteNode: [(service) => service.deleteNode(5), '/async/mediapool/node', 'DELETE', {node_id: 5}],
	moveNodeTo: [(service) => service.moveNodeTo(5, 7, 'before'), '/async/mediapool/node/move', 'POST',
		{src_node_id: 5, target_node_id: 7, target_region: 'before'}],
};

Deno.test('every request of the tree goes to its address with the CSRF token', async (t) =>
{
	using _dom = createDom(CSRF_META);

	for (const [name, [call, url, method, body]] of Object.entries(REQUESTS))
	{
		await t.step(name, async () =>
		{
			const client = new FakeFetchClient({success: true});

			await call(new TreeViewService(client));

			assertEquals(client.requests[0].url, url);
			assertEquals(client.requests[0].options.method, method);
			assertEquals(client.bodyOf(), {...body, csrf_token: 'the-token'});
		});
	}
});

Deno.test('a successful request gives the answer of the backend', async () =>
{
	using _dom   = createDom(CSRF_META);
	const answer = {success: true, data: {id: 42, new_name: 'Lobby (2)'}};

	assertStrictEquals(await new TreeViewService(new FakeFetchClient(answer)).addNode(0, 'Lobby'), answer);
});

Deno.test('a refused request fails with the message of the backend', async (t) =>
{
	using _dom = createDom(CSRF_META);

	for (const [name, [call]] of Object.entries(REQUESTS))
	{
		await t.step(name, async () =>
		{
			const service = new TreeViewService(new FakeFetchClient({success: false, error_message: 'Not allowed'}));

			await assertRejects(() => call(service), Error, 'Not allowed');
		});
	}
});

Deno.test('a timeout or a network error fails with its message', async () =>
{
	using _dom    = createDom(CSRF_META);
	const service = new TreeViewService(new FakeFetchClient(new Error('Timeout: Server did not respond.')));

	await assertRejects(() => service.deleteNode(5), Error, 'Timeout: Server did not respond.');
});

Deno.test('only success true counts as success', async () =>
{
	using _dom = createDom(CSRF_META);

	for (const answer of [{success: 'true'}, {success: 1}, {}])
	{
		const service = new TreeViewService(new FakeFetchClient(answer));
		await assertRejects(() => service.editNode(5, 'Hall'), Error, undefined, JSON.stringify(answer));
	}
});
