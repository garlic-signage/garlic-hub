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
import {CanvasView}   from "./CanvasView.js";
import {MediaDialog}   from "./MediaDialog.js";
import {MediaSelector} from "../../mediapool/selector/MediaSelector.js";
import {ContextMenu}      from "./ContextMenu.js";
import {GlobalProperties} from "./ItemProperties/GlobalProperties.js";
import {GroupProperties}  from "./ItemProperties/GroupProperties.js";
import {SelectiveProperties} from "./ItemProperties/SelectiveProperties.js";
import {TextProperties} from "./ItemProperties/TextProperties.js";
import {ItemProperties} from "./ItemProperties.js";
import {CanvasEvents} from "./CanvasEvents.js";
import {FontHandler} from "./FontHandler.js";
import {ToggleButtonFactory} from "./ItemProperties/ToggleButtonFactory.js";
import {FabricService}       from "./FabricService.js";
import {WunderbaumWrapper}   from "../../mediapool/treeview/WunderbaumWrapper.js";
import {TreeViewElements} from "../../mediapool/treeview/TreeViewElements.js";
import {MediaService} from "../../mediapool/media/MediaService.js";
import {MediaSelectorView} from "../../mediapool/selector/MediaSelectorView.js";
import {MediaFactory} from "../../mediapool/media/MediaFactory.js";
import {BmpDitherFactory} from "./BmpDitherFactory.js";
import {FabricWrapper} from "./FabricWrapper.js";

document.addEventListener("DOMContentLoaded", function (event)
{

	const fabricCanvas = new fabric.Canvas('canvas',
		{
			stopContextMenu: true,
			fireRightClick: true,
			preserveObjectStacking: true
		});
	const fabricWrapper = new FabricWrapper(fabricCanvas);

	const canvasView     = new CanvasView(fabricCanvas, lang);
	const toggleButtonFactory = new ToggleButtonFactory();
	const mediaService = new MediaService(new FetchClient());

	let mediaSelector = new MediaSelector(
		new WunderbaumWrapper(new TreeViewElements()),
		mediaService,
		new MediaSelectorView(new MediaFactory(document.getElementById('mediaTemplate')))
	);
	mediaSelector.filter = "images";

	let mediaDialog   = new MediaDialog(mediaSelector, canvasView);
	let MyContextMenu    = new ContextMenu(canvasView, mediaDialog);

	let MyGlobalProperties    = new GlobalProperties(canvasView);
	let MyGroupProperties     = new GroupProperties(canvasView, toggleButtonFactory);
	let MySelectiveProperties = new SelectiveProperties(canvasView);
	let MyTextProperties      = new TextProperties(canvasView, new FontHandler(FontsList), toggleButtonFactory);
	let MyItemProperties      = new ItemProperties(MyGlobalProperties, MyGroupProperties, MySelectiveProperties, MyTextProperties);

	let MyCanvasEvents   = new CanvasEvents(MyContextMenu, canvasView, mediaDialog, mediaSelector, MyItemProperties);

	const fabricService  = new FabricService(
		canvasView,
		MyCanvasEvents,
		new TemplatesService(new FetchClient()),
		new WaitOverlay(),
		new BmpDitherFactory(),
		MyTextProperties
	);

	const templateId = document.getElementById("template_id");
	const itemId = document.getElementById("item_id");
	let redirectUrl  = "/templates";
	if (itemId !== null)
	{
		fabricService.loadFromPlaylistItemDataBase(itemId.value);
		redirectUrl = "/playlists/compose/" + document.getElementById("playlist_id").value;
	}
	else
		fabricService.loadFromTemplateDataBase(templateId.value);

	MyCanvasEvents.initInsertObjects();
	MyItemProperties.initEventListener(canvasView);
	MyCanvasEvents.initSaveEvent(fabricService);
	MyCanvasEvents.initResetEvent(fabricService);
	MyCanvasEvents.initRangeSliderEvents();
	MyCanvasEvents.initCloseEvent(redirectUrl);

	window.onresize = () => {
		if (MyCanvasEvents.isAutoResize())
			canvasView.zoomToViewPort();
	}

});

