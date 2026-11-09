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
import {stub}                                            from "@std/testing/mock";
import {FakeTime}                                        from "@std/testing/time";
import {FetchClient}                                     from "@js/core/FetchClient.js";

function answer(body, status = 200)
{
	return () => Promise.resolve(new Response(body, {status: status}));
}

/**
 * Like the browser: the request ends with an AbortError when the signal aborts.
 */
function hangingFetch()
{
	return (url, config) => new Promise((resolve, reject) => {
		config.signal.addEventListener('abort', () => reject(new DOMException('aborted', 'AbortError')));
	});
}

class FakeXhr
{
	upload = {onprogress: null};
	onload = null;
	onerror = null;
	onabort = null;
	status = 0;
	statusText = '';
	responseText = '';
	opened = null;
	sent = undefined;

	open(method, url, async)
	{
		this.opened = {method: method, url: url, async: async};
	}

	send(body)
	{
		this.sent = body;
	}

	respond(status, responseText)
	{
		this.status = status;
		this.responseText = responseText;
		this.onload();
	}
}

/**
 * Deno knows no XMLHttpRequest, a constructor cannot be stubbed with stub().
 */
function fakeXhr()
{
	const original = globalThis.XMLHttpRequest;
	globalThis.XMLHttpRequest = FakeXhr;

	return {[Symbol.dispose]: () => globalThis.XMLHttpRequest = original};
}

function startUpload(client, onProgress = undefined)
{
	client.initUploadWithProgress();
	const xhr     = client.getUploadProgressHandle();
	const promise = client.uploadWithProgress('/async/upload', {method: 'POST', body: 'the body'}, onProgress);
	return {xhr: xhr, promise: promise};
}

Deno.test('a GET is sent with JSON and no-cache headers and the own headers win', async () =>
{
	using fetchStub = stub(globalThis, 'fetch', answer('{}'));

	await new FetchClient().fetchData('/async/list', {headers: {'Accept': 'text/html', 'X-Own': 'yes'}});

	const [url, config] = fetchStub.calls[0].args;
	assertEquals(url, '/async/list');
	assertEquals(config.method, 'GET');
	assertEquals(config.headers, {'Accept': 'text/html', 'Cache-Control': 'no-cache', 'X-Own': 'yes'});
	assertEquals(config.signal instanceof AbortSignal, true);
});

Deno.test('the method and the body are passed on, but not the timeout', async () =>
{
	using fetchStub = stub(globalThis, 'fetch', answer('{}'));

	await new FetchClient().fetchData('/async/save', {method: 'POST', body: 'data', timeout: FetchClient.LLM_TIMEOUT});

	const config = fetchStub.calls[0].args[1];
	assertEquals(config.method, 'POST');
	assertEquals(config.body, 'data');
	assertEquals('timeout' in config, false);
});

Deno.test('a JSON answer is parsed', async () =>
{
	using _fetchStub = stub(globalThis, 'fetch', answer('{"success": true, "id": 5}'));

	assertEquals(await new FetchClient().fetchData('/async/list'), {success: true, id: 5});
});

Deno.test('an answer that is no JSON is returned as text', async () =>
{
	using _fetchStub = stub(globalThis, 'fetch', answer('<p>html</p>'));

	assertStrictEquals(await new FetchClient().fetchData('/async/list'), '<p>html</p>');
});

Deno.test('a structured error of a 4xx is returned, so the caller shows the message', async () =>
{
	using _fetchStub = stub(globalThis, 'fetch', answer('{"success": false, "error_message": "Name taken"}', 422));

	assertEquals(await new FetchClient().fetchData('/async/save'), {success: false, error_message: 'Name taken'});
});

Deno.test('a 4xx without a structured error throws the HTTP error', async () =>
{
	for (const body of ['{"success": true}', 'null', 'not found'])
	{
		using _fetchStub = stub(globalThis, 'fetch', answer(body, 404));

		await assertRejects(() => new FetchClient().fetchData('/async/save'), Error, 'HTTP-Error: 404.');
	}
});

Deno.test('a 401 asks to log in again, even with a structured error', async () =>
{
	using _fetchStub = stub(globalThis, 'fetch', answer('{"success": false, "error_message": "x"}', 401));

	await assertRejects(() => new FetchClient().fetchData('/async/list'), Error, 'Unauthorized - Please log in again.');
});

Deno.test('a 500 is a server error, even with a structured error', async () =>
{
	using _fetchStub = stub(globalThis, 'fetch', answer('{"success": false, "error_message": "x"}', 500));

	await assertRejects(() => new FetchClient().fetchData('/async/list'), Error, 'Server error - Try again later.');
});

Deno.test('other 5xx are HTTP errors', async () =>
{
	using _fetchStub = stub(globalThis, 'fetch', answer('{"success": false}', 503));

	await assertRejects(() => new FetchClient().fetchData('/async/list'), Error, 'HTTP-Error: 503.');
});

Deno.test('a failed request is a network error', async () =>
{
	using _fetchStub = stub(globalThis, 'fetch', () => Promise.reject(new TypeError('Failed to fetch')));

	await assertRejects(() => new FetchClient().fetchData('/async/list'), Error, 'Network error: Failed to fetch');
});

