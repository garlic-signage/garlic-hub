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

export class ShadowPropertiesView
{
	#shadowColor   = document.getElementById("shadowColor");
	#shadowOffsetX = document.getElementById("shadowOffsetX");
	#shadowOffsetY = document.getElementById("shadowOffsetY");
	#shadowBlur    = document.getElementById("shadowBlur");
	#deleteShadow  = document.getElementById("deleteShadow");
	#createShadow  = document.getElementById("createShadow");

	constructor() {}


	get shadowColor()
	{
		return this.#shadowColor;
	}

	get shadowOffsetX()
	{
		return this.#shadowOffsetX;
	}

	get shadowOffsetY()
	{
		return this.#shadowOffsetY;
	}

	get shadowBlur()
	{
		return this.#shadowBlur;
	}

	get deleteShadow()
	{
		return this.#deleteShadow;
	}

	get createShadow()
	{
		return this.#createShadow;
	}

	toggleShadowVisibility(isVisible)
	{
		this.#deleteShadow.style.display = isVisible ? "block" : "none";
		this.#createShadow.style.display = isVisible ? "none" : "block";

		this.#shadowColor.disabled = !isVisible;
		this.#shadowOffsetX.disabled = !isVisible;
		this.#shadowOffsetY.disabled = !isVisible;
		this.#shadowBlur.disabled = !isVisible;
	}

	getShadowColorValue()
	{
		return this.#shadowColor.value;
	}

	setShadowColorValue(value)
	{
		this.#shadowColor.value = value;
	}

	getShadowOffsetXValue()
	{
		return this.#shadowOffsetX.value;
	}

	setShadowOffsetXValue(value)
	{
		this.#shadowOffsetX.value = value;
	}

	getShadowOffsetYValue()
	{
		return this.#shadowOffsetY.value;
	}

	setShadowOffsetYValue(value)
	{
		this.#shadowOffsetY.value = value;
	}

	getShadowBlurValue()
	{
		return this.#shadowBlur.value;
	}

	setShadowBlurValue(value)
	{
		this.#shadowBlur.value = value;
	}
}