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

export class SnapView
{
	#snapToGrid = document.getElementById('snapToGrid');
	#canvasContext;


	constructor(canvasContext)
	{
		this.#canvasContext = canvasContext;
	}

	get snapToGrid()
	{
		return this.#snapToGrid;
	}

	getSnapToGridValue()
	{
		return this.#snapToGrid.value;
	}



	drawGrid(gridData)
	{
		const gridSize = parseInt(this.#snapToGrid.value) * gridData.zoom;

		this.#canvasContext.strokeStyle = '#e0e0e0';
		this.#canvasContext.lineWidth = 0.5;

		for (let x = 0; x < gridData.width; x += gridSize)
		{
			this.#canvasContext.beginPath();
			this.#canvasContext.moveTo(x, 0);
			this.#canvasContext.lineTo(x, gridData.height);
			this.#canvasContext.stroke();
		}
		for (let y = 0; y < gridData.height; y += gridSize)
		{
			this.#canvasContext.beginPath();
			this.#canvasContext.moveTo(0, y);
			this.#canvasContext.lineTo(gridData.width, y);
			this.#canvasContext.stroke();
		}
	}

}
