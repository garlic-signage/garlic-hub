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
import {UserService}                    from "../UserService.js";
import {FetchClient}                    from "../../core/FetchClient.js";
import {FlashMessageHandler}            from "../../core/FlashMessageHandler.js";
import {ActionsContextMenu}             from "../../core/ContextMenu/ActionsContextMenu.js";
import {UsersActionsContextMenuFactory} from "./UsersActionsContextMenuFactory.js";

document.addEventListener("DOMContentLoaded", function()
{
	const factory = new UsersActionsContextMenuFactory(new FlashMessageHandler(), new UserService(new FetchClient()));

	ActionsContextMenu.bindTo("user-contextmenu", () => factory.create());
});
