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

export class PlaylistAssignActions
{
    #selectPlaylist = document.getElementsByClassName("select-playlist");
    #autocompleteFactory = null;
	#pushHandler = null;
	#removeHandler = null;
    #playerNameAutocomplete = null;

    #playerService = null;
    constructor(autoCompleteFactory, pushHandler, removeHandler, PlayerService)
    {
        this.#autocompleteFactory = autoCompleteFactory;
		this.#pushHandler         = pushHandler;
		this.#removeHandler       = removeHandler;
        this.#playerService       = PlayerService;
    }

   init()
   {
        for (let i = 0; i < this.#selectPlaylist.length; i++)
		{
            const element = this.#selectPlaylist[i];
            element.addEventListener("click", async (event) => {
                let parentWithDataId = this.#removeHandler.findDataIdInResultsBody(event.target)
                let playerId = 0;
                if (parentWithDataId !== null)
                    playerId = parentWithDataId.dataset.id;
                else
                    return;

				let editPlaylist = parentWithDataId.querySelector(".playlist_id");

				this.#playerNameAutocomplete = this.#autocompleteFactory.create("playlist_name", "/async/playlists/find/for-player/");
				this.#playerNameAutocomplete.initWithCreateFields(editPlaylist);
				this.#playerNameAutocomplete.getHiddenIdElement().addEventListener("change", async (event) => {
					const playlist_id = event.target.value;
					const result = await this.#playerService.replacePlaylist(playerId, event.target.value);
					if (result.success)
					{
						this.#playerNameAutocomplete.restore(result.playlist_name);
						let removePlaylist = parentWithDataId.querySelector(".remove-playlist");
						const actions = parentWithDataId.querySelector(".actions ul");
						if (removePlaylist === null) // new playlist
						{
							const li1 = document.createElement("li");
							removePlaylist = document.createElement("a");
							removePlaylist.href = "#";
							removePlaylist.dataset.action = "playlist";
							removePlaylist.className = "bi bi-x-circle remove-playlist";
							li1.appendChild(removePlaylist);
							actions.appendChild(li1);
							this.#removeHandler.addRemoveEventListener(removePlaylist);
						}
						removePlaylist.dataset.actionId = playlist_id;

						if (result.is_intranet === 1)
						{
							let pushPlaylist = parentWithDataId.querySelector(".push-playlist");
							if (pushPlaylist === null)
							{
								const li2 = document.createElement("li");
								pushPlaylist = document.createElement("a");
								pushPlaylist.href = "#";
								pushPlaylist.dataset.action   = "push";
								pushPlaylist.className = "bi bi-arrow-left-circle-fill push-playlist";
								li2.appendChild(pushPlaylist);
								actions.appendChild(li2);
								this.#pushHandler.addPushPlaylistListener(pushPlaylist)
							}
							pushPlaylist.dataset.actionId = playerId;
						}

						let linkPlaylist = parentWithDataId.querySelector(".playlist-link");
						if (linkPlaylist === null)
						{
							const li3 = document.createElement("li");
							linkPlaylist = document.createElement("a");
							linkPlaylist.dataset.action   = "playlist";
							linkPlaylist.className = "bi bi-music-note-list playlist-link";
							li3.appendChild(linkPlaylist);
							actions.appendChild(li3);
						}
						linkPlaylist.dataset.actionId = playlist_id;
						linkPlaylist.href = "/playlists/compose/" + playlist_id;
					}
				});
            });
        }
    }
}
