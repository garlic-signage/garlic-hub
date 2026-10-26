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
import { UploadApiConfig } from '../../UploadApiConfig.js';

export class AbstractStockPlatform
{
	#apiToken    = "";
	#fetchClient = null;
	hasVideos    = false;
	#maxWith     = 1920;
	#maxHeight   = 1920;
	totalPages   = 0;
	totalResults = 0
	currentPage  = 0
	currentSearchQuery = "";
	resultsPerPage = 20;
	#resultList = {};

	constructor(fetchClient)
	{
		this.#fetchClient = fetchClient;
	}

	search(query, mediatype = "images")
	{
		throw "search() must be implemented in subclass";
	}

	download()
	{
		throw "download() must be implemented in subclass";
	}

	hasApiToken(tokenName)
	{
		if (localStorage.getItem(tokenName) === null)
			return false

		if (localStorage.getItem(tokenName) === "")
			return false

		this.apiToken = localStorage.getItem(tokenName);
		return true;

	}

	setApiToken()
	{
		throw "setApiToken() must be implemented in subclass";
	}

	get apiToken() { return this.#apiToken; }

	set apiToken(value)	{ this.#apiToken = value; }

	get fetchClient() {	return this.#fetchClient; }

	get maxWith() {	return this.#maxWith;}

	get maxHeight()	{ return this.#maxHeight; }

	get resultList() {	return this.#resultList;}

	get hasVideos()	{return this.hasVideos;}

	resetResultList()
	{
		this.#resultList = {};
	}

	set resultList(value) {	this.#resultList = value; }

	/**
	 * The server asks the stock platform for us. Like every POST it needs the CSRF token.
	 */
	async requestStockApi(dataToSend)
	{
		const body    = {...dataToSend, csrf_token: this.#detectCsrfTokenInMetaTag()};
		const options = {method: "POST", headers: {'Content-Type': 'application/json'}, body: JSON.stringify(body)};

		return await this.#fetchClient.fetchData(UploadApiConfig.SEARCH_STOCK_IMAGES, options);
	}

	#detectCsrfTokenInMetaTag()
	{
		const metaTag = document.querySelector('meta[name="csrf-token"]');
		if (metaTag && metaTag.hasAttribute('content'))
			return metaTag.getAttribute('content');

		throw new Error("No CSRF token found in meta tag");
	}
}