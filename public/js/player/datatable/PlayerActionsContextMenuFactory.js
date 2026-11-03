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
import {PlayerActionsContextMenu} from "./PlayerActionsContextMenu.js";
import {PlayerActionsContextMenuView} from "./PlayerActionsContextMenuView.js";
import {WaitOverlay}     from "../../core/WaitOverlay.js";

export class PlayerActionsContextMenuFactory
{
	#flashMessageHandler;
	#autocompleteFactory;
	#playerService;
	#previewOverlay;
	#shareDialog;

	constructor(flashMessageHandler,  autocompleteFactory, playerService, previewOverlay, shareDialog)
	{
		this.#shareDialog = shareDialog;
		this.#flashMessageHandler = flashMessageHandler;
		this.#autocompleteFactory = autocompleteFactory;
		this.#playerService = playerService;
		this.#previewOverlay = previewOverlay;
	}

	create()
	{
		return  new PlayerActionsContextMenu(
			new PlayerActionsContextMenuView(),
			this.#flashMessageHandler,
			this.#autocompleteFactory,
			new WaitOverlay(),
			this.#playerService,
			this.#previewOverlay,
			this.#shareDialog
		);
	}

}
