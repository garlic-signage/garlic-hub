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

import {parseHTML} from "linkedom";
import "css.escape"; // sets CSS.escape(), Deno has no CSS object

/**
 * A document of linkedom as global document, for modules that use document.
 * Event and CustomEvent come from linkedom too: its elements cannot dispatch the events of Deno.
 * Use it with "using", then the globals are restored after the test.
 */
export function createDom(body = '')
{
	const dom       = parseHTML(`<!doctype html><html><head></head><body>${body}</body></html>`);
	const globals   = {document: dom.document, Event: dom.Event, CustomEvent: dom.CustomEvent};
	const originals = {};

	for (const [name, value] of Object.entries(globals))
	{
		originals[name]  = globalThis[name];
		globalThis[name] = value;
	}

	return {
		document: dom.document,
		[Symbol.dispose]: () => Object.assign(globalThis, originals)
	};
}
