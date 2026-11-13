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

export class MediaSelectorView
{
	#mediaFactory = null;
	#mediaItems = {};
	// Stryker disable next-line StringLiteral: displayMediaList() looks the list up again
	#mediaListElement = document.getElementById("mediaList");
	#selectorTemplate = document.getElementById("mediaSelectorTemplate");
	#selectorElement = null;

	constructor(mediaFactory)
	{
		this.#mediaFactory = mediaFactory;
	}

	get mediaItems()
	{
		return this.#mediaItems;
	}

	getMediaListElement()
	{
		// because cache can prevent to execute displayMediaList
		return document.getElementById("mediaList");
	}

	loadSelectorTemplate()
	{
		return this.#selectorTemplate.content.cloneNode(true);
	}


	displayMediaList(mediaDataList)
	{
		this.#mediaListElement = this.getMediaListElement();
		this.#mediaListElement.innerHTML = "";

		for (const mediaData of mediaDataList)
		{
			let media = this.#mediaFactory.create(mediaData);
			this.#mediaItems[media.mediaId] = media;
			const mediaItem = media.renderSimple();
			mediaItem.setAttribute("data-select-id", media.mediaId);
			this.#mediaListElement.appendChild(mediaItem);
		}
	}

}