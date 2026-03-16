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

export class GlobalPropertiesView
{
	#globalProperties = document.getElementById("globalProperties");

	#opacity        = document.getElementById("opacity")
	#opacityDisplay = document.getElementById("opacityDisplay")
	#strokeColor    = document.getElementById("strokeColor")
	#strokeWidth    = document.getElementById("strokeWidth")
	#strokeWidthDisplay  = document.getElementById("strokeWidthDisplay")

	#alignLeft   = document.getElementById("alignLeft")
	#alignCenter = document.getElementById("alignCenter")
	#alignRight  = document.getElementById("alignRight")
	#alignTop    = document.getElementById("alignTop")
	#alignMiddle = document.getElementById("alignMiddle")
	#alignBottom = document.getElementById("alignBottom")


	constructor(){}

	hideGlobalProperties()
	{
		this.#globalProperties.style.display = "none";
	}

	showGlobalProperties()
	{
		this.#globalProperties.style.display = "flex";
	}

	get opacity()
	{
		return this.#opacity;
	}

	getOpacityValue()
	{
		return this.#opacity.value;
	}

	setOpacityValue(value)
	{
		this.#opacity.value = value;
		this.#opacityDisplay.innerHTML = value;
	}

	get strokeColor()
	{
		return this.#strokeColor;
	}

	setStrokeColorValue(value)
	{
		this.#strokeColor.value = value;
	}

	getStrokeColorValue()
	{
		return this.#strokeColor.value;
	}

	get strokeWidth()
	{
		return this.#strokeWidth;
	}

	setStrokeWidthValue(value)
	{
		this.#strokeWidth.value = value;
		this.#strokeWidthDisplay.innerHTML = value;
	}

	getStrokeWidthValue()
	{
		return this.#strokeWidth.value;
	}

	get alignLeft()
	{
		return this.#alignLeft;
	}

	get alignCenter()
	{
		return this.#alignCenter;
	}

	get alignRight()
	{
		return this.#alignRight;
	}

	get alignTop()
	{
		return this.#alignTop;
	}

	get alignMiddle()
	{
		return this.#alignMiddle;
	}

	get alignBottom()
	{
		return this.#alignBottom;
	}
}
