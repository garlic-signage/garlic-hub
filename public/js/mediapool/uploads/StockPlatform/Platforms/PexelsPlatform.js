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
export class PexelsPlatform extends AbstractStockPlatform
{
	#searchImageUri = "https://api.pexels.com/v1/search";
	#searchVideoUri = "https://api.pexels.com/videos/search";
	hasVideos  = true;
	#mediatype = "images";

	constructor(fetchClient)
	{
		super(fetchClient);
	}

	async search(query, mediatype)
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
		return downloadUri + "?auto=compress&cs=tinysrgb&w=" + this.maxWith + "&h=" + this.maxHeight;
	}

	async #executeSearchQuery()
	{
		try
		{
			let searchUri = this.#searchImageUri;
			if (this.#mediatype === "videos")
				searchUri = this.#searchVideoUri;

			const apiUrl = searchUri + "/?query=" + encodeURIComponent(this.currentSearchQuery) +
				"&page=" + this.currentPage + "&per_page=" + this.resultsPerPage;

			const dataToSend  = {"api_url": apiUrl, "headers": {"Authorization": this.apiToken}};
			const results     = await this.requestStockApi(dataToSend);

			if (!results || !results.success)
			{
				console.error('Error:', results.error_message || 'Unknown error');
				return;
			}

			const data = results.data;

			//totalHits => Pixabay API is limited to return a maximum of 500 images per query.
			this.totalPages   = data.total_results / this.resultsPerPage;
			this.totalResults = data.total_results;

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
		if (!json.photos || !Array.isArray(json.photos))
			throw new Error("Wrong JSON format");

		this.resultList = json.photos.reduce((acc, item) => {
			acc[item.id] = {
				type: "image",
				preview: item.src?.medium || null,
				thumb: item.src?.small || null,
				downloadUrl: item.src?.original || null,
				metadata: {
					origin: "pexels",
					description: item.alt || null,
					page_url: item.url || null,
					user: {
						username: item.photographer_id || null,
						name: item.photographer || null,
						url: item.photographer_url,
					},
				}
			};
			return acc;
		}, this.resultList || {});
	}

	#prepareVideosResults(json)
	{
		if (!json.videos || !Array.isArray(json.videos))
			throw new Error("Wrong JSON format");
		this.resultList = json.videos.reduce((acc, item) => {
			acc[item.id] = {
				type: "video",
				preview: item.video_files.filter(v => v.quality === "sd").sort((a, b) => (a.width * a.height) - (b.width * b.height))[0]?.link,
				thumb: item.video_pictures[0]?.picture || null,
				downloadUrl: item.video_files.filter(v => v.quality === "hd").sort((a, b) => (b.width * b.height) - (a.width * a.height))[0]?.link,
				metadata: {
					origin: "pexels",
					description: null,
					page_url: item.url || null,
					user: {
						username: item.user.id || null,
						name: item.user.name || null,
						url: item.user.url,
					},
				}
			};
			return acc;
		}, this.resultList || {});
	}


	hasApiToken()
	{
		return super.hasApiToken('PexelsApiToken');
	}

	saveToken(token)
	{
		localStorage.setItem('PexelsApiToken', token);
		this.apiToken = token;
	}
}
