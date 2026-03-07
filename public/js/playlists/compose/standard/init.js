/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2025 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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
"use strict";

import {InsertContextMenu} from "./InsertContextMenu.js";
import {SelectorFactory}   from "./SelectorFactory.js";
import {ItemsService}      from "./items/ItemsService.js";
import {FetchClient}       from "../../../core/FetchClient.js";
import {ItemList}          from "./items/ItemList.js";
import {ItemFactory}       from "./items/ItemFactory.js";
import {DragDropHandler}   from "./DragDropHandler.js";
import {PlaylistsProperties} from "./playlists/PlaylistsProperties.js";
import {PlaylistsService} from "./playlists/PlaylistsService.js";
import {WidgetFactory} from "./editors/WidgetFactory.js";
import {ConditionalPlayFactory} from "./editors/ConditionalPlayFactory.js";
import {TriggerFactory} from "./editors/TriggerFactory.js";
import {TemplatesService} from "../../../templates/TemplatesService.js";

document.addEventListener("DOMContentLoaded", async function ()
{
	const dropTarget = document.getElementById("thePlaylist");
	const playlistId = document.getElementById("playlist_id").value;

	const playlistsService    = new PlaylistsService(new FetchClient());
	const itemsService        = new ItemsService(new FetchClient());
	const playlistsProperties = new PlaylistsProperties(playlistsService, lang);
	const itemFactory         = new ItemFactory(new WidgetFactory(), new ConditionalPlayFactory(), new TriggerFactory());
	const itemsList           = new ItemList(itemFactory, itemsService, dropTarget, playlistsProperties);
	const dragDropHandler     = new DragDropHandler(dropTarget, itemsService, itemsList);
	dragDropHandler.playlistId = playlistId;

	const templatesService = new TemplatesService(new FetchClient());
	const insertContextMenu = new InsertContextMenu(new SelectorFactory(playlistsService, templatesService), dragDropHandler);

	insertContextMenu.init();
	await itemsList.buildPlaylist(playlistId);

	playlistsProperties.init(playlistId);



});