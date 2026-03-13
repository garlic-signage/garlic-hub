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

export class TextProperties {
	MyCanvasView = {};
	MyFontHandler = {};
	property = document.getElementById("text_properties");
	fontFamily = document.getElementById("font_family")
	textAlign ;
	textBold;
	textItalic;
	textUnderline;
	used_fonts = [];

	constructor(MyCanvasView, MyFontHandler, toggleButtonFactory)
	{
		this.MyCanvasView = MyCanvasView;
		this.MyFontHandler = MyFontHandler;

		this.textAlign     = toggleButtonFactory.create(document.getElementById("text_align"))
		this.textBold      = toggleButtonFactory.create(document.getElementById("text_bold"))
		this.textItalic    = toggleButtonFactory.create(document.getElementById("text_italic"))
		this.textUnderline = toggleButtonFactory.create(document.getElementById("text_underline"))


		this.buildFontDropdown();
	}



	collectUsedFontsFromSelection(object)
	{
		if (object.fontFamily !== undefined && !this.used_fonts.includes(object.fontFamily))
			this.used_fonts.push(object.fontFamily);

		let styles_length =  Object.entries(object.styles).length;

		for (let i = 0; i < styles_length; i++)
		{
			if (object.styles[i] !== undefined &&
				object.styles[i].style !== undefined &&
				object.styles[i].style.fontFamily !== undefined &&
				!this.used_fonts.includes(object.styles[i].style.fontFamily)
			)
			{
				this.used_fonts.push(object.styles[i].style.fontFamily);
			}

			// probably for compatibility
			let styles_inner_length =  Object.entries(object.styles[i]).length;
			for (let j = 0; j < styles_inner_length; j++)
			{
				if (object.styles[i][j] !== undefined &&
					object.styles[i][j].style !== undefined &&
						object.styles[i][j].style.fontFamily !== undefined &&
							!this.used_fonts.includes(object.styles[i][j].style.fontFamily))
				{
					this.used_fonts.push(object.styles[i][j].style.fontFamily);
				}
			}
		}
	}

	async preloadUsedFonts()
	{
		return new Promise(async (resolve) => {
			for (let i = 0; i < this.used_fonts.length; i++)
			{
				let font_id = this.MyFontHandler.findFontListKey(this.used_fonts[i]);
				if (font_id !== -1 && !this.MyFontHandler.isFontLoaded(font_id))
				{
					await this.MyFontHandler.loadFontFaceAsync(font_id);
				}
			}
			resolve();
		});
	}

	//============== private methods ===========================================================

	setTextAlign(object)
	{
		if (!object) return
		const positions = ["left", "center", "right", "left"]
		const currentIndex = positions.findIndex((v) => v === object.textAlign)
		const nextAlign = positions[currentIndex + 1]
		this.textAlign.update("display", nextAlign)
		object.set("textAlign", nextAlign)
	}

	buildFontDropdown()
	{
		let fontsDropdown = document.getElementById("select_fonts_dropdown")
		while (fontsDropdown.children.length > 1)
		{
			fontsDropdown.removeChild(fontsDropdown.lastChild);
		}

		if (!Array.isArray(this.MyFontHandler.fontsList))
			return;

		for (let i = 0; i < this.MyFontHandler.fontsList.length; i++)
		{
			let div = document.createElement("div")
			div.classList.add("template_edit_dropdown_content_font");
			div.classList.add("template_edit_dropdown_content_hover");
			div.innerHTML = this.MyFontHandler.fontsList[i].preview
			div.addEventListener("click", () => {
				this.loadFontFamily(i);
			});
			fontsDropdown.appendChild(div)
		}
	}

	loadFontFamily(font_id)
	{
		let self = this;
		let MyCallBack = function () {
			let font_name = self.MyFontHandler.fontsList[font_id].name;
			self.setTextFontFamily(self.MyCanvasView.getCanvas().getActiveObject(), font_name);
			self.fontFamily.innerHTML = font_name;
			self.MyCanvasView.getCanvas().fire('object:modified', { target: self.MyCanvasView.getCanvas().getActiveObject() })
			self.MyCanvasView.renderCanvas();
		}

		this.MyFontHandler.loadFontFace(font_id, MyCallBack)
	}

}
