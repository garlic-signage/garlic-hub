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

import {BaseService} from "../core/Base/BaseService.js";
import {UsersApiConfig} from "./UsersApiConfig.js";

export class UserService  extends BaseService
{
	determineRights(uid)
	{
		return this._sendRequest(UsersApiConfig.USER_ACLS_URI + "/" + uid, "GET");
	}

	async deleteUser(uid)
	{
		const url = UsersApiConfig.USER_DELETE_FROM_UI;
		const data = {
			UID: uid
		};
		return await this._sendRequest(url, "DELETE", data);
	}

	/**
	 * Sets or removes a role of the user in the module.
	 */
	async setRole(uid, module, role, granted)
	{
		const url  = UsersApiConfig.USER_RIGHTS_URI + "/" + uid;
		const data = {
			module: module,
			role: role,
			granted: granted ? "1" : "0"
		};
		return await this._sendRequest(url, "PUT", data);
	}
}
