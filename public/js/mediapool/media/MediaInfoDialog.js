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

import {Utils} from "../../core/Utils.js";

export class MediaInfoDialog
{
    #dialogElement   = document.getElementById("editInfoDialog");
    #closeElement    = document.getElementById("closeInfoMediaDialog");
	#closeInfoDialog = document.getElementById("closeInfoDialog");
    #mediaService    = null;
	#flashMessageHandler = null;
	#hiddenRows = [];

    constructor(mediaService, flashMessageHandler)
    {
        this.#mediaService   = mediaService;
		this.#flashMessageHandler = flashMessageHandler;
        this.#addCancelEvent();
    }

    show(currentMedia)
    {
		this.#mediaService.getMediaById(currentMedia.getAttribute("data-media-id"))
			.then((media) =>
			{
				// the rows hidden for the media before are shown again
				for (const row of this.#hiddenRows)
					row.style.display = "";
				this.#hiddenRows = [];

				this.#dialogElement.querySelector(".media-filename").textContent = media.filename;
				this.#dialogElement.querySelector(".media-mimetype").textContent = media.mimetype;

				if (media.metadata.dimensions !== undefined && Object.keys(media.metadata.dimensions).length > 0)
				{
					this.#dialogElement.querySelector(".media-dimensions").textContent = media.metadata.dimensions.width + "x" + media.metadata.dimensions.height;
				}
				else
					this.#hide(".media-dimensions");

				if (media.metadata.duration !== undefined && media.metadata.duration > 0 )
					this.#dialogElement.querySelector(".media-duration").textContent = Utils.formatSecondsToTime(media.metadata.duration);
				else
					this.#hide(".media-duration");

				this.#dialogElement.querySelector(".media-owner").textContent = media.username;
				this.#dialogElement.querySelector(".media-uploaded-at").textContent = media.upload_time;
				this.#dialogElement.querySelector(".media-filesize").textContent = Utils.formatBytes(media.metadata.size);
				this.#dialogElement.querySelector(".media-description").textContent = media.media_description;
				if (media.tags.length > 0)
					this.#dialogElement.querySelector(".media-tags").textContent = media.tags.join(", ");
				else
					this.#hide(".media-tags");
				this.#dialogElement.querySelector(".stock-name").textContent = media.metadata.origin;

				if (media.metadata.page_url !== undefined && media.metadata.page_url !== "")
					this.#dialogElement.querySelector(".stock-page-url").href = media.metadata.page_url;
				else
					this.#hide(".stock-page-url");

				if (media.metadata.description !== undefined && media.metadata.description !== "")
					this.#dialogElement.querySelector(".stock-description").textContent = media.metadata.description;
				else
					this.#hide(".stock-description");

				const username = media.metadata.user?.username || null;
				if (username !== null)
					this.#dialogElement.querySelector(".stock-username").textContent = username;
				else
					this.#hide(".stock-username");

				const realname = media.metadata.user?.name || null;
				if (realname !== null)
					this.#dialogElement.querySelector(".stock-realname").textContent = realname;
				else
					this.#hide(".stock-realname");

				const userUrl = media.metadata.user?.url || null;
				if (userUrl !== null)
					this.#dialogElement.querySelector(".stock-user-url").textContent = userUrl;
				else
					this.#hide(".stock-user-url");

				this.#dialogElement.showModal();
			})
			.catch(err => this.#flashMessageHandler.showError(err.message));
	}

	#hide(selector)
	{
		const row = this.#dialogElement.querySelector(selector).parentElement;
		row.style.display = "none";
		this.#hiddenRows.push(row);
	}

	#addCancelEvent()
	{
		this.#closeInfoDialog.addEventListener("click", () =>
		{
			this.#dialogElement.close("cancel");
		});
		this.#closeElement.addEventListener("click", () =>
		{
			this.#dialogElement.close("cancel");
		});
	}
}
