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

/**
 * Instead of the FetchClient for the services: records the requests and answers with the given result,
 * or fails with it when it is an Error.
 */
export class FakeFetchClient
{
	requests = [];
	#result;

	constructor(result)
	{
		this.#result = result;
	}

	fetchData(url, options)
	{
		this.requests.push({url: url, options: options});
		if (this.#result instanceof Error)
			return Promise.reject(this.#result);

		return Promise.resolve(this.#result);
	}

	/**
	 * The JSON body of a request, the CSRF token included.
	 */
	bodyOf(index = 0)
	{
		return JSON.parse(this.requests[index].options.body);
	}
}
