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
	#zoomPercent = document.getElementById("zoomPercent");
	#zoomSlider = document.getElementById("zoomSlider");
	#resolutionWidth = document.getElementById("imageWidth");
	#resolutionHeight = document.getElementById("imageHeight");

	constructor() {}

	get zoomPercent()
	{
		return this.#zoomPercent;
	}

	setZoomPercentHtml(value)
	{
		this.#zoomPercent.innerHTML = value;
	}

	get zoomSlider()
	{
		return this.#zoomSlider;
	}

	getZoomSliderValue()
	{
		return this.#zoomSlider.value;
	}

	setZoomSliderValue(value)
	{
		this.#zoomSlider.value = value;
	}

	get resolutionWidth()
	{
		return this.#resolutionWidth;
	}

	getResolutionWidthValue()
	{
		return this.#resolutionWidth.value;
	}

	setResolutionWidth(value)
	{
		this.#resolutionWidth.value = value;
	}

	get resolutionHeight()
	{
		return this.#resolutionHeight;
	}

	getResolutionHeightValue()
	{
		return this.#resolutionHeight.value;
	}

	setResolutionHeight(value)
	{
		this.#resolutionHeight.value = value;
	}
}
