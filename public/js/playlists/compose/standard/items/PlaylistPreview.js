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

import {Utils} from "../../../../core/Utils.js";
import {MediaApiConfig} from "../../../../mediapool/media/MediaApiConfig.js";

/**
 * The preview of the playlist in a lightbox (GLightbox): previous and next go through the items of this
 * playlist, a nested playlist shows the list of its items.
 * The items are loaded when the preview opens: order, widget values and signed urls are the current ones.
 */
export class PlaylistPreview
{
	#itemsService = null;
	#flashMessageHandler = null;
	#lightbox = null;

	constructor(itemsService, flashMessageHandler)
	{
		this.#itemsService        = itemsService;
		this.#flashMessageHandler = flashMessageHandler;
	}

	async open(playlistId, itemId)
	{
		const result = await this.#itemsService.loadPreview(playlistId);
		if (!result.success)
		{
			this.#flashMessageHandler.showError(result.error_message);
			return;
		}

		const items = result.data;
		if (items.length === 0)
			return;

		const index = Math.max(0, items.findIndex((item) => item.item_id === Number(itemId)));

		this.#lightbox?.destroy();
		this.#lightbox = GLightbox({
			elements: items.map((item) => this.#createElement(item)),
			plyr: {
				css: MediaApiConfig.PYR_CSS_PATH,
				js: MediaApiConfig.PYR_JS_PATH
			},
			width: "90vw",
			height: "90vh",
			loop: false,
			autoplayVideos: true
		});
		this.#lightbox.openAt(index);
	}

	#createElement(item)
	{
		switch (item.type)
		{
			case "video":
				return {href: item.url, type: "video", title: item.item_name};
			case "audio":
				return {content: this.#createAudio(item.url), title: item.item_name};
			case "pdf":
			case "widget": // widget.php sandboxes the widget, see there
				return {href: item.url, type: "external", title: item.item_name};
			case "playlist":
				return {content: this.#createNestedList(item.playlist_id), title: item.item_name};
			default:
				return {href: item.url, type: "image", title: item.item_name};
		}
	}

	#createAudio(url)
	{
		const audio = document.createElement("audio");
		audio.controls = true;
		audio.src = url;
		audio.classList.add("preview-audio");

		return audio;
	}

	/**
	 * Only the list of the items, the nested playlist is not played and not opened.
	 */
	#createNestedList(playlistId)
	{
		const list = document.createElement("ul");
		list.classList.add("preview-nested-playlist");

		this.#itemsService.loadByPlaylistId(playlistId).then((result) => {
			if (!result.success)
				return;

			for (const item of result.data.items)
			{
				const entry = document.createElement("li");

				const thumbnail = document.createElement("img");
				thumbnail.src = item.urls.thumbnail;
				thumbnail.alt = "";

				const name = document.createElement("span");
				name.textContent = item.item_name;

				const duration = document.createElement("span");
				duration.classList.add("preview-nested-duration");
				duration.textContent = Utils.formatSecondsToTime(item.item_duration);

				entry.append(thumbnail, name, duration);
				list.appendChild(entry);
			}
		});

		return list;
	}
}
