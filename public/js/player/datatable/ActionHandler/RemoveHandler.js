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
'use strict';

export class RemoveHandler
{
	#playerService = null;

	constructor(PlayerService)
	{
		this.#playerService = PlayerService;
	}

	init(removePlaylists)
	{
		for (let i = 0; i < removePlaylists.length; i++)
		{
			this.addRemoveEventListener(removePlaylists[i]);
		}

	}

	addRemoveEventListener(removePlaylist)
	{
		removePlaylist.addEventListener("click", async (event) => {
			let parentWithDataId = this.findDataIdInResultsBody(event.target)
			let playerId = 0;
			if (parentWithDataId !== null)
				playerId = parentWithDataId.dataset.id;
			else
				return;
			const result = await this.#playerService.replacePlaylist(playerId, 0);
			if (!result.success)
				return;

			parentWithDataId.querySelector(".playlist_id").innerText = result.playlist_name;
			parentWithDataId.querySelector(".playlist-link").parentElement.remove();
			if (parentWithDataId.querySelector(".push-playlist") !== null)
				parentWithDataId.querySelector(".push-playlist").parentElement.remove();
			event.target.parentElement.remove();
		});
	}

	findDataIdInResultsBody(element)
	{
		let currentElement = element;

		while (currentElement)
		{
			if (currentElement.classList && currentElement.classList.contains('results-body'))
			{
				return currentElement;
			}
			currentElement = currentElement.parentElement;
		}

		return null;
	}

}
