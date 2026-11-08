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

import {ComposerEventBus} from "../Utils/ComposerEventBus.js";
import {FlashMessageHandler} from "../../../core/FlashMessageHandler.js";

/**
 * Suggests pictures of the mediapool for the selected image: the AI of the user finds them by the texts of the
 * template. The user clicks one and it replaces the image, filling its area like "replace image".
 */
export class ImageSuggestionController
{
	#dialog = document.getElementById("imageSuggestionDialog");
	#fabricWrapper;
	#templatesService;
	#insertService;
	#waitOverlay;
	#target = null;

	constructor(fabricWrapper, templatesService, insertService, waitOverlay)
	{
		this.#fabricWrapper    = fabricWrapper;
		this.#templatesService = templatesService;
		this.#insertService    = insertService;
		this.#waitOverlay      = waitOverlay;
	}

	init()
	{
		// without an LLM of the user there is no dialog
		if (this.#dialog === null)
			return;

		ComposerEventBus.addEventListener("suggestImageForReplace", async () => await this.#suggest());
		this.#dialog.querySelector(".close-button").addEventListener("click", () => this.#dialog.close());
	}

	async #suggest()
	{
		const object = this.#fabricWrapper.getActiveObject();
		if (!object || object.type !== "image")
			return;

		this.#target = object;
		this.#waitOverlay.start();
		try
		{
			const result = await this.#templatesService.suggestImages(this.#collectTexts());
			if (result.success === false)
				throw new Error(result.error_message);

			this.#showImages(result.images);
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

	/**
	 * The visible texts of the template, from top to bottom.
	 */
	#collectTexts()
	{
		return this.#fabricWrapper.getObjects()
			.filter(object => ["text", "i-text", "textbox"].includes(object.type) && object.visible !== false && object.text.trim() !== "")
			.sort((a, b) => a.top - b.top)
			.map(object => object.text.trim())
			.join("\n");
	}

	#showImages(images)
	{
		const list = this.#dialog.querySelector(".image-suggestions");
		list.replaceChildren(...images.map(image => this.#buildEntry(image)));
		this.#dialog.showModal();
	}

	#buildEntry(image)
	{
		const entry   = document.createElement("li");
		const button  = document.createElement("button");
		const picture = document.createElement("img");
		button.type   = "button";
		button.title  = image.filename;
		picture.src   = image.thumbnail;
		picture.alt   = image.filename;
		button.appendChild(picture);
		button.addEventListener("click", async () => await this.#replace(image));
		entry.appendChild(button);

		return entry;
	}

	async #replace(image)
	{
		this.#dialog.close();
		if (!this.#fabricWrapper.getObjects().includes(this.#target))
			return;

		try
		{
			this.#fabricWrapper.setActiveObject(this.#target);
			await this.#insertService.replaceImage(image.media_id, image.original, image.file_name);
		}
		catch (e)
		{
			new FlashMessageHandler().showError(e.message ?? String(e));
		}
	}
}
