/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

import {MediaApiConfig} from "./MediaApiConfig.js";
import {FolderTagging}  from "./FolderTagging.js";

export class MediaList
{
    #mediaListElement = null;
    #mediaFactory = null
    #contextMenuFactory = null;
	#mediaService = null;
	#setListView = document.getElementById("setListView");
	#setGridView = document.getElementById("setGridView");
	#rights = null;
	#lightbox = null;
	#filter = "";

    constructor(mediaListElement, mediaFactory, contextMenuFactory, mediaService)
    {
        this.#mediaListElement   = mediaListElement;
        this.#mediaFactory       = mediaFactory;
        this.#contextMenuFactory = contextMenuFactory;
		this.#mediaService       = mediaService;

		this.#setViewActions();
    }

	async loadMediaListByNode(nodeId, rights)
	{
		this.#rights = rights;

		const results = await this.#mediaService.loadFilteredMediaByNodeId(nodeId, encodeURIComponent(this.#filter));

		this.render(results);
		// a search which was shown before is over
		this.#mediaListElement.dispatchEvent(new CustomEvent("directoryloaded"));
	}

	/**
	 * The results of a search instead of a directory: each one knows whether the user may edit it.
	 */
	showSearchResults(results)
	{
		this.#rights = null;

		this.render(results);
	}

	get element()
	{
		return this.#mediaListElement;
	}

	/**
	 * The kind of media the directories and the search show: images, videos, audios, documents or "" for all.
	 */
	get filter()
	{
		return this.#filter;
	}

	set filter(value)
	{
		this.#filter = value;
	}

    render(data)
    {
        this.#mediaListElement.innerHTML = ""; // Clear previous content
		if (localStorage.getItem("media-list-view") !== null)
			this.#mediaListElement.classList.add(localStorage.getItem("media-list-view"));

        data.forEach((media) => {
            this.#addMediaToList(media);
        });

        // one lightbox per folder: the one of the folder before goes
        this.#lightbox?.destroy();
        this.#lightbox = GLightbox({
            plyr: {
                css: MediaApiConfig.PYR_CSS_PATH,
                js: MediaApiConfig.PYR_JS_PATH
            },
            width: "90vw",
            height: "90vh",
            loop: false,
            autoplayVideos: true
        });
    }

	async moveMediaTo(mediaId, nodeId)
	{
		await this.#mediaService.moveMedia(mediaId, nodeId);
		this.#deleteMediaDomBy(mediaId);
	}

    #deleteMediaDomBy(mediaId)
    {
        const element = document.querySelector(`[data-media-id="${mediaId}"]`);
        if (element)
            element.remove();
    }

    #addMediaToList(media)
    {
        const mediaObject = this.#mediaFactory.create(media);
        const mediaItem   = mediaObject.renderForDisplayInMediaPool();

        this.#addContextMenu(mediaItem);
        this.#addDrag(mediaItem);

        this.#mediaListElement.appendChild(mediaItem);
    }

    #addContextMenu(mediaItem)
    {
        mediaItem.addEventListener("contextmenu", (event) => {
            event.preventDefault();

            const contextMenu    = this.#contextMenuFactory.create(mediaItem);
            contextMenu.show(event);

			contextMenu.addInfoEvent(document.getElementById("infoMedia"));
			const remove = document.getElementById("removeMedia");
			const edit   = document.getElementById("editMedia");
			const clone  = document.getElementById("cloneMedia");
			// only there when the user has an AI for the tagging
			const suggestTags = document.getElementById("suggestTags");
			if (this.#canEdit(mediaItem))
			{
				contextMenu.addRemoveEvent(remove);
				contextMenu.addEditEvent(edit);
				contextMenu.addCloneEvent(clone, this.#addMediaToList.bind(this));
				if (suggestTags !== null && FolderTagging.isTaggable(mediaItem.getAttribute("data-mimetype") ?? ""))
					contextMenu.addSuggestTagsEvent(suggestTags);
				else if (suggestTags !== null)
					suggestTags.remove();
			}
			else
			{
				remove.remove();
				edit.remove();
				clone.remove();
				if (suggestTags !== null)
					suggestTags.remove();
			}
        });
    }

	#canEdit(mediaItem)
	{
		if (mediaItem.hasAttribute("data-editable"))
			return mediaItem.getAttribute("data-editable") === "true";

		return this.#rights?.create === true;
	}

	/**
	 * Per medium, so a clone added to the list can be dragged too.
	 */
	#addDrag(mediaItem)
	{
		mediaItem.querySelectorAll(".media-drag-icon").forEach(icon => {
			icon.addEventListener("dragstart", event => {
				let article = event.target.closest(".media-item"); // Finde das übergeordnete <article>
				event.dataTransfer.setData("text/plain", ""); // Notwendig für Drag & Drop in manchen Browsern
				event.dataTransfer.setDragImage(article, 0, 0); // Setzt das <article> als Drag-Objekt
				article.setAttribute("draggable", "true"); // Macht das ganze Element draggable
			});

			icon.addEventListener("dragend", event => {
				let article = event.target.closest(".media-item");
				article.removeAttribute("draggable"); // Entfernt das Attribut nach dem Drag-Vorgang
			});
		});
	}

	#setViewActions()
	{
		if (localStorage.getItem("media-list-view") === null)
		{
			this.#mediaListElement.classList.remove("media-list-grid-view");
			this.#mediaListElement.classList.add("media-list-list-view");
			localStorage.setItem("media-list-view", "media-list-list-view");
		}

		this.#setListView.addEventListener("click", () =>
		{
			this.#mediaListElement.classList.remove("media-list-grid-view");
			this.#mediaListElement.classList.add("media-list-list-view");
			localStorage.setItem("media-list-view", "media-list-list-view");
		});

		this.#setGridView.addEventListener("click", () =>
		{
			this.#mediaListElement.classList.remove("media-list-list-view");
			this.#mediaListElement.classList.add("media-list-grid-view");
			localStorage.setItem("media-list-view", "media-list-grid-view");

		});
	}
}
