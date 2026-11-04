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

export class PlaylistsActionsContextMenu extends ActionsContextMenu
{
	#playlistsService = null;
	#shareDialog      = null;
	#versionsDialog   = null;

	constructor(contextMenuView, flashMessageHandler, playlistsService, shareDialog, versionsDialog)
	{
		super(contextMenuView, flashMessageHandler);
		this.#playlistsService = playlistsService;
		this.#shareDialog      = shareDialog;
		this.#versionsDialog   = versionsDialog;
	}

	async determineRights(playlistId)
	{
		return await this.#playlistsService.determineRights(playlistId);
	}

	prepare(rights)
	{
		this.view.setLink("composer", "/playlists/compose/" + this.unitId);
		this.view.setLink("settings", "/playlists/settings/" + this.unitId);
		this.#shareDialog.attachTo(this.view.item("share"), this.unitId, rights.can_share);
		this.#versionsDialog.attachTo(this.view.item("versions"), this.unitId, rights.can_version);

		// the question names the playlist
		const confirmText = this.view.item("delete").dataset.confirm.replace("%s", rights.playlist_name ?? "");
		this.addDelete(rights.can_delete, (playlistId) => this.#playlistsService.delete(playlistId), confirmText);
	}
}
