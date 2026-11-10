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

/**
 * Replaces globals like GLightbox or localStorage for one test: use it with "using", then the originals come back.
 * defineProperty, because some globals of Deno are getters.
 */
export function replaceGlobals(globals)
{
	const originals = {};
	for (const [name, value] of Object.entries(globals))
	{
		originals[name] = Object.getOwnPropertyDescriptor(globalThis, name);
		Object.defineProperty(globalThis, name, {value: value, configurable: true, writable: true});
	}

	return {
		[Symbol.dispose]: () =>
		{
			for (const [name, descriptor] of Object.entries(originals))
			{
				if (descriptor === undefined)
					delete globalThis[name];
				else
					Object.defineProperty(globalThis, name, descriptor);
			}
		}
	};
}

/**
 * The Storage of the browser in memory, Deno would keep its localStorage between the runs.
 */
export class MemoryStorage
{
	#items = new Map();

	getItem(key)
	{
		return this.#items.has(key) ? this.#items.get(key) : null;
	}

	setItem(key, value)
	{
		this.#items.set(key, String(value));
	}

	removeItem(key)
	{
		this.#items.delete(key);
	}
}
