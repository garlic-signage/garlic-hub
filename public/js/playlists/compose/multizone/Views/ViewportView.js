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

export class ViewportView
{
	#screenWidth  = document.getElementById("screen_width");
	#screenHeight = document.getElementById("screen_height");
	#exportUnit   = document.getElementById("export_unit");
	#slider       = document.getElementById("slider");
	#zoomPercent  = document.getElementById("percent");

	get screenWidth()  { return this.#screenWidth; }
	get screenHeight() { return this.#screenHeight; }
	get exportUnit()   { return this.#exportUnit; }
	get slider()       { return this.#slider; }

	show(width, height, exportUnit, zoom)
	{
		this.#screenWidth.value  = width;
		this.#screenHeight.value = height;
		this.#exportUnit.value   = exportUnit;
		this.#slider.value       = zoom;
		this.showZoomPercent();
	}

	showZoomPercent()
	{
		this.#zoomPercent.textContent = this.#slider.value + ' %';
	}
}
