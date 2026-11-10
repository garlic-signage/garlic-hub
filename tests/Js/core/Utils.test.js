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
import {stub}                       from "@std/testing/mock";
import {createDom}                  from "@support/dom.js";
import {Utils}                      from "@js/core/Utils.js";

Deno.test('seconds are shown as hours, minutes and seconds', () =>
{
	const times = {
		0:      '00:00:00',
		59:     '00:00:59',
		60:     '00:01:00',
		3599:   '00:59:59',
		3600:   '01:00:00',
		3661:   '01:01:01',
		86399:  '23:59:59',
		360000: '100:00:00',
	};

	for (const [seconds, expected] of Object.entries(times))
		assertEquals(Utils.formatSecondsToTime(Number(seconds)), expected, `${seconds} seconds`);
});

Deno.test('the decimals of a video duration are rounded to full seconds', () =>
{
	const times = [
		[72.48,   '00:01:12'],
		[72.5,    '00:01:13'],
		[59.5,    '00:01:00'],
		[3599.6,  '01:00:00'],
		[0.4,     '00:00:00'],
	];

	for (const [seconds, expected] of times)
		assertEquals(Utils.formatSecondsToTime(seconds), expected, `${seconds} seconds`);
});

Deno.test('bytes are shown in the largest fitting unit with two decimals', () =>
{
	const sizes = [
		[0,             '0 Bytes'],
		[1,             '1.00 Bytes'],
		[1023,          '1023.00 Bytes'],
		[1024,          '1.00 KB'],
		[1536,          '1.50 KB'],
		[1048576,       '1.00 MB'],
		[5 * 1024 ** 3, '5.00 GB'],
		[1024 ** 4,     '1.00 TB'],
		[1024 ** 5,     '1.00 PB'],
		[2 * 1024 ** 6, '2048.00 PB'],
	];

	for (const [bytes, expected] of sizes)
		assertEquals(Utils.formatBytes(bytes), expected, `${bytes} bytes`);
});

Deno.test('the CSRF token comes from the meta tag of the layout', () =>
{
	using _dom = createDom('<meta name="csrf-token" content="the-token">');

	assertEquals(Utils.csrfToken(), 'the-token');
});

Deno.test('a missing CSRF token throws', () =>
{
	for (const html of ['', '<meta name="csrf-token">', '<meta name="other" content="the-token">'])
	{
		using _dom = createDom(html);

		assertThrows(() => Utils.csrfToken(), Error, 'No CSRF token found in meta tag');
	}
});

Deno.test('a confirmation asks with the message and returns the answer', async () =>
{
	for (const answer of [true, false])
	{
		using confirmStub = stub(globalThis, 'confirm', () => answer);

		assertEquals(await Utils.confirmAction('Delete it?'), answer);
		assertEquals(confirmStub.calls[0].args, ['Delete it?']);
	}
});
