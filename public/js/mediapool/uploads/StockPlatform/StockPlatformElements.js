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

export class StockPlatformElements
{
	#selectStockPlatform  = document.getElementById("selectStockPlatform");
	#radioSearchConfig    = document.getElementById("radioSearchConfig");
	#checkSearchPlatform  = document.getElementById("checkSearchPlatform");
	#checkConfigPlatform  = document.getElementById("checkConfigPlatform");

	#searchTerms          = document.getElementById("searchTerms");
	#searchStockPlatform  = document.getElementById("searchStockPlatform");
	#platformApiToken     = document.getElementById("platformApiToken");
	#savePlatformApiToken = document.getElementById("savePlatformApiToken");

	#searchInPlatform     = document.getElementById("searchInPlatform");
	#configPlatform       = document.getElementById("configPlatform");

	#searchResultsArea    = document.getElementById("searchResultsArea");
	#previewPlatformArea  = document.getElementById("previewPlatformArea");
	#startPlatformsUpload = document.getElementById("startPlatformsUpload")
	#startFileUpload      = document.getElementById("startPlatformsUpload")
	#downloadStatus       = document.getElementById("downloadStatus");
	#radioSelectMediatype = document.getElementById("radioSelectMediatype");
	#searchMediatypeVideos = document.getElementById("searchMediatypeVideos");
	_searchMediatypeImages = document.getElementById("searchMediatypeImages");

	get selectStockPlatform()
	{
		return this.#selectStockPlatform;
	}

	get radioSearchConfig()
	{
		return this.#radioSearchConfig;
	}

	get checkSearchPlatform()
	{
		return this.#checkSearchPlatform;
	}

	get checkConfigPlatform()
	{
		return this.#checkConfigPlatform;
	}

	get searchTerms()
	{
		return this.#searchTerms;
	}

	get searchStockPlatform()
	{
		return this.#searchStockPlatform;
	}

	get platformApiToken()
	{
		return this.#platformApiToken;
	}

	get savePlatformApiToken()
	{
		return this.#savePlatformApiToken;
	}

	get searchInPlatform()
	{
		return this.#searchInPlatform;
	}

	get configPlatform()
	{
		return this.#configPlatform;
	}

	get searchResultsArea()
	{
		return this.#searchResultsArea;
	}

	get previewPlatformArea()
	{
		return this.#previewPlatformArea;
	}

	get startPlatformsUpload()
	{
		return this.#startPlatformsUpload;
	}

	get startFileUpload()
	{
		return this.#startFileUpload;
	}

	get downloadStatus()
	{
		return this.#downloadStatus;
	}

	get radioSelectMediatype()	{ return this.#radioSelectMediatype;}


	get searchMediatypeVideos()
	{
		return this.#searchMediatypeVideos;
	}

	get searchMediatypeImages()
	{
		return this._searchMediatypeImages;
	}

	toggleSearchInPlatform(isVisible)
	{
		if (isVisible)
		{
			this.#searchInPlatform.style.display = "block";
			this.#configPlatform.style.display = "none";
		}
		else
		{
			this.#searchInPlatform.style.display = "none";
			this.#configPlatform.style.display = "block";
		}

	}

	toogleHasVideo(hasToken, hasVideo)
	{
		if (!hasToken)
		{
			this.#radioSelectMediatype.style.display = "none";
			return;
		}

		if (hasVideo)
			this.#radioSelectMediatype.style.display = "block";
		else
			this.#radioSelectMediatype.style.display = "none";
	}

	/**
	 * Without a platform: as the page starts, nothing to configure or search.
	 */
	hidePlatformOptions()
	{
		this.#radioSearchConfig.style.display    = "none";
		this.#radioSelectMediatype.style.display = "none";
		this.#searchInPlatform.style.display     = "none";
		this.#configPlatform.style.display       = "none";
	}

	toggleSearchConfig(isVisible)
	{
		if (isVisible)
			this.#radioSearchConfig.style.display = "block";
		else
			this.#radioSearchConfig.style.display = "none";
	}

	addPlatform(name)
	{
		const option = document.createElement('option');
		option.value = name;
		option.textContent = name;

		this.#selectStockPlatform.appendChild(option);
	}
}