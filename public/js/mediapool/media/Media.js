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

import {DirectoryView} from "../treeview/DirectoryView.js";
import {Utils}         from "../../core/Utils.js";

export class Media
{
    #mediaElement = null;
	#mediaData     = null;
	#mediaId       = ""
	#thumbnailUrl  = "";
	#originalUrl   = "";
	#fileName      = "";
	#filename      = "";
	#mimetype      = "";
	#description   = ""
	#filesize      = 0;
	#username      = "";
	#dimensions    = "";
	#duration      = 0;
	#mediaItem     = null;


	constructor(mediaElement, mediaData)
    {
        this.#mediaElement = mediaElement;
		this.#mediaId       = mediaData.media_id;
		// the files are stored outside the document root, the backend delivers signed media.php urls
		this.#thumbnailUrl  = mediaData.urls.thumbnail;
		this.#originalUrl   = mediaData.urls.original;
		this.#fileName      = mediaData.checksum + "." + mediaData.extension;
		this.#filename      = mediaData.filename;
		this.#mimetype		= mediaData.mimetype;
		this.#description   = mediaData.media_description
		this.#username      = mediaData.username;
		this.#filesize     	= Utils.formatBytes(mediaData.metadata.size);
		if (mediaData.metadata.dimensions !== undefined && Object.keys(mediaData.metadata.dimensions).length > 0 )
			this.#dimensions = mediaData.metadata.dimensions.width + "x" + mediaData.metadata.dimensions.height;
		if (mediaData.metadata.duration !== undefined && mediaData.metadata.duration > 0)
			this.#duration = mediaData.metadata.duration;
		this.#mediaData = mediaData;
    }

	get mediaId()
	{
		return this.#mediaId;
	}

	get thumbnailUrl()
	{
		return this.#thumbnailUrl;
	}

	get originalUrl()
	{
		return this.#originalUrl;
	}

	get fileName()
	{
		return this.#fileName;
	}

	get filename()
	{
		return this.#filename;
	}

	get filesize()
	{
		return this.#filesize;
	}

	get dimensions()
	{
		return this.#dimensions;
	}

	get duration()
	{
		return this.#duration;
	}

	get mediaItem()
	{
		return this.#mediaItem;
	}

	renderForDisplayInMediaPool()
    {
		this.#createThumbnail()

		const a = this.#mediaElement.querySelector("a");
		if (this.#hasDetailedView(this.#mimetype))
			a.href  = this.#originalUrl;
		else
		{
			a.classList.remove("glightbox");
			a.style.display = "none";
		}
        this.#mediaElement.querySelector(".media-owner").textContent    = this.#username;
        this.#mediaElement.querySelector(".media-filename").textContent = this.#filename;
        this.#mediaElement.querySelector(".media-filesize").textContent = this.#filesize;
        this.#mediaElement.querySelector(".media-mimetype").textContent = this.#mimetype;

        const dimensionsElement = this.#mediaElement.querySelector(".media-dimensions");
        if (this.#dimensions !== "")
        {
            dimensionsElement.textContent = this.#dimensions;
            dimensionsElement.parentElement.style.display = "block";
        }

        const durationElement = this.#mediaElement.querySelector(".media-duration");
        if (this.#duration > 0 )
        {
            durationElement.textContent = Utils.formatSecondsToTime(this.#duration);
            durationElement.parentElement.style.display = "block";
        }

		this.#mediaItem = this.#mediaElement.querySelector(".media-item");
		this.#mediaItem .addEventListener("dragstart", (event) => {
			DirectoryView.workaroundShitForMediaIdBecauseOfChrome = this.#mediaItem.getAttribute("data-media-id");
        });

        return this.#mediaItem;
    }

	renderSimple()
	{
		this.#createThumbnail();

		this.#mediaItem =  this.#mediaElement.querySelector(".media-item");

		return this.#mediaItem;
	}

	#createThumbnail()
	{
		this.#mediaElement.querySelector(".media-type-icon").classList.add(this.#detectMediaType(this.#mimetype));
		const mediaItem = this.#mediaElement.querySelector(".media-item");
		mediaItem.setAttribute("data-media-id", this.#mediaId);
		// consumers like the template composer need the original, not the displayed thumbnail
		mediaItem.setAttribute("data-original-url", this.#originalUrl);
		mediaItem.setAttribute("data-file-name", this.#fileName);
		const img = this.#mediaElement.querySelector("img");
		img.src = this.#thumbnailUrl;
		img.alt = "Thumbnail: " + this.#filename;
		img.setAttribute("data-title", this.#filename);
	}

	#hasDetailedView(mimetype)
	{
		const first = mimetype.split("/")[0]
		if (first === "audio" || first === "video" || first === "image")
			return true;

		return mimetype.split("/")[1] === "pdf";
	}

    #detectMediaType(mimetype)
    {
        const first = mimetype.split("/")[0]

        if (first === "audio")
            return "bi-music-note";
        else if (first === "video")
            return "bi-film";
        else if (first === "image")
            return "bi-image";

        switch(mimetype.split("/")[1])
        {
            case "pdf":
                return "bi-filetype-pdf";
            case "zip":
            case "widget":
            case "wgt":
                return "bi-file-zip";

        }

        return "bi-file-earmark";
    }
}