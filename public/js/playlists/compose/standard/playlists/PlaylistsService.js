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
import {FetchClient}        from "../../../../core/FetchClient.js";
import {ItemsApiConfig}     from "../items/ItemsApiConfig.js";

export class PlaylistsService extends BaseService
{

	/**
	 * The media which the AI suggests on a theme, nothing is inserted.
	 */
	async suggestWithAi(playlistId, theme, minutes)
	{
		// two questions to the AI: the search terms, then the choice
		const result = await this._sendRequest(PlaylistsApiConfig.AI_SUGGEST_URI, "POST", {playlist_id: playlistId, theme: theme, minutes: minutes}, 2 * FetchClient.LLM_TIMEOUT);
		if (result.success !== true)
			throw new Error(result.error_message);

		return result.items;
	}

	async findPlaylists(playlistMode, playlistName)
	{
		const url     = `${PlaylistsApiConfig.FIND_URI}/${playlistMode}/${playlistName}`;
		const response    = await fetch(url);
		return await response.json();
	}

	/**
	 * The name of a zone in the multizone editor.
	 *
	 * @return {Promise<string>} empty when the playlist is not found or not usable
	 */
	async findNameById(playlistId)
	{
		const result = await this._sendRequest(PlaylistsApiConfig.FIND_URI + "/" + encodeURIComponent(playlistId), "GET");

		return result?.playlist_id ? String(result.name ?? "") : "";
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
		return this._sendRequest(url, "GET");
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

	async requestRelease(playlistId)
	{
		const data = {
			playlist_id: playlistId,
		};
		return await this._sendRequest(PlaylistsApiConfig.RELEASE_URI, "PATCH", data);
	}


}

