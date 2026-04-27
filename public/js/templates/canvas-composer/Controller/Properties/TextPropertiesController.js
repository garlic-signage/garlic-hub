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

export class TextPropertiesController
{
	#textPropertiesView;
	#textPropertiesService;
	#fontLoader;

	constructor(textPropertiesView, textPropertiesService, fontLoader)
	{
		this.#textPropertiesView = textPropertiesView;
		this.#textPropertiesService = textPropertiesService;
		this.#fontLoader = fontLoader;

		this.#textPropertiesView.textAlign.addEventListener("click", () =>
		{
			this.#textPropertiesService.setTextAlign();
			this.#textPropertiesView.setTextAlignValue(this.#textPropertiesService.getTextAlign())

		});
		this.#textPropertiesView.textBold.addEventListener("click", () =>
		{
			this.#textPropertiesService.setTextBold();
			this.#textPropertiesView.setTextBoldValue(this.#textPropertiesService.getTextBold());
		});
		this.#textPropertiesView.textItalic.addEventListener("click", () =>
		{
			this.#textPropertiesService.setTextItalic();
			this.#textPropertiesView.setTextItalicValue(this.#textPropertiesService.getTextItalic());
		});
		this.#textPropertiesView.textUnderline.addEventListener("click", () =>
		{
			this.#textPropertiesService.setTextUnderline();
			this.#textPropertiesView.setTextUnderlineValue(this.#textPropertiesService.getTextUnderline());
		});

		this.buildFontDropdown();

		this.#textPropertiesView.fontSize.addEventListener("input", () =>
		{
			const value = parseInt(this.#textPropertiesView.getFontSizeValue());
			this.#textPropertiesService.setTextFontSize(value);
		})
	}

	activate()
	{
		this.#textPropertiesView.setfontFamilyValue(this.#textPropertiesService.getTextFontFamily());
		this.#textPropertiesView.setFontSizeValue(this.#textPropertiesService.getTextFontSize());
		this.#textPropertiesView.setTextAlignValue(this.#textPropertiesService.getTextAlign());
		this.#textPropertiesView.setTextBoldValue(this.#textPropertiesService.getTextBold());
		this.#textPropertiesView.setTextItalicValue(this.#textPropertiesService.getTextItalic());
		this.#textPropertiesView.setTextUnderlineValue(this.#textPropertiesService.getTextUnderline());
		this.#textPropertiesView.show();
	}

	deactivate()
	{
		this.#textPropertiesView.hide();
	}

	buildFontDropdown()
	{

		this.#textPropertiesView.clearFontDropdown();
		if (!Array.isArray(this.#fontLoader.fontsList))
			return;

		for (let i = 0; i < this.#fontLoader.fontsList.length; i++)
		{
			let div = document.createElement("div")
			div.classList.add("template_edit_dropdown_content_font");
			div.classList.add("template_edit_dropdown_content_hover");
			div.innerHTML = this.#fontLoader.fontsList[i].preview
			div.addEventListener("click", () => {
				this.loadFontFamily(i);
			});
			this.#textPropertiesView.fontsDropdown.appendChild(div)
		}
	}

	loadFontFamily(fontId)
	{
		const callback = () =>
		{
			const fontName = this.#fontLoader.fontsList[fontId].name;
			this.#textPropertiesService.setTextFontFamily(fontName);
			this.#textPropertiesView.setfontFamilyValue(fontName);

			this.#textPropertiesService.justUpdateCanvas();
		};

		this.#fontLoader.loadFontFace(fontId, callback)
	}

}
