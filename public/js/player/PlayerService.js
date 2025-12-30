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

import { BaseService } from "../core/Base/BaseService.js";
import { PlayerApiConfig } from "./PlayerApiConfig.js";

export class PlayerService extends BaseService
{
    async replacePlaylist(playerId, playlistId)
    {
        const url = PlayerApiConfig.PLAYLIST_URI;
        const data = {
            player_id: playerId,
            playlist_id: playlistId
        };
        return await this._sendRequest(url, "PATCH", data);
    }

	determineRights(playerId)
	{
		const url = PlayerApiConfig.ACLS_URI + "/" + playerId;
		return this._sendRequestAsync(url, "GET");
	}


	pushPlaylist(playerId)
	{
		const url = PlayerApiConfig.PUSH_URI;
		const data = {
			player_id: playerId,
		};
		return this._sendRequestAsync(url, "PATCH", data);
	}

	delete(playerId)
	{
		const url = PlayerApiConfig.BASE_URI;
		const data = {
			player_id: playerId,
		};
		return this._sendRequestAsync(url, "DELETE", data);
	}

}
