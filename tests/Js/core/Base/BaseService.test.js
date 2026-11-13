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

import {assertEquals}    from "@std/assert";
import {createDom}       from "@support/dom.js";
import {FakeFetchClient} from "@support/FakeFetchClient.js";
import {BaseService}     from "@js/core/Base/BaseService.js";

const CSRF_META = '<meta name="csrf-token" content="the-token">';

class TestService extends BaseService
{
	send(url, method, data, timeout)
	{
		return this._sendRequest(url, method, data, timeout);
	}
}

Deno.test('a GET goes as JSON without a body and without an own timeout', async () =>
{
	const client = new FakeFetchClient({success: true});

	assertEquals(await new TestService(client).send('/async/units/7', 'GET'), {success: true});

	assertEquals(client.requests, [{url: '/async/units/7', options: {method: 'GET', headers: {'Content-Type': 'application/json'}}}]);
});

Deno.test('other methods send the data with the CSRF token, a timeout goes with them', async () =>
{
	using _dom   = createDom(CSRF_META);
	const client = new FakeFetchClient({success: true});

	await new TestService(client).send('/async/units', 'POST', {name: 'Lobby'}, 60000);

	assertEquals(client.requests[0].options.timeout, 60000);
	assertEquals(client.requests[0].options.headers, {'Content-Type': 'application/json'});
	assertEquals(client.bodyOf(), {name: 'Lobby', csrf_token: 'the-token'});
});

Deno.test('a failed request comes back like an error of the server', async () =>
{
	const client = new FakeFetchClient(new Error('Timeout: Server did not respond.'));

	assertEquals(await new TestService(client).send('/async/units/7', 'GET'), {success: false, error_message: 'Timeout: Server did not respond.'});
});
