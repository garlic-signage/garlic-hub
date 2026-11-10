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

import {assertEquals, assertStrictEquals} from "@std/assert";
import {createDom}                        from "@support/dom.js";
import {FakeFetchClient}                  from "@support/FakeFetchClient.js";
import {LlmModelsService}                 from "@js/core/LlmModelsService.js";

Deno.test('the models are asked with a POST of the provider and the CSRF token', async () =>
{
	using _dom   = createDom('<meta name="csrf-token" content="the-token">');
	const answer = {success: true, models: ['gpt-4o']};
	const client = new FakeFetchClient(answer);

	assertStrictEquals(await new LlmModelsService(client).load('/async/llm/models', {connection_id: '3'}), answer);

	assertEquals(client.requests[0].url, '/async/llm/models');
	assertEquals(client.requests[0].options.method, 'POST');
	assertEquals(client.requests[0].options.timeout, undefined);
	assertEquals(client.bodyOf(), {connection_id: '3', csrf_token: 'the-token'});
});
