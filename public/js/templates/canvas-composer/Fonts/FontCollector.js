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

export class FontCollector
{
	#fontLoader;
	used_fonts = [];


	constructor(fontLoader)
	{
		this.#fontLoader = fontLoader;
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
				let font_id = this.#fontLoader.findFontListKey(this.used_fonts[i]);
				if (font_id !== -1 && !this.#fontLoader.isFontLoaded(font_id))
				{
					await this.#fontLoader.loadFontFaceAsync(font_id);
				}
			}
			resolve();
		});
	}
}
