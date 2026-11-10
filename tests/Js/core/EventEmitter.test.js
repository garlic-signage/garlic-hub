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
import {EventEmitter}                     from "@js/core/EventEmitter.js";

Deno.test('the listeners of an event get its arguments in the order they were added', () =>
{
	const emitter = new EventEmitter();
	const calls   = [];
	emitter.on('saved', (id, name) => calls.push(['first', id, name]));
	emitter.on('saved', (id) => calls.push(['second', id]));
	emitter.on('other', () => calls.push(['other']));

	assertEquals(emitter.emit('saved', 7, 'Lobby'), true);

	assertEquals(calls, [['first', 7, 'Lobby'], ['second', 7]]);
});

Deno.test('an event without listeners reports it', () =>
{
	assertEquals(new EventEmitter().emit('saved'), false);
});

Deno.test('off() removes one listener or all of an event', () =>
{
	const emitter = new EventEmitter();
	const calls   = [];
	const first   = () => calls.push('first');
	emitter.on('saved', first);
	emitter.on('saved', () => calls.push('second'));

	emitter.off('saved', first);
	emitter.emit('saved');
	emitter.off('saved');

	assertEquals(calls, ['second']);
	assertEquals(emitter.emit('saved'), false);
	assertStrictEquals(emitter.off('unknown'), emitter);
});

Deno.test('a once listener is called one time only', () =>
{
	const emitter = new EventEmitter();
	const calls   = [];
	emitter.once('saved', (id) => calls.push(id));

	emitter.emit('saved', 1);
	emitter.emit('saved', 2);

	assertEquals(calls, [1]);
});

Deno.test('on() and once() can be chained', () =>
{
	const emitter = new EventEmitter();

	assertStrictEquals(emitter.on('a', () => {}).once('b', () => {}), emitter);
});
