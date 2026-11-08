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

import {FlashMessageHandler} from "../../../core/FlashMessageHandler.js";

/**
 * Corrects, shortens or translates the text of the selected textbox with the model of the user for texts. The new
 * text is one step of the history: undo brings the old text back.
 */
export class TextCorrectionService
{
	/** the width of an average character in relation to the font size, enough for an estimate */
	static CHARACTER_WIDTH = 0.55;

	#fabricWrapper;
	#templatesService;
	#waitOverlay;
	#composerContext;
	#viewportService;

	constructor(fabricWrapper, templatesService, waitOverlay, composerContext, viewportService)
	{
		this.#fabricWrapper    = fabricWrapper;
		this.#templatesService = templatesService;
		this.#waitOverlay      = waitOverlay;
		this.#composerContext  = composerContext;
		this.#viewportService  = viewportService;
	}

	isText(object)
	{
		return ["text", "i-text", "textbox"].includes(object.type);
	}

	async correctActiveText()
	{
		await this.#rewriteActiveText((text) => this.#templatesService.correctText(text), "text_corrected");
	}

	/**
	 * Shortens the text so that it fits from its top to the bottom edge of the template in the width of its box.
	 */
	async shortenActiveText()
	{
		const object = this.#fabricWrapper.getActiveObject();
		if (!object || !this.isText(object))
			return;

		const maxLength = this.#estimateCapacity(object);
		await this.#rewriteActiveText((text) => this.#templatesService.shortenText(text, maxLength), "text_shortened");
	}

	async translateActiveText(locale)
	{
		await this.#rewriteActiveText((text) => this.#templatesService.translateText(text, locale), "text_translated");
	}

	/**
	 * The characters which fit: the lines between the top of the box and the bottom edge of the template, times the
	 * characters of a line of the box. The box itself grows with the text, it has no height of its own.
	 */
	#estimateCapacity(object)
	{
		const fontSize       = object.fontSize * (object.scaleY ?? 1);
		const lineHeight     = fontSize * (object.lineHeight ?? 1.16);
		const charsPerLine   = Math.max(1, Math.floor((object.width * (object.scaleX ?? 1)) / (fontSize * TextCorrectionService.CHARACTER_WIDTH)));
		// in the units of the template, without the zoom of the composer
		const availableSpace = Math.max(lineHeight, this.#viewportService.height - object.getBoundingRect(true).top);
		const lines          = Math.max(1, Math.floor(availableSpace / lineHeight));

		return charsPerLine * lines;
	}

	async #rewriteActiveText(request, successKey)
	{
		const object = this.#fabricWrapper.getActiveObject();
		if (!object || !this.isText(object) || object.text.trim() === "")
			return;

		this.#waitOverlay.start();
		try
		{
			const result = await request(object.text);
			if (result.success === false)
				throw new Error(result.error_message);

			if (result.text === object.text)
				return;

			if (object.isEditing)
				object.exitEditing();

			// the styles of single characters belong to positions in the old text
			object.set({text: result.text, styles: {}});
			this.#fabricWrapper.historySaveAction();
			this.#fabricWrapper.renderAll();
			new FlashMessageHandler().showSuccess(this.#composerContext.getLangByKey(successKey));
		}
		catch (e)
		{
			new FlashMessageHandler().showError(e.message);
		}
		finally
		{
			this.#waitOverlay.stop();
		}
	}
}
