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

import {ComposerEventBus} from "@js/core/canvas/ComposerEventBus.js";

// the CustomEvent of Deno: the bus is an EventTarget of Deno, the one of linkedom (createDom) does not fit it
const DenoCustomEvent = globalThis.CustomEvent;

/**
 * The ComposerEventBus is one for all tests: the listeners which the controllers of a test add are removed
 * again after it, use it with "using". It records the events of the given types, too.
 */
export function busOfTest(...recordedTypes)
{
	const added    = [];
	const events   = [];
	const original = ComposerEventBus.addEventListener;
	ComposerEventBus.addEventListener = function (type, listener, options)
	{
		added.push([type, listener]);
		return original.call(this, type, listener, options);
	};
	for (const type of recordedTypes)
		ComposerEventBus.addEventListener(type, (event) => events.push([type, event.detail]));

	return {
		events,
		send: (type, detail) => ComposerEventBus.dispatchEvent(new DenoCustomEvent(type, {detail})),
		[Symbol.dispose]: () =>
		{
			delete ComposerEventBus.addEventListener; // the method of EventTarget again
			for (const [type, listener] of added)
				ComposerEventBus.removeEventListener(type, listener);
		}
	};
}
