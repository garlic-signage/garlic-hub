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

import {PlaylistAssignActions} from "./PlaylistAssignActions.js";
import {PlayerService}         from "../PlayerService.js";
import {FetchClient} from "../../core/FetchClient.js";
import {AutocompleteFactory} from "../../core/AutocompleteFactory.js";
import {PlayerSettingsContextMenu} from "./PlayerSettingsContextMenu.js";
import {PushHandler}               from "./ActionHandler/PushHandler.js";
import {FlashMessageHandler} from "../../core/FlashMessageHandler.js";
import {RemoveHandler}       from "./ActionHandler/RemoveHandler.js";
import {WaitOverlay} from "../../core/WaitOverlay.js";

document.addEventListener("DOMContentLoaded", function()
{
	const playerService = new PlayerService(new FetchClient())
	const contextMenu = new PlayerSettingsContextMenu(
		document.getElementById("playerSettingsContextMenuTemplate"),
		playerService
	);
	contextMenu.init(document.getElementsByClassName("player-contextmenu"));

    const autocompleteFactory = new AutocompleteFactory();
	const flashMessageHandler = new FlashMessageHandler();
	const pushHandler = new PushHandler(flashMessageHandler, playerService, new WaitOverlay());
	pushHandler.init(document.getElementsByClassName("push-playlist"));
	const removeHandler = new RemoveHandler(playerService);
	removeHandler.init(document.getElementsByClassName("remove-playlist"));
    const playlistAssignActions =  new PlaylistAssignActions(autocompleteFactory, pushHandler, removeHandler, playerService);
	playlistAssignActions.init();


});