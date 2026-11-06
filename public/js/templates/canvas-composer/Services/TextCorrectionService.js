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
 * Corrects the text of the selected textbox with the own LLM of the user. The correction is one step of
 * the history: undo brings the old text back.
 */
export class TextCorrectionService
{
	#fabricWrapper;
	#templatesService;
	#waitOverlay;
	#composerContext;

	constructor(fabricWrapper, templatesService, waitOverlay, composerContext)
	{
		this.#fabricWrapper    = fabricWrapper;
		this.#templatesService = templatesService;
		this.#waitOverlay      = waitOverlay;
		this.#composerContext  = composerContext;
	}

	isText(object)
	{
		return ["text", "i-text", "textbox"].includes(object.type);
	}

	async correctActiveText()
	{
		const object = this.#fabricWrapper.getActiveObject();
		if (!object || !this.isText(object) || object.text.trim() === "")
			return;

		this.#waitOverlay.start();
		try
		{
			const result = await this.#templatesService.correctText(object.text);
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
			new FlashMessageHandler().showSuccess(this.#composerContext.getLangByKey("text_corrected"));
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
