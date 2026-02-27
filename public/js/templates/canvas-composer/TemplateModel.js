class TemplateModel {
	MySvgItemsParser = {};
	MyCanvasEvents = {};
	content_id;
	constructor(MySvgItemsParser, MyCanvasEvents) {
		this.MySvgItemsParser = MySvgItemsParser;
		this.MyCanvasEvents = MyCanvasEvents;
	}

	loadFromDataBase(content_id) {
		this.content_id = content_id;
		try {
			let url = ThymianConfig.async_site + "?site=templates_async" + url_separator + "action=get_content" + url_separator + "content_id=" + content_id;
			let MyRequest = new XMLHttpRequest();
			MyRequest.open("GET", url, true);
			MyRequest.setRequestHeader("Content-Type", "application/json;charset=UTF-8");
			MyRequest.onload = () => {
				if (MyRequest.status !== 200) {
					jThymian.printError(MyRequest.statusText);
				}
				else {

					let jsonResponse = JSON.parse(MyRequest.responseText);
					if (jsonResponse.error !== undefined || jsonResponse.template_style !== "2")
						return;

					// compatibility for files created the old template editor from 2014
					if ((jsonResponse.content.length !== 0) &&
						(jsonResponse.canvas == null || jsonResponse.canvas === "")) {
						jSVGEditor.init(jsonResponse.template_detail);
						jSVGEditor.prepareForEdit(jsonResponse.content);
						this.loadSvgFromString(jSVGEditor.toString());
					}
					else {
						if (jsonResponse.canvas !== null) {
							this.loadJsonFromString(jsonResponse.canvas);
						}
						else {
							this.loadSvgFromString(jsonResponse.template_detail);
						}
					}
				}
			};
			MyRequest.onerror = () => {
				jThymian.printError(MyRequest.statusText);
				ThymianLog.log(MyRequest.statusText, 0, window.location.pathname)
			};
			MyRequest.send();
		}
		catch (err) {
			ThymianLog.logException(err);
			jThymian.printError(err);
		}
	}

	loadFromLocalFile(file_path) {
		try {
			let url = file_path
			let MyRequest = new XMLHttpRequest();
			MyRequest.open("GET", url, true);
			MyRequest.setRequestHeader("Content-Type", "application/json;charset=UTF-8");
			MyRequest.onload = () => {
				if (MyRequest.status !== 200) {
					jThymian.printError(MyRequest.statusText);
				}
				else {
					if (file_path.split('.').pop() === 'json') {
						this.loadJsonFromString(MyRequest.responseText);
					}
					else {
						this.loadSvgFromString(MyRequest.responseText);
					}
				}
			};
			MyRequest.onerror = () => {
				jThymian.printError(MyRequest.statusText);
				ThymianLog.log(MyRequest.statusText, 0, window.location.pathname)
			};
			MyRequest.send();
		}
		catch (err) {
			ThymianLog.logException(err);
			jThymian.printError(err);
		}
	}

	loadSvgFromString(svg) {
		fabric.loadSVGFromString(svg,
			(objects, options) => {
				// we cannot parse here directly cause we need item for class
				// but item via object._element is only for img available
				this.MySvgItemsParser.outputTemplate(objects, options);
				this.MyCanvasEvents.initChangeDetectors();
				this.MyCanvasEvents.initEditEvents();
				fabric.Canvas.prototype.historyUndo = []
				fabric.Canvas.prototype.historyRedo = []
				this.MySvgItemsParser.MyCanvasView.getCanvas()._historySaveAction()
				this.MySvgItemsParser.MyCanvasView.getCanvas().renderAll();
			},
			(item, object) => {
				// cannot use second function direct cause parsing order is messed up
				// text is parsed before image etc...
				// so we must store an object array with id and object
				// the first function iterates correct
				// maybe there is an option for that hidden deeply in the shitty documentation
				if (object.type === "text") {
					this.MySvgItemsParser.createTextFromSVG(item, object);
				}
				else {
					this.MySvgItemsParser.createImageFromSVG(item, object);
				}
			}, { crossOrigin: 'anonymous' });
	}


	async loadJsonFromString(json_canvas)
	{
		let MyProgress = new WaitOverlay();
		MyProgress.start();
		// text is not editable, so we needed i-text
		json_canvas = json_canvas.replaceAll('"type":"text"', '"type":"textbox"');
		json_canvas = json_canvas.replaceAll('"type":"i-text"', '"type":"textbox"');

		let j = JSON.parse(json_canvas);
		// traverse objects to load fonts
		for (let i = 0; i < j.objects.length; i++)
		{
			if (j.objects[i].type === "textbox")
			{
				this.MyCanvasEvents.MyItemProperties.MyTextProperties.collectUsedFontsFromSelection(j.objects[i]);
			}
		}
		console.log("Collected all fonts");

		await this.MyCanvasEvents.MyItemProperties.MyTextProperties.preloadUsedFonts();
		console.log("Required fonts preloaded");

		this.MySvgItemsParser.MyCanvasView.getCanvas().loadFromJSON(json_canvas, () => {
				fabric.util.clearFabricFontCache();
				fabric.charWidthsCache = {};
				this.MySvgItemsParser.outputJsonTemplate(j.viewport.width, j.viewport.height);
				this.MyCanvasEvents.initChangeDetectors();
				this.MyCanvasEvents.initEditEvents();
				fabric.Canvas.prototype.historyUndo = []
				fabric.Canvas.prototype.historyRedo = []
				this.MySvgItemsParser.MyCanvasView.getCanvas()._historySaveAction();
				this.MySvgItemsParser.MyCanvasView.getCanvas().renderAll();
				MyProgress.stop();
			},
			(item, object) => {

				this.MySvgItemsParser.createItem(item, object);
			});
		}


	saveAsJpg(canvas) {
		let MyProgress = new WaitOverlay();
		MyProgress.start();
		try {
			let url = "";
			let is_template_editor_dev =  document.getElementById("is_template_editor_dev").value;
			if (is_template_editor_dev === undefined || is_template_editor_dev === "false")
			{
				url = ThymianConfig.main_site + "?site=templates_async_post" +
					url_separator + "content_id=" + this.content_id +
					url_separator + "action=save_canvas_content";
			}
			else
			{
				url = "canvas_save.php";
			}

			let MyRequest = new XMLHttpRequest();
			MyRequest.open("POST", url, true);
			MyRequest.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
			MyRequest.onload = () => {
				MyProgress.stop();

				if (MyRequest.status !== 200) {
					jThymian.printError(MyRequest.statusText);
				}
				else {
					let jsonResponse = JSON.parse(MyRequest.responseText);

					if (jsonResponse.success === false) {
						jThymian.printError(jsonResponse.message);
					}
				}
			};
			MyRequest.onerror = function () {
				MyProgress.stop();

				jThymian.printError(MyRequest.statusText);
				ThymianLog.log(MyRequest.statusText, 0, window.location.pathname)
			};

			// as coping an object in JS is ridiculous complicated we need to set Zoom to 100 and then revert it to original values
			// change Zoom to 100% otherwise current zoom factor will used
			canvas.setZoom(1);
			canvas.setWidth(this.MySvgItemsParser.width)
			canvas.setHeight(this.MySvgItemsParser.height);

			let save = canvas.toJSON(["id"]);
			save['viewport'] = { 'width': this.MySvgItemsParser.width, 'height': this.MySvgItemsParser.height, 'scale': 100 };
			// we need encode url otherwise the image links in JSON will be decoded as post
			let body = "json_canvas=" + encodeURIComponent(JSON.stringify(save)) +	"&image=" + canvas.toDataURL({ format: 'jpeg' });

			MyRequest.send(body);
			// set zoom back to original values as JavaScript changes original object
			this.MySvgItemsParser.MyCanvasView.scaleCanvas();

		}
		catch (err) {
			MyProgress.stop();
			ThymianLog.logException(err);
			jThymian.printError(err);
		}

	}

}
