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

export class TextPropertiesView
{
	#fontsDropdown  = document.getElementById("fontsDropdown")
	#textProperties = document.getElementById("textProperties");
	#fontFamily     = document.getElementById("fontFamily")
	#fontSize       = document.getElementById("fontSize")
	#textAlign ;
	#textBold;
	#textItalic;
	#textUnderline;

	constructor(toggleButtonFactory)
	{
		this.#textAlign     = toggleButtonFactory.create(document.getElementById("textAlign"))
		this.#textBold      = toggleButtonFactory.create(document.getElementById("textBold"))
		this.#textItalic    = toggleButtonFactory.create(document.getElementById("textItalic"))
		this.#textUnderline = toggleButtonFactory.create(document.getElementById("textUnderline"))
	}

	show()
	{
		this.#textProperties.style.display = "flex";
	}

	hide()
	{
		this.#textProperties.style.display = "none";
	}

	get fontsDropdown()
	{
		return this.#fontsDropdown;
	}

	clearFontDropdown()
	{
		while (this.#fontsDropdown.children.length > 1)
		{
			this.#fontsDropdown.removeChild(this.#fontsDropdown.lastChild);
		}
	}


	setfontFamilyValue(value)
	{
		this.#fontFamily.innerHTML = value;
	}

	getFontSizeValue()
	{
		return this.#fontSize.value
	}


	setFontSizeValue(value)
	{
		this.#fontSize.value = value
	}

	get textAlign()
	{
		return this.#textAlign.getElement();
	}

	setTextAlignValue(value)
	{
		this.#textAlign.update('display', value);
	}

	get textBold()
	{
		return this.#textBold.getElement();
	}

	setTextBoldValue(value)
	{
		this.#textBold.update('active', value)
	}

	get textItalic()
	{
		return this.#textItalic.getElement();
	}

	setTextItalicValue(value)
	{
		this.#textItalic.update('active', value)

	}

	get textUnderline()
	{
		return this.#textUnderline.getElement();
	}

	setTextUnderlineValue(value)
	{
		this.#textUnderline.update('active', value)
	}

	get fontSize()
	{
		return this.#fontSize;
	}
}
