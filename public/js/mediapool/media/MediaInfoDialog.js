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

export class MediaInfoDialog
{
    #dialogElement   = document.getElementById("editInfoDialog");
    #closeElement    = document.getElementById("closeInfoMediaDialog");
	#closeInfoDialog = document.getElementById("closeInfoDialog");
    #mediaService    = null;
	#flashMessageHandler = null;

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
				this.#dialogElement.querySelector(".media-filename").textContent = media.filename;
				this.#dialogElement.querySelector(".media-mimetype").textContent = media.mimetype;

				if (media.metadata.dimensions !== undefined && Object.keys(media.metadata.dimensions).length > 0)
				{
					this.#dialogElement.querySelector(".media-dimensions").textContent = media.metadata.dimensions.width + "x" + media.metadata.dimensions.height;
				}
				else
					this.#dialogElement.querySelector(".media-dimensions").parentElement.style.display = "none";

				if (media.metadata.duration !== undefined && media.metadata.duration > 0 )
					this.#dialogElement.querySelector(".media-duration").textContent = this.#formatSeconds(media.metadata.duration);
				else
					this.#dialogElement.querySelector(".media-duration").parentElement.style.display = "none";

				this.#dialogElement.querySelector(".media-owner").textContent = media.username;
				this.#dialogElement.querySelector(".media-uploaded-at").textContent = media.upload_time;
				this.#dialogElement.querySelector(".media-filesize").textContent = this.#formatBytes(media.metadata.size);
				this.#dialogElement.querySelector(".media-description").textContent = media.media_description;
				this.#dialogElement.querySelector(".stock-name").textContent = media.metadata.origin;

				if (media.metadata.page_url !== undefined && media.metadata.page_url !== "")
					this.#dialogElement.querySelector(".stock-page-url").href = media.metadata.page_url;
				else
					this.#dialogElement.querySelector(".stock-page-url").parentElement.style.display = "none";

				if (media.metadata.description !== undefined && media.metadata.description !== "")
					this.#dialogElement.querySelector(".stock-description").textContent = media.metadata.description;
				else
					this.#dialogElement.querySelector(".stock-description").parentElement.style.display = "none";

				const username = media.metadata.user?.username || null;
				if (username !== null)
					this.#dialogElement.querySelector(".stock-username").textContent = username;
				else
					this.#dialogElement.querySelector(".stock-username").parentElement.style.display = "none";

				const realname = media.metadata.user?.name || null;
				if (realname !== null)
					this.#dialogElement.querySelector(".stock-realname").textContent = realname;
				else
					this.#dialogElement.querySelector(".stock-realname").parentElement.style.display = "none";

				const userUrl = media.metadata.user?.url || null;
				if (userUrl !== null)
					this.#dialogElement.querySelector(".stock-user-url").textContent = userUrl;
				else
					this.#dialogElement.querySelector(".stock-user-url").parentElement.style.display = "none";

				this.#dialogElement.showModal();
			})
			.catch(err => this.#flashMessageHandler.showError(err.message));
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

	#formatBytes(bytes)
	{
		if (bytes >= 1073741824)  // 1 GB
			return (bytes / 1073741824).toFixed(2) + " GB";
		else if (bytes >= 1048576)  // 1 MB
			return (bytes / 1048576).toFixed(2) + " MB";
		else if (bytes >= 1024)  // 1 KB
			return (bytes / 1024).toFixed(2) + " KB";
		else
			return bytes + " Bytes"; // less 1 KB
	}

	#formatSeconds(seconds)
	{
		const hours = Math.floor(seconds / 3600);
		const minutes = Math.floor((seconds % 3600) / 60);
		const secs = seconds % 60;

		return `${String(hours).padStart(2, "0")}:${String(minutes).padStart(2, "0")}:${String(secs).padStart(2, "0")}`;
	}
}