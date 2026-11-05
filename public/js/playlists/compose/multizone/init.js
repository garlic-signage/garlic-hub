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

import {FabricWrapper}            from "../../../core/canvas/FabricWrapper.js";
import {AutocompleteFactory}      from "../../../core/AutocompleteFactory.js";
import {FetchClient}              from "../../../core/FetchClient.js";
import {PlaylistsService}         from "../standard/playlists/PlaylistsService.js";
import {LabeledZoneFactory}       from "./Utils/LabeledZoneFactory.js";
import {LayoutSettings}           from "./Utils/LayoutSettings.js";
import {ViewportService}          from "./Services/ViewportService.js";
import {ZoneService}              from "./Services/ZoneService.js";
import {ZonePropertiesService}    from "./Services/ZonePropertiesService.js";
import {LoadService}              from "./Services/LoadService.js";
import {SaveService}              from "./Services/SaveService.js";
import {ViewportView}             from "./Views/ViewportView.js";
import {ZonePropertiesView}       from "./Views/ZonePropertiesView.js";
import {ZoneListView}             from "./Views/ZoneListView.js";
import {ContextMenuView}          from "./Views/ContextMenuView.js";
import {ToolbarView}              from "./Views/ToolbarView.js";
import {ViewportController}       from "./Controller/ViewportController.js";
import {ZonePropertiesController} from "./Controller/ZonePropertiesController.js";
import {ZoneListController}       from "./Controller/ZoneListController.js";
import {ContextMenuController}    from "./Controller/ContextMenuController.js";
import {ToolbarController}        from "./Controller/ToolbarController.js";

window.onload = async function ()
{
	const fabricCanvas = new fabric.Canvas("canvas", {stopContextMenu: true, fireRightClick: true, preserveObjectStacking: true});
	fabricCanvas.selection = false;

	const fabricWrapper    = new FabricWrapper(fabricCanvas);
	const settings         = new LayoutSettings();
	const playlistId       = document.getElementById("playlist_id").value;
	const playlistsService = new PlaylistsService(new FetchClient());
	const viewportService  = new ViewportService(fabricWrapper);
	const zoneService      = new ZoneService(fabricWrapper, new LabeledZoneFactory());

	const playlistSearch = new AutocompleteFactory().create("zone_playlist", "/async/playlists/find/master/");
	playlistSearch.initWithExistingFields();

	const viewportController   = new ViewportController(new ViewportView(), viewportService, settings, fabricWrapper);
	const propertiesController = new ZonePropertiesController(
		new ZonePropertiesView(),
		new ZonePropertiesService(fabricWrapper, playlistsService),
		playlistSearch,
		fabricWrapper
	);
	new ZoneListController(new ZoneListView(), zoneService, propertiesController, fabricWrapper);
	new ContextMenuController(new ContextMenuView(), zoneService, fabricWrapper);
	new ToolbarController(new ToolbarView(), zoneService, new SaveService(settings, viewportService, zoneService, playlistId), propertiesController, fabricWrapper);

	try
	{
		await new LoadService(settings, viewportService, zoneService, playlistsService).load(playlistId);
		viewportController.show();
		fabricWrapper.renderAll();
		// the zones of the layout are no change
		fabricWrapper.resetChange();
	}
	catch(error)
	{
		console.log("Error while loading zones:", error);
	}
};
