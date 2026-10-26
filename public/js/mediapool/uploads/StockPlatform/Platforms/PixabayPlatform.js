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

export class PixabayPlatform extends AbstractStockPlatform
{
	#searchImageUri = "https://pixabay.com/api"
	#searchVideoUri = "https://pixabay.com/api/videos";
	hasVideos  = true;
	#mediatype = "images";

	constructor(fetchClient)
	{
		super(fetchClient);
	}

	async search(query, mediatype = "images")
	{
		this.currentPage = 1;
		this.currentSearchQuery = query;
		this.#mediatype = mediatype;

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
		// no need to do any further processing for Pixabay
		return downloadUri;
	}

	async #executeSearchQuery()
	{
		try
		{
			let searchUri = this.#searchImageUri;
			if (this.#mediatype === "videos")
				searchUri = this.#searchVideoUri;

			const apiUrl = searchUri + "/?q=" + encodeURIComponent(this.currentSearchQuery) +
				"&page=" + this.currentPage + "&per_page=" + this.resultsPerPage +
				"&key=" + this.apiToken;

			const dataToSend  = {"api_url": apiUrl};
			const results = await this.requestStockApi(dataToSend);

			if (!results || !results.success)
			{
				console.error('Error:', results.error_message || 'Unknown error');
				return;
			}

			const data = results.data;

			//totalHits => Pixabay API is limited to return a maximum of 500 images per query.
			this.totalPages   = data.totalHits / this.resultsPerPage;
			this.totalResults = data.total;

			if (this.#mediatype === "images")
				this.#prepareImagesResults(data);
			else
				this.#prepareVideosResults(data);

			return this.resultList;
		}
		catch(error)
		{
			console.log('Search error:', error.message);
		}
	}

	#prepareImagesResults(json)
	{
		if (!json.hits || !Array.isArray(json.hits))
			throw new Error("Wrong JSON format");


		// traverse results and create a new array with the required fields
		this.resultList = json.hits.reduce((acc, item) => {
			acc[item.id] = {
				type: "image",
				preview: item.webformatURL || null,
				thumb: item.previewURL || null,
				downloadUrl: item.largeImageURL || null,
				metadata: {
					origin: "Pixabay",
					description: item.tags || null,
					page_url: item.pageURL || null,
					user: {
						username: item.username || null,
						name: item.username || null,
						url: null,
					},
				}
			};
			return acc;
		}, this.resultList || {});
	}

	#prepareVideosResults(json)
	{
		if (!json.hits || !Array.isArray(json.hits))
			throw new Error("Wrong JSON format");


		// traverse results and create a new array with the required fields
		this.resultList = json.hits.reduce((acc, item) => {
			acc[item.id] = {
				type: "video",
				preview: item.videos.tiny.url || null,
				thumb: item.videos.tiny.thumbnail || null,
				downloadUrl: item.videos.large.url || null,
				metadata: {
					origin: "Pixabay",
					description: item.tags || null,
					page_url: item.pageURL || null,
					user: {
						username: item.user_id || null,
						name: item.user || null,
						url: null,
					},
				}
			};
			return acc;
		}, this.resultList || {});

	}



	hasApiToken()
	{
		return super.hasApiToken('PixabayApiToken');
	}

	saveToken(token)
	{
		localStorage.setItem('PixabayApiToken', token);
		this.apiToken = token;
	}
}
