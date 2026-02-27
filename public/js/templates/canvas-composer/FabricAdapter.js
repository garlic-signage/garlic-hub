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

export class FabricAdapter
{
	MySvgItemsParser;
	MyCanvasEvents;
	#templatesService;
	#waitOverlay;

	constructor(MySvgItemsParser, MyCanvasEvents, templatesService, waitOverlay)
	{
		this.MySvgItemsParser = MySvgItemsParser;
		this.MyCanvasEvents = MyCanvasEvents;
		this.#templatesService = templatesService;
		this.#waitOverlay = waitOverlay;
	}

	async loadTemplateFromDataBase(templateId)
	{
		this.#waitOverlay.start();
		const jsonResponse = await this.#templatesService.loadTemplateContent(templateId);
		await this.loadJsonFromString(jsonResponse.canvas);
		this.#waitOverlay.stop();
	}


	async loadJsonFromString(json_canvas)
	{
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

	async saveAsJpg(templateId, canvas)
	{
		this.#waitOverlay.start();

		// as coping an object in JS is ridiculous complicated we need to set Zoom to 100 and then revert it to original values
		// change Zoom to 100% otherwise current zoom factor will used
		canvas.setZoom(1);
		canvas.setWidth(this.MySvgItemsParser.width)
		canvas.setHeight(this.MySvgItemsParser.height);

		let save = canvas.toJSON(["id"]);
		save['viewport'] = { 'width': this.MySvgItemsParser.width, 'height': this.MySvgItemsParser.height, 'scale': 100 };

		let body = "json_canvas=" + encodeURIComponent(JSON.stringify(save)) +	"&image=" + canvas.toDataURL({ format: 'jpeg', quality: 0.8 });

		// set zoom back to original values as JavaScript changes original object
		this.MySvgItemsParser.MyCanvasView.scaleCanvas();

		await this.#templatesService.saveTemplateContent(templateId, body);
		this.#waitOverlay.stop();
	}



	/*
		legacy stuff from old composer
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
	*/
}
