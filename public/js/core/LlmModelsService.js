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
import {BaseService} from "./Base/BaseService.js";

/**
 * The models at the address of an LLM form: <action of the form>/models.
 */
export class LlmModelsService extends BaseService
{
	load(url, apiType, baseUrl, apiKey)
	{
		return this._sendRequest(url, "POST", {api_type: apiType, base_url: baseUrl, api_key: apiKey});
	}
}
