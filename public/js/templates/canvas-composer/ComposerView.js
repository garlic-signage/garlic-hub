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

export class ComposerView  extends EventTarget
{
	#percent = document.getElementById("percent");
	#slider = document.getElementById("slider");
	#saveButton = document.getElementById("save_template");
	#resetButton = document.getElementById("save_template");
	#closeButton = document.getElementById("save_template");
	#canvasWrap = document.getElementById("canvas_wrap");
	#insertCircle = document.getElementById("object_add_circle");
	#insertTriangle = document.getElementById("object_add_triangle");
	#insertRectangle = document.getElementById("object_add_rectangle");
	#insertPolygon = document.getElementById("object_add_polygon");
	#insertHexagon = document.getElementById("object_add_hexagon");
	#insertOctagon = document.getElementById("object_add_octagon");
	#undo = document.getElementById("undo");
	#redo = document.getElementById("redo");
	#resolutionWidth = document.getElementById("image_width");
	#resolutionHeight = document.getElementById("image_height");
	#exportFormat = document.getElementById("image_width");
	#exportQuality = document.getElementById("image_height");
	#lang;

	constructor(lang)
	{
		super();
		this.#lang = lang;
	}

}
