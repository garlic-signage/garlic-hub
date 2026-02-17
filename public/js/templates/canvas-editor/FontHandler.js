class FontHandler
{
	fonts_list = {};

	constructor(fontsList)
	{
		this.fonts_list = fontsList
	}

	findFontListKey(font_name)
	{
		return this.fonts_list.findIndex(font => font.name === font_name);
	}

	isFontLoaded(font_id)
	{
		return this.fonts_list[font_id].loaded
	}

	async loadFontFace(fonts_list_key, MyCallBack)
	{
		// do not reload font when it is already reloaded
		if (this.fonts_list[fonts_list_key].loaded === true)
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
		this.fonts_list[font_id].loaded = true
		console.log("loaded: " + this.fonts_list[font_id].name);
	}

	createFontFace(font_id)
	{
		// Todo: Create a factory for FontFace
		return new FontFace(this.fonts_list[font_id].name, 'url(' + this.fonts_list[font_id].url + ')', {
			style: 'normal',
			weight: 'normal'
		});
	}
}