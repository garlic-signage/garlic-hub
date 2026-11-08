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

/**
 * Guidelines while an object is moved or scaled (extension AligningGuidelines of fabric.js): it snaps to the edges
 * and middles of the other objects and of the template itself, and a line shows where.
 * Not for an image in the crop mode: dragging moves the picture in its frame there.
 */
export class TemplateGuidelines extends fabricExtensions.AligningGuidelines
{
	constructor(canvas)
	{
		super(canvas, {color: "rgba(217, 119, 6, 0.9)", margin: 6});
	}

	moving(event)
	{
		if (event.target?.isCropping === true)
			return;

		super.moving(event);
	}

	/**
	 * Not while the canvas renders into a picture (save, design check): it has no top layer then.
	 */
	beforeRender()
	{
		if (this.canvas.contextTop)
			super.beforeRender();
	}

	afterRender()
	{
		if (this.canvas.contextTop)
			super.afterRender();
	}

	/**
	 * The other objects and the template as a frame of its own size.
	 */
	getObjectsByTarget(target)
	{
		const objects = super.getObjectsByTarget(target);
		const zoom    = this.canvas.getZoom();
		objects.add(new fabric.Rect({
			left: 0, top: 0, strokeWidth: 0,
			width: this.canvas.getWidth() / zoom,
			height: this.canvas.getHeight() / zoom
		}));

		return objects;
	}
}
