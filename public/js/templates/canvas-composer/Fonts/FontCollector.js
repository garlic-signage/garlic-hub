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

	/**
	 * The font of a text and the fonts of its characters. The styles of the characters are stored as ranges since
	 * fabric.js 6 ([{start, end, style}]), before per line and character ({line: {char: style}}).
	 */
	collectUsedFontsFromSelection(object)
	{
		this.#addFont(object.fontFamily);

		const styles = object.styles ?? {};
		for (const entry of Object.values(styles))
		{
			if (entry === null || typeof entry !== "object")
				continue;

			if (entry.style !== undefined)
				this.#addFont(entry.style.fontFamily);
			else
			{
				for (const style of Object.values(entry))
					this.#addFont(style?.fontFamily);
			}
		}
	}

	#addFont(fontFamily)
	{
		if (typeof fontFamily === "string" && fontFamily !== "" && !this.used_fonts.includes(fontFamily))
			this.used_fonts.push(fontFamily);
	}

	async preloadUsedFonts()
	{
		for (let i = 0; i < this.used_fonts.length; i++)
		{
			let font_id = this.#fontLoader.findFontListKey(this.used_fonts[i]);
			if (font_id !== -1 && !this.#fontLoader.isFontLoaded(font_id))
				await this.#fontLoader.loadFontFaceAsync(font_id);
		}
	}
}
