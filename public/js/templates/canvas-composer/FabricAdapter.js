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
	#waitOverlay
	#templateId = 0;
	#itemId = 0;

	constructor(MySvgItemsParser, MyCanvasEvents, templatesService, waitOverlay)
	{
		this.MySvgItemsParser = MySvgItemsParser;
		this.MyCanvasEvents = MyCanvasEvents;
		this.#templatesService = templatesService;
		this.#waitOverlay = waitOverlay;
	}

	async loadFromTemplateDataBase(templateId)
	{
		this.#waitOverlay.start();
		this.#templateId = templateId;
		const jsonResponse = await this.#templatesService.loadTemplateContent(templateId);
		let content = jsonResponse.content;
		if (content.length === 0)
			content = "{\"objects\": [],\"viewport\":{\"width\":1920,\"height\":1080,\"scale\":100}}";

		await this.loadJsonFromString(content);

		this.#waitOverlay.stop();
	}

	async loadFromPlaylistItemDataBase(itemId)
	{
		this.#waitOverlay.start();
		this.#itemId = itemId;
		const jsonResponse = await this.#templatesService.loadPlaylistItemContent(itemId);
		let content = jsonResponse.content;
		if (content.length === 0)
			content = "{\"objects\": [],\"viewport\":{\"width\":1920,\"height\":1080,\"scale\":100}}";

		await this.loadJsonFromString(content);

		this.#waitOverlay.stop();
	}


	async loadJsonFromString(json_canvas)
	{
		// text is not editable, so we needed i-text
		json_canvas = json_canvas.replaceAll('"type":"text"', '"type":"textbox"');
		json_canvas = json_canvas.replaceAll('"type":"i-text"', '"type":"textbox"');

		let j = JSON.parse(json_canvas);
		this.#traverseObjects.call(this, j.objects);
		console.log("Collected all fonts");

		await this.MyCanvasEvents.MyItemProperties.MyTextProperties.preloadUsedFonts();

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
			},
			(item, object) => {
				(item, object) => {
					this.MySvgItemsParser.createItem(item, object);
				}
			});
	}

	async saveAsJpg(canvas)
	{
		this.#waitOverlay.start();

		// as coping an object in JS is ridiculous complicated we need to set Zoom to 100 and then revert it to original values
		// change Zoom to 100% otherwise current zoom factor will used
		canvas.setZoom(1);
		canvas.setWidth(this.MySvgItemsParser.width)
		canvas.setHeight(this.MySvgItemsParser.height);

		let save = canvas.toJSON(["mediaId", "fileName"]);
		save['viewport'] = { 'width': this.MySvgItemsParser.width, 'height': this.MySvgItemsParser.height, 'scale': 100 };

		const content = JSON.stringify(save);

		const image = canvas.toDataURL({
			format: 'jpeg',
			quality: 0.8,
			backgroundColor: '#ffffff'
		});

		// set zoom back to original values as JavaScript changes original object
		this.MySvgItemsParser.MyCanvasView.scaleCanvas();

		try
		{
			await this.#templatesService.saveTemplateContent(this.#templateId, content, image);
		}
		catch(e)
		{
			console.error(e);
		}
		this.#waitOverlay.stop();
	}

	#traverseObjects(objects)
	{
		for (let i = 0; i < objects.length; i++)
		{
			const obj = objects[i];
			if (obj.type === "textbox")
			{
				this.MyCanvasEvents.MyItemProperties.MyTextProperties.collectUsedFontsFromSelection(obj);
			}
			else if (obj.type === "group" && obj.objects)
			{
				this.#traverseObjects.call(this, obj.objects);
			}
		}
	}
}
