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
'use strict';

/**
 * Undo and redo of the template composer: every object:modified (or save()) stores the state before the change.
 * At most 20 steps. A state is the content as JSON with the properties of the composer.
 */
export class CanvasHistory
{
	static MAX_STEPS = 20;

	#canvas;
	#properties;
	#undo       = [];
	#redo       = [];
	#nextState  = "";
	#processing = false;
	#paused     = false;

	/**
	 * @param {fabric.Canvas} canvas
	 * @param {string[]} properties the properties besides the ones of fabric.js
	 */
	constructor(canvas, properties)
	{
		this.#canvas     = canvas;
		this.#properties = properties;
		this.#nextState  = this.#state();
		this.#canvas.on("object:modified", () => this.save());
	}

	/**
	 * A new content: no steps back or forward.
	 */
	reset()
	{
		this.#undo      = [];
		this.#redo      = [];
		this.#nextState = this.#state();
	}

	/**
	 * No steps until resume(), e.g. while an image is cropped: the cut becomes one step at the end.
	 */
	pause()
	{
		this.#paused = true;
	}

	resume()
	{
		this.#paused = false;
	}

	save()
	{
		if (this.#processing || this.#paused)
			return;

		this.#undo.push(this.#nextState);
		if (this.#undo.length > CanvasHistory.MAX_STEPS)
			this.#undo.shift();
		this.#nextState = this.#state();
	}

	async undo()
	{
		if (this.#processing || this.#undo.length <= 1)
			return;

		const state = this.#undo.pop();
		this.#redo.push(this.#state());
		await this.#load(state, "history:undo");
	}

	async redo()
	{
		if (this.#processing || this.#redo.length === 0)
			return;

		const state = this.#redo.pop();
		this.#undo.push(this.#state());
		await this.#load(state, "history:redo");
	}

	#state()
	{
		return JSON.stringify(this.#canvas.toObject(this.#properties));
	}

	async #load(state, eventName)
	{
		this.#processing = true;
		this.#nextState  = state;
		try
		{
			await this.#canvas.loadFromJSON(state);
			this.#canvas.renderAll();
			this.#canvas.fire(eventName);
		}
		finally
		{
			this.#processing = false;
		}
	}
}
