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

import {TemplatesService} from "../TemplatesService.js";
import {FetchClient}      from "../../core/FetchClient.js";
import {WaitOverlay}      from "../../core/WaitOverlay.js";

document.addEventListener("DOMContentLoaded", function (event) {

	const fabricAdapter  = new FabricAdapter(
		MySvgItemsParser,
		MyCanvasEvents,
		new TemplatesService(new FetchClient()),
		new WaitOverlay()
	);

	const templateId = document.getElementById("content_id").value;
	fabricAdapter.loadFromDataBase(templateId);

	/*
	let MyCanvasView     = new CanvasView(new fabric.Canvas('canvas',
		{
			stopContextMenu: true,
			fireRightClick: true,
			preserveObjectStacking: true
		}
	), {});
	let MySvgItemsParser = new SvgItemsParser(MyCanvasView);
	// needed for load media
	let MyMediaSelector  = new MediaSelector();

	let MyCanvasDialog   = new CanvasDialog(MyMediaSelector, MySvgItemsParser);
	let MyContextMenu    = new ContextMenu(MyCanvasView, MyCanvasDialog);

	let MyGlobalProperties    = new GlobalProperties(MyCanvasView);
	let MyGroupProperties     = new GroupProperties(MyCanvasView);
	let MySelectiveProperties = new SelectiveProperties(MyCanvasView);
	let MyTextProperties      = new TextProperties(MyCanvasView, new FontHandler(FontsList));

	let MyItemProperties = new ItemProperties(MyGlobalProperties, MyGroupProperties, MySelectiveProperties, MyTextProperties);

	let MyCanvasEvents   = new CanvasEvents(MyContextMenu, MyCanvasView, MyCanvasDialog, MyMediaSelector, MyItemProperties);
	let MyTemplateModel  = new TemplateModel(MySvgItemsParser, MyCanvasEvents);

	let content_id = document.getElementById("content_id").value;

	let is_template_editor_dev =  document.getElementById("is_template_editor_dev").value;
	if (is_template_editor_dev === undefined || is_template_editor_dev === "false")
	{
		// Load mechanism set in CMS
		MyTemplateModel.loadFromDataBase(content_id);
	}
	else
	{
		// this should be used only for developing
		MyTemplateModel.loadFromLocalFile("./data/template_1.svg");
	}

	MyCanvasEvents.initInsertObjects();
	MyItemProperties.initEventListener(MyCanvasView);
	MyCanvasEvents.initSaveEvent(MyTemplateModel);
	MyCanvasEvents.initRangeSliderEvents();
	MyCanvasEvents.initCloseEvent();

	window.onresize = () => {
		if (MyCanvasEvents.isAutoResize())
			MySvgItemsParser.zoomToViewPort();
	}
*/
});

