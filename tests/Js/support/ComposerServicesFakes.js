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
 * Fakes for the tests of the services of the template composer (public/js/templates/canvas-composer/Services).
 *
 * fabric and fabricExtensions are globals of script tags. The services read fabric only when they are called:
 * a test sets its fake with replaceGlobals({fabric: composerFabric()}). TemplateGuidelines extends
 * fabricExtensions.AligningGuidelines when its module is loaded, so a fallback is set here, import this file
 * before the modules of the composer.
 */

class FakeAligningGuidelines
{
	canvas;
	options;

	constructor(canvas, options = {})
	{
		this.canvas  = canvas;
		this.options = options;
	}

	dispose() {}
}

// another test may have set its own extensions already
globalThis.fabricExtensions ??= {};
globalThis.fabricExtensions.AligningGuidelines ??= FakeAligningGuidelines;

/** the CustomEvent of Deno: the ComposerEventBus is an EventTarget of Deno, the one of linkedom does not fit it */
export const DenoCustomEvent = globalThis.CustomEvent;

/**
 * An object of fabric.js with what the services use: set(), clone(), events and the coordinates.
 */
export class FakeFabricObject
{
	calls    = [];
	handlers = {};

	constructor(options = {})
	{
		Object.assign(this, options);
	}

	set(key, value)
	{
		if (typeof key === "string")
			this[key] = value;
		else
			Object.assign(this, key);
		return this;
	}

	setCoords()
	{
		this.calls.push(["setCoords"]);
	}

	rotate(angle)
	{
		this.angle = angle;
	}

	on(type, handler)
	{
		this.handlers[type] = handler;
	}

	off(type, handler)
	{
		if (this.handlers[type] === handler)
			delete this.handlers[type];
	}

	async clone(properties)
	{
		// assigned after the constructor: the fields of a subclass would overwrite the options
		const copy = Object.assign(new this.constructor(), {...this, calls: [], handlers: {}});
		copy.clonedWith = properties;
		return copy;
	}
}

/**
 * The fake of fabric for one test: ActiveSelection, FabricImage, Color, Point and the utils of the crop mode.
 * loadImage answers with the element set by the test, or fails with an Error.
 */
export function composerFabric({loadedImage = null} = {})
{
	class ActiveSelection extends FakeFabricObject
	{
		_objects = [];

		forEachObject(callback)
		{
			this._objects.forEach(callback);
		}
	}

	// no fields: they would overwrite the options of the constructor
	class FabricImage extends FakeFabricObject
	{
		getElement()
		{
			return this.element;
		}

		setElement(element)
		{
			this.element = element;
		}
	}

	class Color
	{
		#rgb;

		constructor(color)
		{
			const hex = /^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(color);
			const rgb = /^rgba?\((\d+),\s*(\d+),\s*(\d+)/i.exec(color);
			const found = hex ? hex.slice(1).map(value => parseInt(value, 16)) : rgb ? rgb.slice(1).map(Number) : [0, 0, 0];
			this.#rgb = found;
		}

		getSource()
		{
			return [...this.#rgb, 1];
		}

		toHex()
		{
			return this.#rgb.map(value => value.toString(16).padStart(2, "0")).join("").toUpperCase();
		}
	}

	/** without a rotation: the transformations of the crop mode leave a point as it is */
	class Point
	{
		constructor(x, y)
		{
			this.x = x;
			this.y = y;
		}

		transform()
		{
			return this;
		}
	}

	const loaded = [];

	return {
		ActiveSelection, FabricImage, Color, Point, loaded,
		util: {
			invertTransform: (matrix) => matrix,
			createRotateMatrix: () => [1, 0, 0, 1, 0, 0],
			async loadImage(url, options)
			{
				loaded.push([url, options]);
				if (loadedImage instanceof Error)
					throw loadedImage;
				return loadedImage;
			}
		}
	};
}

/**
 * The TemplateFabricWrapper: every method is recorded with its arguments and answers with answers[name], a function
 * is called with the arguments. Properties like fabricCanvas or hasChanged are set in properties.
 */
export function composerWrapper(answers = {}, properties = {})
{
	const calls = [];
	const target = {calls, ...properties};

	return new Proxy(target, {
		get(object, name)
		{
			if (name in object)
				return object[name];
			if (typeof name === "symbol")
				return undefined;

			return (...args) =>
			{
				calls.push([name, ...args]);
				const answer = answers[name];
				return typeof answer === "function" ? answer(...args) : answer;
			};
		}
	});
}

/**
 * The names of the recorded calls, e.g. to see the order.
 */
export function callNames(wrapper)
{
	return wrapper.calls.map(call => call[0]);
}

/**
 * The overlay while something is loaded or saved: it records start and stop.
 */
export function composerWaitOverlay()
{
	const calls = [];
	return {calls, start: () => calls.push("start"), stop: () => calls.push("stop")};
}

/** the flash messages of the layout (FlashMessageHandler) */
export const FLASH_TEMPLATE = `
	<template id="flashMessageTemplate">
		<div class="message-bar"><span></span><button class="close-button"><i>&times;</i></button></div>
	</template>`;

export function flashMessages(document)
{
	return [...document.querySelectorAll(".message-bar")].map(bar =>
		[bar.classList.contains("message-bar--error") ? "error" : "success", bar.querySelector("span").textContent]);
}
