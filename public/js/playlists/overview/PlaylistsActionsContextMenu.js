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
import {Utils} from "../../core/Utils.js";

export class PlaylistsActionsContextMenu
{
	#playlistsService = null;
	#flashMessageHandler = null;
	#contextMenuView = null;
	#shareDialog = null;
	#controller = null;
	#currentPlaylistId = 0;

	constructor(contextMenuView, flashMessageHandler, playlistsService, shareDialog)
	{
		this.#contextMenuView     = contextMenuView;
		this.#flashMessageHandler = flashMessageHandler;
		this.#playlistsService    = playlistsService;
		this.#shareDialog         = shareDialog;
		this.#controller          = new AbortController();
	}

	async init(event)
	{
		this.#currentPlaylistId = Number(event.target.dataset.actionId);
		const responseData = await this.#playlistsService.determineRights(this.#currentPlaylistId);

		if (!responseData.can_edit)
			return;

		this.#contextMenuView.initMenuItems();
		this.#contextMenuView.setComposerLink(this.#currentPlaylistId);
		this.#contextMenuView.setSettingsLink(this.#currentPlaylistId);
		this.#shareDialog.attachTo(this.#contextMenuView.shareMenuItem, this.#currentPlaylistId, responseData.can_share);

		if (!responseData.can_delete)
			this.#contextMenuView.deleteMenuItem.remove();
		else
			this.#deleteEventListener(responseData.playlist_name);

		document.body.appendChild(this.#contextMenuView.menu);
		this.#contextMenuView.placeMenu(event.clientX, event.clientY);

		document.addEventListener('click', () => {
			this.#controller.abort(); // removes the delete listener
			this.#contextMenuView.menu.remove();
		}, { once: true });
	}

	#deleteEventListener(playlistName)
	{
		this.#contextMenuView.deleteMenuItem.addEventListener('click', async (e) => {
			e.preventDefault();
			const ok = await Utils.confirmAction(this.#contextMenuView.deleteMenuItem.dataset.confirm.replace("%s", playlistName));
			if (!ok)
				return;

			const result = await this.#playlistsService.delete(this.#currentPlaylistId);
			if (result.success)
				document.querySelector(`ul[data-id="${this.#currentPlaylistId}"]`)?.closest('li')?.remove();
			else
				this.#flashMessageHandler.showError(result.error_message);
		}, { signal: this.#controller.signal });
	}
}
