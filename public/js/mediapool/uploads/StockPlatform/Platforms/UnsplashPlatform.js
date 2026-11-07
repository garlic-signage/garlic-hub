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

import { AbstractStockPlatform } from './AbstractStockPlatform.js';
export class UnsplashPlatform extends AbstractStockPlatform
{
	#searchUri = "https://api.unsplash.com/search/photos"
	hasVideos  = false;

	constructor(fetchClient)
	{
		super(fetchClient);
	}

	async search(query, mediatype = "images")
	{

		this.currentPage = 1;
		this.currentSearchQuery = query;

		return await this.#executeSearchQuery();
	}

	async loadNextPage()
	{
		if (this.currentPage >= this.totalPages)
			return;

		this.currentPage++;
		return await this.#executeSearchQuery();
	}


	async determineMediaDownloadUrl(downloadUri)
	{
		try
		{
			const apiUrl = downloadUri + "&client_id=" +this.apiToken;
			const result = await this.fetchClient.fetchData(apiUrl);

			if (!result)
			{
				console.error('Error:', result || 'Unknown error');
				return;
			}

			return this.#prepareDownloadMediaUrl(result.url);
		}
		catch(error)
		{
			console.log('Search error:', error.message);
		}
	}

	async #executeSearchQuery()
	{
		try
		{
			const apiUrl = this.#searchUri + "/?query=" + encodeURIComponent(this.currentSearchQuery) +
				"&page=" + this.currentPage + "&per_page=" + this.resultsPerPage +
				"&client_id=" + this.apiToken;
				
			const results = await this.fetchClient.fetchData(apiUrl);

			if (!results)
			{
				console.error('Error:', results || 'Unknown error');
				return;
			}

			this.totalPages   = results.total_pages;
			this.totalResults = results.total;

			this.#prepareResults(results);

			return this.resultList;
		}
		catch(error)
		{
			console.log('Search error:', error.message);
		}
	}

	#prepareDownloadMediaUrl(downloadUrl)
	{
		return downloadUrl + "&fit=clip&w=" + this.maxWith + "&h=" + this.maxHeight + "&client_id=" + this.apiToken;
	}

	#prepareResults(json)
	{
		if (!json.results || !Array.isArray(json.results))
			throw new Error("Wrong JSON format");

		// traverse results and create a new array with the required fields
		this.resultList = json.results.reduce((acc, item) => {
			acc[item.id] = {
				type: "image",
				preview: item.urls?.small || null,
				thumb: item.urls?.thumb || null,
				downloadUrl: item.links?.download_location || null,
				metadata: {
					origin: "Unsplash",
					description: item.description || null,
					// the server takes the tags as text separated by commas, like the ones of Pixabay
					tags: (item.tags ?? []).map(tag => tag.title).filter(title => typeof title === "string" && title !== "").join(", ") || null,
					page_url: item.links?.html || null,
					user: {
						username: item.user?.username || null,
						name: item.user?.name || null,
						url: item.user?.portfolio_url || null,
					},
				}
			};
			return acc;
		}, this.resultList || {});
	}

	hasApiToken()
	{
		return super.hasApiToken('UnsplashApiToken');
	}

	saveToken(token)
	{
		localStorage.setItem('UnsplashApiToken', token);
		this.apiToken = token;
	}
}