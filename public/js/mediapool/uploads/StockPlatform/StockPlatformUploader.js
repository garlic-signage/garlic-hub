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

import { BaseUploader } from "../BaseUploader.js";

export class StockPlatformUploader extends BaseUploader
{
	#stockPlatformFactory = null;
	#stockPlatform = null;
	#shownIds      = new Set();
	#loadingPage   = false;

	constructor(stockPlatformFactory, domElements, directoryView, uploaderDialog, fetchClient)
	{
		super(domElements, directoryView, uploaderDialog, fetchClient);
		this.#stockPlatformFactory = stockPlatformFactory;

		for (const [key, value] of Object.entries(this.#stockPlatformFactory.platforms))
		{
			this.domElements.addPlatform(key);
		}

		const lastPlatform = localStorage.getItem("lastPlatform");
		if (lastPlatform !== null)
		{
			this.#selectPlatform(lastPlatform);
			if (this.#stockPlatform !== null)
				this.domElements.selectStockPlatform.value = lastPlatform;
		}

		this.domElements.selectStockPlatform.addEventListener("change", (event) => this.#selectPlatform(event.target.value));
		this.domElements.savePlatformApiToken.addEventListener("click", (event) => this.#saveApiToken(event));
		this.domElements.checkSearchPlatform.addEventListener("click", (event) => this.#toggleSearchConfig(event));
		this.domElements.checkConfigPlatform.addEventListener("click", (event) => this.#toggleSearchConfig(event));
		this.domElements.searchStockPlatform.addEventListener("click", (event) => this.#search(event));
		this.domElements.startFileUpload.addEventListener("click", () => this.#startDownload());

		this.domElements.searchResultsArea.addEventListener('scroll', () => this.#loadNextPage());
	}

	async #startDownload()  //Downlod from Medis-Platform, but upload to us
	{
		if (this.#stockPlatform === null)
			return;

		const checkboxes = document.querySelectorAll('.result-checkbox');
		for (const checkbox of checkboxes)
		{
			if (!checkbox.checked)
				continue;

			if (!Object.hasOwn(this.#stockPlatform.resultList, checkbox.id))
				continue;

			const uri = this.#stockPlatform.resultList[checkbox.id].downloadUrl;
			const url = new URL(uri);
			const pathname = url.pathname;
			const filename = pathname.split('/').pop();

			this.domElements.downloadStatus.textContent = "Start downloading: " + filename;

			const mediaUrl = await this.#stockPlatform.determineMediaDownloadUrl(uri);
			// the mark stays after a failure for a second try
			if (!mediaUrl)
			{
				this.domElements.downloadStatus.textContent = "Download failed: " + filename;
				continue;
			}

			let metadata   = this.#stockPlatform.resultList[checkbox.id].metadata;
			if (!await this.uploadExternalFile(mediaUrl, metadata))
			{
				this.domElements.downloadStatus.textContent = "Download failed: " + filename;
				continue;
			}

			this.domElements.downloadStatus.textContent = "Finish downloading: " + filename;

			checkbox.checked = false;

		}
	}

	#selectPlatform(platform)
	{
		// the placeholder "-" or a platform of an older version, which is not registered any more
		if (!Object.hasOwn(this.#stockPlatformFactory.platforms, platform))
		{
			this.#stockPlatform = null;
			this.domElements.hidePlatformOptions();
			localStorage.removeItem("lastPlatform");
			return;
		}

		this.#stockPlatform = this.#stockPlatformFactory.selectPlatform(platform);

		this.#configPlatformOptions(this.#stockPlatform.hasApiToken());

		localStorage.setItem("lastPlatform", platform);
	}

	#saveApiToken()
	{
		if (this.#stockPlatform === null)
			return;

		this.#stockPlatform.saveToken(this.domElements.platformApiToken.value);
		this.#configPlatformOptions(this.#stockPlatform.hasApiToken());
	}

	#configPlatformOptions(hasToken)
	{
		this.domElements.toggleSearchConfig(hasToken);
		this.domElements.toggleSearchInPlatform(hasToken);
		this.domElements.toogleHasVideo(hasToken, this.#stockPlatform.hasVideos);
	}

	#toggleSearchConfig(event)
	{
		if (event.target.id === "checkSearchPlatform")
			this.domElements.toggleSearchInPlatform(event.target.checked);
		else
			this.domElements.toggleSearchInPlatform(!event.target.checked);

		if (this.#stockPlatform !== null && this.#stockPlatform.hasApiToken())
			this.domElements.platformApiToken.value = this.#stockPlatform.apiToken;

	}

	async #search(event)
	{
		if (this.#stockPlatform === null)
			return;

		this.#stockPlatform.resetResultList();

		this.domElements.searchResultsArea.innerHTML = "";

		let mediatype = "images";
		if (this.#stockPlatform.hasVideos && this.domElements.searchMediatypeVideos.checked)
			mediatype = "videos";

		this.#shownIds.clear();
		const results = await this.#stockPlatform.search(this.domElements.searchTerms.value, mediatype);
		// an error returns nothing
		if (!results)
			return;

		this.#addSearchResults(results);
	}

	async #loadNextPage()
	{
//		return; // temporary disabled to save requests during developing
		if (this.domElements.searchResultsArea.scrollTop + this.domElements.searchResultsArea.clientHeight >= this.domElements.searchResultsArea.scrollHeight)
		{
			// every scroll event at the end would load the page again
			if (this.#stockPlatform === null || this.#loadingPage)
				return;

			this.#loadingPage = true;
			try
			{
				const results = await this.#stockPlatform.loadNextPage();
				if (results)
					this.#addSearchResults(results);
			}
			finally
			{
				this.#loadingPage = false;
			}
		}
	}

	/**
	 * The platform returns all results up to now: the ones shown already are left out.
	 */
	#addSearchResults(results)
	{
		for (const [id, item] of Object.entries(results))
		{
			if (this.#shownIds.has(id))
				continue;

			this.#shownIds.add(id);
			this.#addSearchResult(id, item);
		}
	}

	#addSearchResult(id, item)
	{
		const container = document.getElementById("resultMediaTemplate").content.cloneNode(true).firstElementChild;
		const img = container.querySelector(".result-thumbnail");
		img.src = item.thumb;
		img.alt = item.metadata.description || item.metadata.tags || "";
		let preview = null;
		if (item.type === "image")
		{
			preview = document.createElement("img");
		}
		else if (item.type === "video")
		{
			preview = document.createElement("video");
			preview.autoplay = true;
			preview.muted = true;
			preview.loop = true
			preview.preload = "none";
			preview.poster = item.thumb;
		}

		if (preview === null)
		{
			console.log("Unknown media type: " + item.type);
			return;
		}
		preview.className = "result-preview";

		preview.src = item.preview;
		preview.alt = item.metadata.description || item.metadata.tags || "";
		container.append(preview);

		const hoverPreview = container.querySelector(".media-view-detail");
		hoverPreview.addEventListener("mouseover", (event) => this.showPreview(event));
		hoverPreview.addEventListener("mouseout", (event) => this.stopPreview(event, preview));

		const downloadChecker = container.querySelector(".result-checkbox");
		downloadChecker.setAttribute("id", id);
		downloadChecker.addEventListener("click", (event) => this.#markedDownload(event));

		this.domElements.searchResultsArea.appendChild(container);
	}

	showPreview(event)
	{
		const preview = event.target.parentElement.querySelector(".result-preview");
		if (preview.tagName === "VIDEO")
			preview.play();

		preview.style.display = "block";
	}

	stopPreview(event, preview)
	{
		if (preview.tagName === "VIDEO")
			preview.pause();

		preview.style.display = "none";
	}

	#markedDownload(event)
	{
		const checkboxes  = document.querySelectorAll('.result-checkbox');
		const isAnyChecked = Array.from(checkboxes).some(checkbox => checkbox.checked);

		this.domElements.startFileUpload.disabled = !isAnyChecked;
	}


}