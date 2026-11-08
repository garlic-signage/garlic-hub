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
 * Runs the design check a moment after every change and shows the hints: as a list in the toolbar, which selects
 * the element of a hint, and as a mark at the element on the canvas.
 */
export class DesignCheckController
{
	static DELAY_MS = 500;
	static MARK     = "⚠";

	#designCheckService;
	#fabricWrapper;
	#composerContext;
	#countElement = document.getElementById("designCheckCount");
	#okElement    = document.getElementById("designCheckOk");
	#listElement  = document.getElementById("designCheckHints");
	#marked       = [];
	#timer        = null;

	constructor(designCheckService, fabricWrapper, composerContext)
	{
		this.#designCheckService = designCheckService;
		this.#fabricWrapper      = fabricWrapper;
		this.#composerContext    = composerContext;
	}

	init()
	{
		for (const eventName of ["object:added", "object:removed", "object:modified", "text:changed"])
			this.#fabricWrapper.on(eventName, () => this.#schedule());

		const canvas = this.#fabricWrapper.fabricCanvas;
		canvas.on("after:render", (event) =>
		{
			// only on the canvas of the composer, not on the copies the check renders
			if (event.ctx === canvas.contextContainer)
				this.#drawMarks(event.ctx);
		});
	}

	#schedule()
	{
		clearTimeout(this.#timer);
		this.#timer = setTimeout(() => this.#run(), DesignCheckController.DELAY_MS);
	}

	#run()
	{
		const hints = this.#designCheckService.check();

		this.#marked = [...new Set(hints.filter(hint => hint.object !== null).map(hint => hint.object))];
		this.#countElement.textContent = String(hints.length);
		this.#countElement.hidden      = hints.length === 0;
		this.#okElement.hidden         = hints.length > 0;
		this.#listElement.replaceChildren(...hints.map(hint => this.#buildEntry(hint)));
		this.#fabricWrapper.renderAll();
	}

	#buildEntry(hint)
	{
		const entry = document.createElement("li");
		let text    = this.#composerContext.getLangByKey(hint.key);
		for (const [name, value] of Object.entries(hint.values))
			text = text.replace("{" + name + "}", String(value));
		entry.textContent = text;

		if (hint.object !== null)
		{
			entry.classList.add("design-check-element");
			entry.addEventListener("click", () =>
			{
				this.#fabricWrapper.setActiveObject(hint.object);
				this.#fabricWrapper.renderAll();
			});
		}

		return entry;
	}

	#drawMarks(ctx)
	{
		for (const object of this.#marked)
		{
			if (!this.#fabricWrapper.getObjects().includes(object))
				continue;

			const rect = object.getBoundingRect();
			ctx.save();
			ctx.font         = "bold 22px sans-serif";
			ctx.textBaseline = "top";
			ctx.lineWidth    = 3;
			ctx.strokeStyle  = "#ffffff";
			ctx.fillStyle    = "#d97706";
			ctx.strokeText(DesignCheckController.MARK, rect.left + rect.width + 4, rect.top);
			ctx.fillText(DesignCheckController.MARK, rect.left + rect.width + 4, rect.top);
			ctx.restore();
		}
	}
}
