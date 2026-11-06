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
import {UnitVersionsDialog} from "../../core/UnitVersionsDialog.js";
import {ActionsContextMenu} from "../../core/ContextMenu/ActionsContextMenu.js";
import {Utils} from "../../core/Utils.js";

document.addEventListener("DOMContentLoaded", function()
{
	const templatesService                   = new TemplatesService(new FetchClient());
	const templatesActionsContextMenuFactory = new TemplatesActionsContextMenuFactory(
		new FlashMessageHandler(),
		templatesService,
		UnitShareDialog.create("templates"),
		UnitVersionsDialog.create("templates")
	);

	ActionsContextMenu.bindTo("template-contextmenu", () => templatesActionsContextMenuFactory.create());

	// installed templates come with the reload, the message of the server too
	const installPack = document.getElementById("installTemplatePack");
	installPack?.addEventListener("click", async () =>
	{
		if (!await Utils.confirmAction(installPack.dataset.confirm))
			return;

		installPack.disabled = true;
		const result = await templatesService.installPack();
		installPack.disabled = false;
		if (!result.success)
			new FlashMessageHandler().showError(result.error_message);
		else if (result.installed > 0)
			window.location.reload();
		else
			new FlashMessageHandler().showInformation(result.message);
	});

});