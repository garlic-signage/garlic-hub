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

// fabric.js stores numbers with 2 decimals: a picture of 4000 px scaled by 0.123 would change its size by up to 4 %
// with every save. Same in misc/template-generator/generate.html.
fabric.Object.NUM_FRACTION_DIGITS = 6;

/**
 * The canvas of the template composer: a new size counts as a change, the history of fabric-history,
 * the JSON of a template and its image.
 */
export class TemplateFabricWrapper extends FabricWrapper
{
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

	undo()
	{
		this.fabricCanvas.undo();
	}

	redo()
	{
		this.fabricCanvas.redo();
	}

	historySaveAction()
	{
		this.fabricCanvas._historySaveAction();
	}

	toTemplateJSON()
	{
		return this.fabricCanvas.toJSON([
			"mediaId",
			"fileName",
			"placeholder",
			"lockMovementX",
			"lockMovementY",
			"lockRotation",
			"lockScalingX",
			"lockScalingY",
		]);
	}

	load(jsonContent)
	{
		return new Promise((resolve) => {
			this.fabricCanvas.loadFromJSON(jsonContent, () => {
				fabric.util.clearFabricFontCache();
				fabric.charWidthsCache = {};
				fabric.Canvas.prototype.historyUndo = [];
				fabric.Canvas.prototype.historyRedo = [];
				resolve();
			});
		});
	}

	toBase64Image(format, quality, backgroundColor)
	{
		this.fabricCanvas.backgroundColor = backgroundColor;
		return this.fabricCanvas.toDataURL({
			format: format,
			quality: quality
		});
	}
}