Deno.test('a request is aborted after 6 seconds', async () =>
{
	using time       = new FakeTime();
	using _fetchStub = stub(globalThis, 'fetch', hangingFetch());

	const request = new FetchClient().fetchData('/async/list');
	let   settled = false;
	request.catch(() => {}).finally(() => settled = true);

	await time.tickAsync(5999);
	assertEquals(settled, false);

	await time.tickAsync(1);
	await assertRejects(() => request, Error, 'Timeout: Server did not respond.');
});

Deno.test('a longer timeout lets the request wait longer', async () =>
{
	using time       = new FakeTime();
	using _fetchStub = stub(globalThis, 'fetch', hangingFetch());

	const request = new FetchClient().fetchData('/async/llm', {timeout: FetchClient.LLM_TIMEOUT});
	let   settled = false;
	request.catch(() => {}).finally(() => settled = true);

	await time.tickAsync(64999);
	assertEquals(settled, false);

	await time.tickAsync(1);
	await assertRejects(() => request, Error, 'Timeout: Server did not respond.');
});

Deno.test('the timer is cleared after the answer', async () =>
{
	using time      = new FakeTime();
	using fetchStub = stub(globalThis, 'fetch', answer('{}'));

	await new FetchClient().fetchData('/async/list');
	await time.tickAsync(10000);

	assertEquals(fetchStub.calls[0].args[1].signal.aborted, false);
});

Deno.test('an upload needs initUploadWithProgress() first', async () =>
{
	await assertRejects(() => new FetchClient().uploadWithProgress('/async/upload', {method: 'POST'}), Error, 'Call initUploadWithProgress() first.');
});

Deno.test('the upload handle exists between init and destroy', () =>
{
	using _xhr     = fakeXhr();
	const client   = new FetchClient();

	assertStrictEquals(client.getUploadProgressHandle(), null);
	client.initUploadWithProgress();
	assertEquals(client.getUploadProgressHandle() instanceof FakeXhr, true);
	client.destroyUploadWithProgress();
	assertStrictEquals(client.getUploadProgressHandle(), null);
});

Deno.test('an upload opens the request asynchronously and sends the body', async () =>
{
	using _xhr         = fakeXhr();
	const {xhr, promise} = startUpload(new FetchClient());

	assertEquals(xhr.opened, {method: 'POST', url: '/async/upload', async: true});
	assertEquals(xhr.sent, 'the body');

	xhr.respond(200, '{}');
	await promise;
});

Deno.test('the progress of an upload is reported in percent', async () =>
{
	using _xhr           = fakeXhr();
	const progress       = [];
	const {xhr, promise} = startUpload(new FetchClient(), (percent) => progress.push(percent));

	xhr.upload.onprogress({lengthComputable: true, loaded: 25, total: 200});
	xhr.upload.onprogress({lengthComputable: false, loaded: 50, total: 0});
	xhr.upload.onprogress({lengthComputable: true, loaded: 200, total: 200});

	assertEquals(progress, [12.5, 100]);

	xhr.respond(200, '{}');
	await promise;
});

Deno.test('an upload without a progress callback still works', async () =>
{
	using _xhr           = fakeXhr();
	const {xhr, promise} = startUpload(new FetchClient());

	xhr.upload.onprogress({lengthComputable: true, loaded: 1, total: 2});
	xhr.respond(201, '{"success": true}');

	assertEquals(await promise, {success: true});
});

Deno.test('an upload whose answer is no JSON fails with the answer', async () =>
{
	using _xhr           = fakeXhr();
	const {xhr, promise} = startUpload(new FetchClient());

	xhr.respond(200, 'PHP Warning');

	await assertRejects(() => promise, Error, 'PHP Warning');
});

Deno.test('a structured error of an upload is returned like in fetchData()', async () =>
{
	using _xhr           = fakeXhr();
	const {xhr, promise} = startUpload(new FetchClient());

	xhr.respond(413, '{"success": false, "error_message": "File too large"}');

	assertEquals(await promise, {success: false, error_message: 'File too large'});
});

Deno.test('an upload fails with the HTTP error for 401, 5xx and 4xx without structured error', async () =>
{
	using _xhr     = fakeXhr();
	const cases    = [
		[401, '{"success": false}'],
		[500, '{"success": false}'],
		[404, '{"success": true}'],
		[404, 'null'],
		[404, 'not found'],
	];

	for (const [status, body] of cases)
	{
		const {xhr, promise} = startUpload(new FetchClient());
		xhr.respond(status, body);

		await assertRejects(() => promise, Error, 'HTTP-Error: ' + status);
	}
});

Deno.test('a network error or an abort ends the upload', async () =>
{
	using _xhr     = fakeXhr();

	const failed = startUpload(new FetchClient());
	failed.xhr.onerror();
	await assertRejects(() => failed.promise, Error, 'Network error occurred.');

	const aborted = startUpload(new FetchClient());
	aborted.xhr.onabort();
	await assertRejects(() => aborted.promise, Error, 'Upload aborted.');
});
