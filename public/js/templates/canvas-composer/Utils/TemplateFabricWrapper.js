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

import {FabricWrapper} from "../../../core/canvas/FabricWrapper.js";
import {CanvasHistory} from "./CanvasHistory.js";
import {ComposerEventBus} from "./ComposerEventBus.js";

/**
 * The canvas of the template composer: a new size counts as a change, the history of fabric-history,
 * the JSON of a template and its image.
 */
export class TemplateFabricWrapper extends FabricWrapper
{
	/** the properties of a template besides the ones of fabric.js, same in misc/template-generator/generate.html */
	static PROPERTIES = ["mediaId", "fileName", "placeholder", "lockMovementX", "lockMovementY", "lockRotation", "lockScalingX", "lockScalingY"];

	#history;

	constructor(fabricCanvas)
	{
		super(fabricCanvas);
		this.#history = new CanvasHistory(fabricCanvas, [...TemplateFabricWrapper.PROPERTIES, "evented", "selectable"]);
	}

	setWidth(width)
	{
		super.setWidth(width);
		this.markChanged();
	}

	setHeight(height)
	{
		super.setHeight(height);
		this.markChanged();
	}

	/**
	 * beforeHistory: e.g. an image in the crop mode keeps its cut first.
	 */
	async undo()
	{
		ComposerEventBus.dispatchEvent(new CustomEvent("beforeHistory"));
		await this.#history.undo();
	}

	async redo()
	{
		ComposerEventBus.dispatchEvent(new CustomEvent("beforeHistory"));
		await this.#history.redo();
	}

	historySaveAction()
	{
		this.#history.save();
	}

	pauseHistory()
	{
		this.#history.pause();
	}

	resumeHistory()
	{
		this.#history.resume();
	}

	/**
	 * The content of the template: toJSON() of fabric.js takes no own properties since version 6.
	 */
	toTemplateJSON()
	{
		return this.fabricCanvas.toObject(TemplateFabricWrapper.PROPERTIES);
	}

	async load(jsonContent)
	{
		await this.fabricCanvas.loadFromJSON(jsonContent);
		// the widths of the characters of the fonts loaded before
		fabric.cache.clearFontCache();
		this.#history.reset();
	}

	/**
	 * The color of the template behind all objects, white without one.
	 */
	getBackgroundColor()
	{
		const color = this.fabricCanvas.backgroundColor;
		return (typeof color === "string" && color !== "") ? color : "#ffffff";
	}

	/**
	 * While the color is chosen: no step in the history yet, see commitBackgroundColor().
	 */
	setBackgroundColor(color)
	{
		this.fabricCanvas.backgroundColor = color;
		this.renderAll();
	}

	commitBackgroundColor()
	{
		this.historySaveAction();
		this.changed();
	}

	/**
	 * The image with the background color of the template, the given color only for a template without one.
	 * The canvas keeps its own color: else the next save had the fallback in the content.
	 */
	toBase64Image(format, quality, fallbackBackgroundColor)
	{
		const ownColor = this.fabricCanvas.backgroundColor;
		if (typeof ownColor !== "string" || ownColor === "")
			this.fabricCanvas.backgroundColor = fallbackBackgroundColor;

		try
		{
			return this.fabricCanvas.toDataURL({
				format: format,
				quality: quality
			});
		}
		finally
		{
			this.fabricCanvas.backgroundColor = ownColor;
		}
	}
}
