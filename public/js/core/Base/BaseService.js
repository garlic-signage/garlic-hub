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
import {Utils} from "../Utils.js";

export class BaseService
{
	#fetchClient       = null;

	constructor(fetchClient)
	{
		this.#fetchClient      = fetchClient;
	}

	/**
	 * as JavaScript do not have protected methods, we use the old "private" workaround
	 *
	 * Never throws: a timeout, a network or a server error comes back like the errors of the server,
	 * as {success: false, error_message}. The caller shows the message.
	 */
	async _sendRequest(url, method, data = {}, timeout = null)
	{
		try
		{
			const options = {method, headers: { 'Content-Type': 'application/json' }};
			if (timeout !== null)
				options.timeout = timeout;
			if (method !== "GET")
				options.body = JSON.stringify({...data, csrf_token: Utils.csrfToken()});

			return await this.#fetchClient.fetchData(url, options);
		}
		catch (error)
		{
			return {success: false, error_message: error.message};
		}
	}
}
