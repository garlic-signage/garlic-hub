/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

import {MediaApiConfig} from "./MediaApiConfig.js";
import {BaseService}    from "../../core/Base/BaseService.js";

/**
 * @typedef {Object} ResultType
 * @property {string} error_text
 * @property {number} new_media
 */
export class MediaService extends BaseService
{
    async loadMediaByNodeId(nodeId)
    {
        const url    = MediaApiConfig.LIST_URI + "/" + nodeId;
		const result = await this._sendRequest(url, "GET", null);

		return this.#ensureSuccess(result).media_list;
    }

	async loadFilteredMediaByNodeId(nodeId, filter)
	{
		const url    = MediaApiConfig.LIST_URI + "/" + nodeId + '?filter=' + filter;
		const result = await this._sendRequest(url, "GET", null);

		return this.#ensureSuccess(result).media_list;
	}

	async getMediaById(mediaId)
	{
		const url = MediaApiConfig.BASE_URI + "/" + mediaId;
		const result = await this._sendRequest(url, "GET", null);
		return this.#ensureSuccess(result).media;
	}


	async editMedia(mediaId, filename, description)
	{
		const data = {
			"media_id": mediaId,
			"filename": filename,
			"description": description
		};
		return this.#ensureSuccess(await this._sendRequest(MediaApiConfig.EDIT_URI, "POST",  data));
	}

    async moveMedia(mediaId, nodeId)
    {
		return this.#ensureSuccess(await this._sendRequest(MediaApiConfig.MOVE_URI, "POST",  {"media_id": mediaId, "node_id": nodeId}));
    }

    async cloneMedia(mediaId)
    {
        const result = await this._sendRequest(MediaApiConfig.CLONE_URI, "POST", {"media_id": mediaId});
		return this.#ensureSuccess(result).new_media;
    }

	async removeMedia(mediaId)
	{
		return this.#ensureSuccess(await this._sendRequest(MediaApiConfig.BASE_URI,"DELETE", {"media_id": mediaId}));
	}

	/**
	 * Throws the error_message of a failed request, so the callers
	 * change the media list only after a successful request.
	 */
	#ensureSuccess(result)
	{
		if (result.success !== true)
			throw new Error(result.error_message);

		return result;
	}

}
