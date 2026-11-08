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

export class GroupPropertiesService extends BasePropertyService
{
	constructor(fabricWrapper)
	{
		super(fabricWrapper);
	}

	/**
	 * The selected objects become a group at the place of the topmost of them (fabric.js 6 has no toGroup()).
	 */
	toGroup(selection)
	{
		const canvas  = this.fabricWrapper.fabricCanvas;
		const objects = selection.removeAll();
		const index   = Math.max(...objects.map(object => canvas.getObjects().indexOf(object)));
		canvas.discardActiveObject();
		const group = new fabric.Group(objects);
		canvas.remove(...objects);
		canvas.insertAt(Math.min(index, canvas.getObjects().length), group);
		canvas.setActiveObject(group);
		this._updateCanvas(group);
	}

	/**
	 * The objects of the group take its place on the canvas and are selected (fabric.js 6 has no
	 * toActiveSelection()).
	 */
	toActiveSelection(group)
	{
		const canvas  = this.fabricWrapper.fabricCanvas;
		const index   = canvas.getObjects().indexOf(group);
		const objects = group.removeAll();
		canvas.remove(group);
		canvas.insertAt(index, ...objects);
		canvas.setActiveObject(new fabric.ActiveSelection(objects, {canvas: canvas}));
		this._updateCanvas(objects[0]);
	}


}
