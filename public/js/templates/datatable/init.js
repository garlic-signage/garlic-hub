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

import {TemplatesService}  from "../TemplatesService.js";
import {FetchClient}       from "../../core/FetchClient.js";
import {TemplatesActionsContextMenuFactory} from "./TemplatesActionsContextMenuFactory.js";
import {FlashMessageHandler} from "../../core/FlashMessageHandler.js";
import {UnitShareDialog} from "../../core/UnitShareDialog.js";

document.addEventListener("DOMContentLoaded", function()
{
	UnitShareDialog.bind("templates");
	const templatesService                   = new TemplatesService(new FetchClient());
	const templatesActionsContextMenuFactory = new TemplatesActionsContextMenuFactory(
		new FlashMessageHandler(),
		templatesService
	);

	const contextMenus = document.getElementsByClassName("template-contextmenu");

	for (let i = 0; i < contextMenus.length; i++)
	{
		contextMenus[i].addEventListener('click', async (event) =>
		{
			event.preventDefault();
			const contextMenu = templatesActionsContextMenuFactory.create();
			await contextMenu.init(event);
		});
	}

});