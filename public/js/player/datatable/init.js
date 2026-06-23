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

import {PlayerService}         from "../PlayerService.js";
import {FetchClient} from "../../core/FetchClient.js";
import {AutocompleteFactory}      from "../../core/AutocompleteFactory.js";
import {FlashMessageHandler} from "../../core/FlashMessageHandler.js";
import {PlayerActionsContextMenuFactory} from "./PlayerActionsContextMenuFactory.js";

document.addEventListener("DOMContentLoaded", function()
{
	const playerService       = new PlayerService(new FetchClient())
	const flashMessageHandler = new FlashMessageHandler("body");
	const autocompleteFactory = new AutocompleteFactory();

	const playerActionsContextMenuFactory = new PlayerActionsContextMenuFactory(
		flashMessageHandler,
		autocompleteFactory,
		playerService
	);

	const contextMenus = document.getElementsByClassName("player-contextmenu");

	for (let i = 0; i < contextMenus.length; i++)
	{
		contextMenus[i].addEventListener('click', async (event) =>
		{
			event.preventDefault();
			const contextMenu = playerActionsContextMenuFactory.create();
			await contextMenu.init(event);
		});
	}
});