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

import {ItemsApiConfig} from "./ItemsApiConfig.js";
import {BaseService}    from "../../../../core/Base/BaseService.js";

export class ItemsService extends BaseService
{
	insertMedia(id, playlistId, position)
	{
		const data = {
			playlist_id: playlistId,
			"id": id,
			"position": position,
			"source": "mediapool"
		};
		return this._sendRequest(ItemsApiConfig.INSERT_URI, "POST",  data);
	}

	editItem(id, name, value)
	{
		const data = {
			"item_id": id,
			"name": name,
			"value": value
		};
		return this._sendRequest(ItemsApiConfig.ITEM_URI, "PATCH",  data);
	}

	insertPlaylist(id, playlistId, position)
	{
		const data = {
			playlist_id: playlistId,
			"id": id,
			"position": position,
			"source": "playlist"
		};
		return this._sendRequest(ItemsApiConfig.INSERT_URI, "POST",  data);
	}

	insertTemplate(id, playlistId, position)
	{
		const data = {
			playlist_id: playlistId,
			"id": id,
			"position": position,
			"source": "template"
		};
		return this._sendRequest(ItemsApiConfig.INSERT_URI, "POST",  data);
	}


	async updateItemsOrders(playlistId, itemsPositions)
	{
		const url = ItemsApiConfig.BASE_URI;
		const data = {
			playlist_id: playlistId,
			items_positions: itemsPositions
		};
		return await this._sendRequest(url, "PATCH", data);
	}

	async fetchDefaultSeconds(itemId)
	{
		const url = ItemsApiConfig.ITEM_URI + "/" + itemId;
		return await this._sendRequest(url, "GET", {});
	}

	async delete(playlistId, itemId)
	{
		const url = ItemsApiConfig.BASE_URI;
		const data = {
			playlist_id: playlistId,
			"item_id": itemId
		};
		return await this._sendRequest(url, "DELETE", data);
	}

	async loadByPlaylistId(playlistId)
	{
		const url = ItemsApiConfig.LOAD_PLAYLIST_ITEMS_URI + "/" + playlistId;
		return await this._sendRequest(url, "GET",  []);
	}
}