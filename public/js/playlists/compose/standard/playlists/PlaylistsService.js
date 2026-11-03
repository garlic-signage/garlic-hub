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

import {PlaylistsApiConfig} from "./PlaylistsApiConfig.js";
import {BaseService}        from "../../../../core/Base/BaseService.js";
import {ItemsApiConfig}     from "../items/ItemsApiConfig.js";

export class PlaylistsService extends BaseService
{

	async findPlaylists(playlistMode, playlistName)
	{
		const url     = `${PlaylistsApiConfig.FIND_URI}/${playlistMode}/${playlistName}`;
		const response    = await fetch(url);
		return await response.json();
	}

	async delete(playlistId)
	{
		const url = PlaylistsApiConfig.BASE_URI;
		const data = {
			playlist_id: playlistId
		};
		return await this._sendRequest(url, "DELETE", data);
	}

	determineRights(playlistId)
	{
		const url = PlaylistsApiConfig.ACLS_URI + "/" + playlistId;
		return this._sendRequestAsync(url, "GET");
	}

	async toggleShuffle(playlistId)
	{
		const url = PlaylistsApiConfig.SHUFFLE_URI;
		const data = {
			playlist_id: playlistId
		};
		return await this._sendRequest(url, "PATCH", data);
	}

	async shufflePicking(playlistId, shufflePicking)
	{
		const url = PlaylistsApiConfig.PICKING_URI;
		const data = {
			playlist_id: playlistId,
			shuffle_picking: shufflePicking
		};
		return await this._sendRequest(url, "PATCH", data);
	}

	async export(playlistId)
	{
		const url = PlaylistsApiConfig.BASE_URI;
		const data = {
			playlist_id: playlistId,
		};
		return await this._sendRequest(url, "PUT", data);
	}


}

