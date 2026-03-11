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

export class SaveService
{
	#fabricWrapper;
	#templatesService;
	#bmpDitherFactory;
	#waitOverlay;

	constructor(fabricWrapper, templatesService, bmpDitherFactory, waitOverlay)
	{
		this.#fabricWrapper    = fabricWrapper;
		this.#templatesService = templatesService;
		this.#bmpDitherFactory = bmpDitherFactory;
		this.#waitOverlay      = waitOverlay;
	}

	async save(canvas)
	{
		this.#waitOverlay.start();

		if (!this.#allowed.includes(this.#imageFormat))
			this.#imageFormat = "jpg";
		if (this.#imageQuality < 1 || this.#imageQuality > 100)
			this.#imageQuality = 80;

		const save = this.#prepareCanvasForSave(canvas);

		const image = await this.#createImage(save.canvas);

		this.#canvasView.scaleCanvas();

		try
		{
			if (this.#itemId > 0) // only when in playlist
				await this.#templatesService.savePlaylistItemContent(this.#itemId, save.content, image);
			else
				await this.#templatesService.saveTemplateContent(this.#templateId, save.content, image);
		}
		catch(e)
		{
			console.error(e);
		}
		this.#waitOverlay.stop();
	}

	async #createImage(canvas)
	{
		let format = this.#imageFormat;
		if (format === 'jpg')
			format = 'jpeg';
		if (format === 'bmp')
		{
			const base64DataUrl = canvas.toDataURL({
				format: 'jpeg',
				quality: 100,
				backgroundColor: "#ffffff"
			});
			const bmp = this.#bmpDitherFactory.create();
			return await bmp.convert(base64DataUrl);
		}

		const backgroundColor = ['png', 'webp'].includes(format) ? null : '#ffffff';
		const quality = this.#imageQuality / 100;

		return canvas.toDataURL({
			format: format,
			quality: quality,
			backgroundColor: backgroundColor
		});
	}

	#prepareCanvasForSave(canvas)
	{
		// as coping an object in JS is ridiculous complicated we need to set Zoom to 100 and then revert it to original values
		// change Zoom to 100% otherwise current zoom factor will used
		canvas.setZoom(1);
		canvas.setWidth(this.#canvasView.width)
		canvas.setHeight(this.#canvasView.height);

		let save = canvas.toJSON(["mediaId", "fileName"]);
		save["viewport"] = { "width": this.#canvasView.width, "height": this.#canvasView.height, "scale": 100 };

		return {"canvas": canvas, "content": JSON.stringify(save)};
	}

}
