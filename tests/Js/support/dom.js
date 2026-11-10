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
 * HTMLImageElement for the checks with instanceof.
 * The dialogs get showModal() and close(), linkedom has none.
 * The selects get a value to set and Option() is there, both like in the browser.
 * Use it with "using", then the globals are restored after the test.
 */
export function createDom(body = '')
{
	const dom       = parseHTML(`<!doctype html><html><head></head><body>${body}</body></html>`);
	const globals   = {document: dom.document, Event: dom.Event, CustomEvent: dom.CustomEvent, HTMLImageElement: dom.HTMLImageElement};
	const originals = {};

	for (const [name, value] of Object.entries(globals))
	{
		originals[name]  = globalThis[name];
		globalThis[name] = value;
	}

	const removeDialogMethods = addDialogMethods(dom.document);
	const removeSelectValue   = addSelectValue(dom.document);
	const originalOption      = globalThis.Option;
	globalThis.Option = function (text = '', value = undefined)
	{
		const option = dom.document.createElement('option');
		option.textContent = text;
		if (value !== undefined)
			option.value = value;
		return option;
	};

	return {
		document: dom.document,
		[Symbol.dispose]: () =>
		{
			removeDialogMethods();
			removeSelectValue();
			globalThis.Option = originalOption;
			Object.assign(globalThis, originals);
		}
	};
}

/**
 * Like the browser: showModal() sets "open", close() removes it and fires "close" with the return value.
 * The prototype is shared by all documents of linkedom, so the methods are removed again.
 */
function addDialogMethods(document)
{
	const prototype = Object.getPrototypeOf(document.createElement('dialog'));
	if ('showModal' in prototype)
		return () => {};

	prototype.showModal = function () { this.setAttribute('open', ''); };
	prototype.close     = function (returnValue = '')
	{
		if (!this.hasAttribute('open'))
			return;

		this.returnValue = returnValue;
		this.removeAttribute('open');
		this.dispatchEvent(new globalThis.Event('close'));
	};

	return () =>
	{
		delete prototype.showModal;
		delete prototype.close;
	};
}

/**
 * Like the browser: the value is the one of the selected option or of the first one, setting it selects the option
 * with this value or none. linkedom only reads the selected option.
 */
function addSelectValue(document)
{
	const prototype = Object.getPrototypeOf(document.createElement('select'));
	const original  = Object.getOwnPropertyDescriptor(prototype, 'value');

	Object.defineProperty(prototype, 'value', {
		configurable: true,
		get()
		{
			const option = this.querySelector('option[selected]') ?? this.querySelector('option');
			return option?.value ?? '';
		},
		set(value)
		{
			for (const option of this.querySelectorAll('option'))
				option.toggleAttribute('selected', false);

			const match = [...this.querySelectorAll('option')].find(option => option.value === String(value));
			match?.setAttribute('selected', '');
		}
	});

	return () => Object.defineProperty(prototype, 'value', original);
}
