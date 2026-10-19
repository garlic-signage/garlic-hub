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
import { TreeViewApiConfig } from "./TreeViewApiConfig.js";
import {BaseService}         from "../../core/Base/BaseService.js";

export class TreeViewService extends BaseService
{
	async addNode(nodeId, name)
	{
		return this.#ensureSuccess(await this._sendRequest(TreeViewApiConfig.BASE_NODE_URI, "POST", { node_id: nodeId, name }));
	}

	async editNode(nodeId, name)
	{
		return this.#ensureSuccess(await this._sendRequest(TreeViewApiConfig.BASE_NODE_URI, "PATCH", { node_id: nodeId, name }));
	}

	async deleteNode(nodeId)
	{
		return this.#ensureSuccess(await this._sendRequest(TreeViewApiConfig.BASE_NODE_URI, "DELETE", { node_id: nodeId }));
	}

	async moveNodeTo(sourceKey, targetKey, targetRegion)
	{
		const data = { src_node_id: sourceKey, target_node_id: targetKey, target_region: targetRegion };
		return this.#ensureSuccess(await this._sendRequest(TreeViewApiConfig.MOVE_NODE_URI, "POST", data));
	}

	/**
	 * Throws the error_message of a failed request, so the callers
	 * change the tree only after a successful request.
	 */
	#ensureSuccess(result)
	{
		if (result.success !== true)
			throw new Error(result.error_message);

		return result;
	}

}