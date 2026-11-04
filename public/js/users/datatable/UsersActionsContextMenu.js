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
import {ActionsContextMenu} from "../../core/ContextMenu/ActionsContextMenu.js";

export class UsersActionsContextMenu extends ActionsContextMenu
{
	#userService = null;

	constructor(contextMenuView, flashMessageHandler, userService)
	{
		super(contextMenuView, flashMessageHandler);
		this.#userService = userService;
	}

	async determineRights(UID)
	{
		return await this.#userService.determineRights(UID);
	}

	isShown(rights)
	{
		return rights.can_edit === true || rights.can_rights === true || rights.can_delete === true;
	}

	prepare(rights)
	{
		if (rights.can_edit)
			this.view.setLink("edit", "/users/edit/" + this.unitId);
		else
			this.view.remove("edit");

		if (rights.can_rights)
			this.view.setLink("rights", "/users/rights/" + this.unitId);
		else
			this.view.remove("rights");

		this.addDelete(rights.can_delete, (UID) => this.#userService.deleteUser(UID));
	}
}
