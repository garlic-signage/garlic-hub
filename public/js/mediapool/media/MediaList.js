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

export class MediaList
{
    #mediaListElement = null;
    #mediaFactory = null
    #contextMenuFactory = null;
	#mediaService = null;
	#setListView = document.getElementById("setListView");
	#setGridView = document.getElementById("setGridView");
	#rights = null;

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

		const results = await this.#mediaService.loadMediaByNodeId(nodeId);

		this.render(results);
		this.#initDrag();
	}

    render(data)
    {
        this.#mediaListElement.innerHTML = ""; // Clear previous content
		if (localStorage.getItem("media-list-view") !== null)
			this.#mediaListElement.classList.add(localStorage.getItem("media-list-view"));

        data.forEach((media) => {
            this.#addMediaToList(media);
        });

        const lightbox = GLightbox({
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
			if (this.#rights.create !== null && this.#rights.create === true)
			{
				contextMenu.addRemoveEvent(remove);
				contextMenu.addEditEvent(edit);
				contextMenu.addCloneEvent(clone, this.#addMediaToList.bind(this));
			}
			else
			{
				remove.remove();
				edit.remove();
				clone.remove();
			}
        });
    }

	#initDrag()
	{
		document.querySelectorAll(".media-drag-icon").forEach(icon => {
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
