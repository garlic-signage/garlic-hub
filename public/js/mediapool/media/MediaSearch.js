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

import {UploadDialogElements} from "../uploads/UploadDialogElements.js";

/**
 * The search field above the media list: from two letters on the list shows the results of all directories the
 * user may read, an empty field brings the directory back. Choosing a directory in the tree ends the search.
 * The filter of the kind of media beside it counts for both.
 */
export class MediaSearch
{
	static MIN_LENGTH = 2;
	static DELAY_MS   = 300;

	#inputElement;
	#filterElement;
	#mediaService;
	#mediaList;
	#directoryView;
	#currentPathElement;
	#flashMessageHandler;
	#lang;
	#timer       = null;
	#requestId   = 0;
	#isSearching = false;

	constructor(inputElement, filterElement, mediaService, mediaList, directoryView, currentPathElement, flashMessageHandler, lang)
	{
		this.#inputElement        = inputElement;
		this.#filterElement       = filterElement;
		this.#mediaService        = mediaService;
		this.#mediaList           = mediaList;
		this.#directoryView       = directoryView;
		this.#currentPathElement  = currentPathElement;
		this.#flashMessageHandler = flashMessageHandler;
		this.#lang                = lang;
	}

	init()
	{
		this.#inputElement.addEventListener("input", () => {
			clearTimeout(this.#timer);
			this.#timer = setTimeout(() => this.#search(), MediaSearch.DELAY_MS);
		});

		// the kind of media counts for the search and for the directory
		this.#filterElement.addEventListener("change", () => {
			this.#mediaList.filter = this.#filterElement.value;
			clearTimeout(this.#timer);
			if (this.#isSearching)
				this.#search();
			else
				this.#directoryView.reloadCurrentNode();
		});

		// a directory of the tree was chosen during a search
		this.#mediaList.element.addEventListener("directoryloaded", () => {
			if (!this.#isSearching)
				return;

			this.#isSearching        = false;
			this.#inputElement.value = "";
		});
	}

	async #search()
	{
		const term = this.#inputElement.value.trim();
		// only the answer of the last input counts, a slower one before it does not overwrite it
		const requestId = ++this.#requestId;
		if (term.length < MediaSearch.MIN_LENGTH)
		{
			if (this.#isSearching)
			{
				this.#isSearching = false;
				this.#directoryView.reloadCurrentNode();
			}
			return;
		}

		try
		{
			const results = await this.#mediaService.searchMedia(term, this.#mediaList.filter);
			if (requestId !== this.#requestId)
				return;

			this.#isSearching = true;
			UploadDialogElements.openUploadDialog.disabled = true;
			this.#mediaList.showSearchResults(results);
			this.#currentPathElement.innerText = this.#lang.search_results + " (" + results.length + ")";
		}
		catch (err)
		{
			this.#flashMessageHandler.showError(err.message);
		}
	}
}
