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

export class SaveService
{
	#fabricWrapper;
	#templatesService;
	#bmpDitherFactory;
	#waitOverlay;
	#allowed = ["jpg", "png", "webp", "bmp"];
	#imageData = {"format": "jpeg", "quality": 80 };

	constructor(fabricWrapper, templatesService, bmpDitherFactory, waitOverlay)
	{
		this.#fabricWrapper    = fabricWrapper;
		this.#templatesService = templatesService;
		this.#bmpDitherFactory = bmpDitherFactory;
		this.#waitOverlay      = waitOverlay;
	}

	hasChanged()
	{
		return this.#fabricWrapper.hasChanged;
	}

	validateImageData(format, quality)
	{
		if (quality < 1 || quality > 100)
			quality = 80;

		if (!this.#allowed.includes(format))
			format = "jpg";

		if (format === 'jpg')
			format = 'jpeg';

		this.#imageData = {"format": format, "quality": quality };
	}

	async save(originalWidth, originalHeight, isPlaylist, id)
	{
		this.#waitOverlay.start();
		const save = this.#prepareCanvasForSave(originalWidth, originalHeight);
		const image = await this.#createImage();

		try
		{
			if (isPlaylist) // only when in playlist
				await this.#templatesService.savePlaylistItemContent(id, save, image);
			else
				await this.#templatesService.saveTemplateContent(id, save, image);

			ComposerEventBus.dispatchEvent(new CustomEvent("canvasUpdated"));
			this.#fabricWrapper.resetChange();
		}
		catch(e)
		{
			console.error(e);
		}
		this.#waitOverlay.stop();
	}

	async #createImage()
	{
		if (this.#imageData.format === 'bmp')
		{
			const base64DataUrl = this.#fabricWrapper.toBase64Image('jpeg', 100, "#ffffff");
			const bmp = this.#bmpDitherFactory.create();
			return await bmp.convert(base64DataUrl);
		}

		const backgroundColor = ['png', 'webp'].includes(this.#imageData.format) ? null : '#ffffff';

		return this.#fabricWrapper.toBase64Image(
			this.#imageData.format,
			this.#imageData.quality / 100,
			backgroundColor
		);
	}

	#prepareCanvasForSave(originalWidth, originalHeight)
	{
		// as coping an object in JS is ridiculous complicated we need to set Zoom to 100 and then revert it to original values
		// change Zoom to 100% otherwise current zoom factor will used
		this.#fabricWrapper.setZoom(1);
		this.#fabricWrapper.setWidth(originalWidth)
		this.#fabricWrapper.setHeight(originalHeight);

		let save = this.#fabricWrapper.toTemplateJSON();
		save["viewport"] = { "width": originalWidth, "height": originalHeight, "scale": 100 };

		return JSON.stringify(save);
	}

}
