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
import {ActionsContextMenu} from "../../core/ContextMenu/ActionsContextMenu.js";
import {PushHandler}        from "./ActionHandler/PushHandler.js";

export class PlayerActionsContextMenu extends ActionsContextMenu
{
	#playerService       = null;
	#autoCompleteFactory = null;
	#waitOverlay         = null;
	#previewOverlay      = null;
	#shareDialog         = null;

	constructor(contextMenuView, flashMessageHandler, autoCompleteFactory, waitOverlay, playerService, previewOverlay, shareDialog)
	{
		super(contextMenuView, flashMessageHandler);
		this.#autoCompleteFactory = autoCompleteFactory;
		this.#waitOverlay         = waitOverlay;
		this.#playerService       = playerService;
		this.#previewOverlay      = previewOverlay;
		this.#shareDialog         = shareDialog;
	}

	async determineRights(playerId)
	{
		return await this.#playerService.determineRights(playerId);
	}

	prepare(rights)
	{
		this.addDelete(rights.can_delete, (playerId) => this.#playerService.delete(playerId));

		if (!rights.has_playlist)
		{
			this.view.remove("unassign");
			this.view.remove("goto");
			this.view.remove("preview");
		}
		else
		{
			this.#addUnAssignEventListener();
			this.view.setLink("goto", "/playlists/compose/" + rights.playlist_id);
			this.view.item("preview").addEventListener("click", () => {
				this.#previewOverlay.open(rights.uuid, rights.player_name);
			});
		}

		this.#addAssignEventListener(); // always active
		this.#shareDialog.attachTo(this.view.item("share"), this.unitId, rights.can_share);

		if (!rights.has_playlist || !rights.is_intranet)
			this.view.remove("push");
		else
			new PushHandler(this.flashMessageHandler, this.#playerService, this.#waitOverlay, this.unitId).addPushPlaylistListener(this.view.item("push"));
	}

	#addAssignEventListener()
	{
		this.view.item("assign").addEventListener("click", async () => {

			const editPlaylistField = this.#findPlaylistNameInResultsBody();

			const playlistAutocomplete = this.#autoCompleteFactory.create("playlist_name", "/async/playlists/find/for-player/");
			playlistAutocomplete.initWithCreateFields(editPlaylistField, 'playlist_id');
			playlistAutocomplete.getHiddenIdElement().addEventListener("selectedFromDataList", async (event) => {
				const playlistId = event.target.value;
				const result = await this.#playerService.replacePlaylist(this.unitId, playlistId);
				if (result.success)
					playlistAutocomplete.restore(result.playlist_name);
			});
		});
	}

	#addUnAssignEventListener()
	{
		this.view.item("unassign").addEventListener("click", async () => {

			const result = await this.#playerService.replacePlaylist(this.unitId, 0);
			if (!result.success)
				return;

			this.#findPlaylistNameInResultsBody().textContent = "";
		});
	}

	#findPlaylistNameInResultsBody()
	{
		return document.querySelector('ul.results-body[data-id="' + this.unitId + '"] li.playlist_id');
	}
}
