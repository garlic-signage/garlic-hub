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
import {Utils} from "../../core/Utils.js";

export class PlayerActionsContextMenu
{
	#playerService = null;
	#flashMessageHandler = null;
	#autoCompleteFactory = null;
	#pushHandler = null;
	#playerNameAutocomplete = null;
	#contextMenuView = null;
	#controller = null;
	#currentPlayerId = 0;

	constructor(contextMenuView, flashMessageHandler, autoCompleteFactory, pushhandler, playerService)
	{
		this.#contextMenuView       = contextMenuView;
		this.#flashMessageHandler   = flashMessageHandler;
		this.#autoCompleteFactory   = autoCompleteFactory;
		this.#pushHandler           = pushhandler;
		this.#playerService         = playerService;
		this.#controller            = new AbortController();
	}

	async init(event)
	{
		this.#currentPlayerId = Number(event.target.dataset.actionId);
		const responseData = await this.#playerService.determineRights(this.#currentPlayerId );

		if (!responseData.can_edit)
			return;

		this.#contextMenuView.initMenuItems();

		if (!responseData.can_delete)
			this.#contextMenuView.deleteMenuItem.remove();
		else
			this.#deletePlayerEventListener();

		if (!responseData.has_playlist)
		{
			this.#contextMenuView.unassignMenuItem.remove();
			this.#contextMenuView.gotoMenuItem.remove();
		}
		else
		{
			this.#addUnAssignEventListener();
			this.#contextMenuView.setGotoLink(responseData.playlist_id);
		}

		this.#addAssignEventListener(); // always active

		if (!responseData.has_playlist || !responseData.is_intranet)
			this.#contextMenuView.pushMenuItem.remove();
		else
			this.#pushHandler.addPushPlaylistListener(this.#contextMenuView.pushMenuItem);

		document.body.appendChild(this.#contextMenuView.menu);

		this.#contextMenuView.placeMenu(event.clientX, event.clientY)

		document.addEventListener('click', () => {
			this.#controller.abort(); // Killt delete-Listener
			this.#contextMenuView.menu.remove();
		}, { once: true });

	}

	#addAssignEventListener()
	{
		this.#contextMenuView.assignMenuItem.addEventListener("click", async (event) => {

			let editPlaylistField = this.#findPlaylistNameInResultsBody();

			this.#playerNameAutocomplete = this.#autoCompleteFactory.create("playlist_name", "/async/playlists/find/for-player/");
			this.#playerNameAutocomplete.initWithCreateFields(editPlaylistField, 'playlist_id');
			this.#playerNameAutocomplete.getHiddenIdElement().addEventListener("selectedFromDataList", async (event) => {
				const playlistId = event.target.value;
				const result = await this.#playerService.replacePlaylist(this.#currentPlayerId, playlistId);
				if (result.success)
				{
					this.#playerNameAutocomplete.restore(result.playlist_name);
				}
			});

		});

	}

	#addUnAssignEventListener()
	{
		this.#contextMenuView.unassignMenuItem.addEventListener("click", async (event) => {

			const result = await this.#playerService.replacePlaylist(this.#currentPlayerId, 0);
			if (!result.success)
				return;

			this.#findPlaylistNameInResultsBody().textContent = "";
		});
	}

	#deletePlayerEventListener()
	{
		this.#contextMenuView.deleteMenuItem.addEventListener('click', async (e) => {
			e.preventDefault();
			const ok = await Utils.confirmAction(this.#contextMenuView.deleteMenuItem.dataset.confirm);
			if (ok)
			{
				const result = await this.#playerService.delete(this.#currentPlayerId);
				if (result.success)
					document.querySelector(`ul[data-id="${this.#currentPlayerId}"]`)?.closest('li')?.remove();
				else
					this.#flashMessageHandler.showError(result.error_message);
			}
		}, { signal: this.#controller.signal });
	}

	#findPlaylistNameInResultsBody(element)
	{
		return document.querySelector('ul.results-body[data-id="' + this.#currentPlayerId + '"] li.playlist_id');
	}
}