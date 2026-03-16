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

import {BasePropertyService} from "./BasePropertyService.js";

export class SelectivePropertiesService extends BasePropertyService
{
	constructor(fabricWrapper)
	{
		super(fabricWrapper);
	}

	getFillColor()
	{
		const object = this._getActiveObject();

		if (object.type !== "i-text" && object.type !== "text" && object.type !== "textbox")
			return object.fill

		const styles = object.getSelectionStyles(object.isEditing ? object.selectionStart : 0, object.isEditing ? object.selectionEnd : 1, true)
		return styles && styles[0] ? styles[0].fill : object.fill
	}

	setFillColor(color)
	{
		const object = this._getActiveObject();
		if (object.type === "i-text" || object.type === "text" || object.type === "textbox")
		{
			object.setSelectionStyles({ fill: color }, object.selectionStart === object.selectionEnd ? 0 : object.selectionStart, object.selectionStart === object.selectionEnd ? object.text.length : object.selectionEnd)
		}
		else
		{
			object.set("fill", color)
		}
		this._updateCanvas(object);
	}

}
