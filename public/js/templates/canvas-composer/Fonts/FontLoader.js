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

export class FontLoader
{
	#fontsList = {};

	constructor(fontsList)
	{
		this.#fontsList = fontsList;
	}


	get fontsList()
	{
		return this.#fontsList;
	}

	findFontListKey(font_name)
	{
		return this.#fontsList.findIndex(font => font.name === font_name);
	}

	isFontLoaded(font_id)
	{
		return this.#fontsList[font_id].loaded
	}

	async loadFontFace(fonts_list_key, MyCallBack)
	{
		// do not reload font when it is already reloaded
		if (this.#fontsList[fonts_list_key].loaded === true)
		{
			MyCallBack();
			return;
		}
		await this.loadFontFaceAsync(fonts_list_key);
		MyCallBack();
	}

	async loadFontFaceAsync(font_id)
	{
		return new Promise(async (resolve) => {
			const font_face = this.createFontFace(font_id);
			await font_face.load();
			document.fonts.add(font_face);
			this.setLoaded(font_id);
			resolve();
		});
	}

//============== private methods ===========================================================

	setLoaded(font_id)
	{
		this.#fontsList[font_id].loaded = true
		console.log("loaded: " + this.#fontsList[font_id].name);
	}

	createFontFace(font_id)
	{
		// Todo: Create a factory for FontFace
		return new FontFace(this.#fontsList[font_id].name, 'url(' + this.#fontsList[font_id].url + ')', {
			style: 'normal',
			weight: 'normal'
		});
	}
}