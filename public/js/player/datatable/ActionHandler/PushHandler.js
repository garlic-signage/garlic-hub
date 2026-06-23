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

export class PushHandler
{
	#playerService = null;
	#messageHandler = null;
	#waitOverlay = null;

	constructor(messageHandler, PlayerService, waitOverlay)
	{
		this.#messageHandler = messageHandler;
		this.#playerService = PlayerService;
		this.#waitOverlay = waitOverlay;
	}

	addPushPlaylistListener(pushPlaylist)
	{
		pushPlaylist.addEventListener('click', async (event) =>
		{
			try
			{
				const currentId = event.target.dataset.actionId;
				this.#playerService.pushPlaylist(currentId)
				.then(result => {
					this.#waitOverlay.stop();
					this.#messageHandler.clearAllMessages();
					if (result.success === true)
						this.#messageHandler.showSuccess(result.message);
					else
						this.#messageHandler.showError(result.error_message);
				})
				.catch(e => {
					this.#waitOverlay.stop();
					this.#messageHandler.showError(e.message);
				});
				this.#waitOverlay.start();
			}
			catch (e)
			{
				this.#waitOverlay.stop();
				this.#messageHandler.showError(e.message);
			}
		});
	}
}