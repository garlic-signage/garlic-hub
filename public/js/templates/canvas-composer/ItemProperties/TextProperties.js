class TextProperties {
	MyCanvasView = {};
	MyFontHandler = {};
	property = document.getElementById("text_properties");
	fontFamily = document.getElementById("font_family")
	textAlign = new ToggleButton(document.getElementById("text_align"))
	textBold = new ToggleButton(document.getElementById("text_bold"))
	textItalic = new ToggleButton(document.getElementById("text_italic"))
	textUnderline = new ToggleButton(document.getElementById("text_underline"))
	used_fonts = [];

	constructor(MyCanvasView, MyFontHandler) {
		this.MyCanvasView = MyCanvasView;
		this.MyFontHandler = MyFontHandler;
		this.buildFontDropdown();
	}

	activate(object) {
		//console.log(object)
		this.fontFamily.innerHTML = this.getTextFontFamily(object);
		this.textAlign.update('display', object.textAlign)
		this.textBold.update('active', this.getTextBold(object))
		this.textItalic.update('active', this.getTextItalic(object))
		this.textUnderline.update('active', object.underline ? 'underline' : 'normal')
		this.property.style.display = "flex";
	}

	deactivate() {
		this.property.style.display = "none";
	}

	initEventListener() {
		this.textAlign.getElement().addEventListener("click", () => {
			let object = this.MyCanvasView.getCanvas().getActiveObject();
			this.setTextAlign(object)
			this.MyCanvasView.getCanvas().fire('object:modified', { target: object })
			this.MyCanvasView.renderCanvas();
		});
		this.textBold.getElement().addEventListener("click", () => {
			let object = this.MyCanvasView.getCanvas().getActiveObject();
			this.setTextBold(object)
			this.MyCanvasView.getCanvas().fire('object:modified', { target: object })
			this.MyCanvasView.renderCanvas();
		});
		this.textItalic.getElement().addEventListener("click", () => {
			let object = this.MyCanvasView.getCanvas().getActiveObject();
			this.setTextItalic(object)
			this.MyCanvasView.getCanvas().fire('object:modified', { target: object })
			this.MyCanvasView.renderCanvas();
		});
		this.textUnderline.getElement().addEventListener("click", () => {
			let object = this.MyCanvasView.getCanvas().getActiveObject();
			this.setTextUnderline(object)
			this.MyCanvasView.getCanvas().fire('object:modified', { target: object })
			this.MyCanvasView.renderCanvas();
		});
		/* for later use
		 document.getElementById('upload_font').addEventListener("change", (event) => {
		 let object = this.MyCanvasView.getCanvas().getActiveObject();
		 let files = event.target.files
		 if (files.length > 0) {
		 for (let i = 0; i < files.length; i++) {
		 const reader = new FileReader();
		 reader.readAsDataURL(files[i]);
		 reader.onload = () => {
		 let fontName = files[i].name.substring(0, files[i].name.lastIndexOf('.'))
		 var sheet = window.document.styleSheets[0];
		 sheet.insertRule(`@font-face {
		 font-family: '${fontName}';
		 font-style: normal;
		 font-weight: normal;
		 src:url('${reader.result}');}`, sheet.cssRules.length);
		 this.setTextFontFamily(object, fontName);
		 this.fontFamily.innerHTML = fontName
		 this.MyCanvasView.getCanvas().fire('object:modified', { target: object })
		 let fontObserver = new this.MyCanvasView.FontFaceObserver(fontName);
		 fontObserver.load().then(() => {
		 this.MyCanvasView.renderCanvas();
		 });
		 };
		 reader.onerror = (error) => {
		 console.log(`Error: ${error}`);
		 };
		 }
		 }
		 })
		 */
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

	setTextAlign(object) {
		if (!object) return
		const positions = ["left", "center", "right", "left"]
		const currentIndex = positions.findIndex((v) => v === object.textAlign)
		const nextAlign = positions[currentIndex + 1]
		this.textAlign.update("display", nextAlign)
		object.set("textAlign", nextAlign)
	}

	buildFontDropdown() {
		let fontsDropdown = document.getElementById("select_fonts_dropdown")
		while (fontsDropdown.children.length > 1)
		{
			fontsDropdown.removeChild(fontsDropdown.lastChild);
		}

		if (!Array.isArray(this.MyFontHandler.fonts_list))
			return;

		for (let i = 0; i < this.MyFontHandler.fonts_list.length; i++)
		{
			let div = document.createElement("div")
			div.classList.add("template_edit_dropdown_content_font");
			div.classList.add("template_edit_dropdown_content_hover");
			div.innerHTML = this.MyFontHandler.fonts_list[i].preview
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
			let font_name = self.MyFontHandler.fonts_list[font_id].name;
			self.setTextFontFamily(self.MyCanvasView.getCanvas().getActiveObject(), font_name);
			self.fontFamily.innerHTML = font_name;
			self.MyCanvasView.getCanvas().fire('object:modified', { target: self.MyCanvasView.getCanvas().getActiveObject() })
			self.MyCanvasView.renderCanvas();
		}

		this.MyFontHandler.loadFontFace(font_id, MyCallBack)
	}

	getTextFontFamily(object) {
		if (!object) return
		//return object.fontFamily
		let styles = object.getSelectionStyles(object.isEditing ? object.selectionStart : 0, object.isEditing ? object.selectionEnd : 9, false)
		return styles && styles[0] ? styles[0].fontFamily : object.fontFamily
	}

	setTextFontFamily(object, fontFamily) {
		if (!object) return
		// object.set("fontFamily", fontFamily)
		object.setSelectionStyles({ fontFamily: fontFamily }, object.selectionStart === object.selectionEnd ? 0 : object.selectionStart, object.selectionStart === object.selectionEnd ? object.text.length : object.selectionEnd)
	}

	getTextBold(object) {
		if (!object) return
		let styles = object.getSelectionStyles(object.isEditing ? object.selectionStart : 0, object.isEditing ? object.selectionEnd : 1, true)
		return styles && styles[0] ? styles[0].fontWeight : object.fontWeight
	}

	setTextBold(object) {
		if (!object) return
		let nextBold = this.getTextBold(object) === 'bold' ? 'normal' : 'bold'
		this.textBold.update("active", nextBold)
		object.setSelectionStyles({ fontWeight: nextBold }, object.selectionStart === object.selectionEnd ? 0 : object.selectionStart, object.selectionStart === object.selectionEnd ? object.text.length : object.selectionEnd)
	}

	getTextItalic(object) {
		if (!object) return
		let styles = object.getSelectionStyles(object.isEditing ? object.selectionStart : 0, object.isEditing ? object.selectionEnd : 1, true)
		return styles && styles[0] ? styles[0].fontStyle : object.fontStyle
	}

	setTextItalic(object) {
		if (!object) return
		let nextItalic = this.getTextItalic(object) === 'italic' ? 'normal' : 'italic'
		this.textItalic.update("active", nextItalic)
		object.setSelectionStyles({ fontStyle: nextItalic }, object.selectionStart === object.selectionEnd ? 0 : object.selectionStart, object.selectionStart === object.selectionEnd ? object.text.length : object.selectionEnd)
	}

	setTextUnderline(object) {
		if (!object) return
		let nextUnderline = !object.underline
		this.textUnderline.update("active", nextUnderline ? 'underline' : 'normal')
		// object.setSelectionStyles({ underline: nextUnderline }, object.selectionStart === object.selectionEnd ? 0 : object.selectionStart, object.selectionStart === object.selectionEnd ? object.text.length : object.selectionEnd)
		object.set("underline", nextUnderline)
	}
}
