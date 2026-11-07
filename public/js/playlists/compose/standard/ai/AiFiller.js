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

import {WaitOverlay}         from "../../../../core/WaitOverlay.js";
import {Utils}               from "../../../../core/Utils.js";
import {PlaylistsProperties} from "../playlists/PlaylistsProperties.js";

/**
 * "Fill with AI" of the insert menu: the user enters a theme and a duration, the AI suggests media of the
 * mediapool as a list with ticks. The ticked ones are added at the end of the playlist like dropped media.
 */
export class AiFiller
{
	#panelTemplate = document.getElementById("aiFillTemplate");
	#itemTemplate  = document.getElementById("aiSuggestionItemTemplate");
	#playlistsService;
	#itemsService;
	#itemList;
	#dropTarget;
	#playlistId;
	#flashMessageHandler;
	#lang;

	constructor(playlistsService, itemsService, itemList, dropTarget, playlistId, flashMessageHandler, lang)
	{
		this.#playlistsService    = playlistsService;
		this.#itemsService        = itemsService;
		this.#itemList            = itemList;
		this.#dropTarget          = dropTarget;
		this.#playlistId          = playlistId;
		this.#flashMessageHandler = flashMessageHandler;
		this.#lang                = lang;
	}

	show(container)
	{
		container.replaceChildren(this.#panelTemplate.content.cloneNode(true));

		const form = container.querySelector("#aiFillForm");
		form.addEventListener("submit", async (event) => {
			event.preventDefault();
			await this.#suggest(container);
		});
		container.querySelector("#aiInsertChecked").addEventListener("click", async () => {
			await this.#insertChecked(container);
		});
	}

	async #suggest(container)
	{
		const list   = container.querySelector("#aiSuggestion");
		const insert = container.querySelector("#aiInsertChecked");
		const waitOverlay = new WaitOverlay();
		waitOverlay.start();
		try
		{
			const items = await this.#playlistsService.suggestWithAi(
				this.#playlistId,
				container.querySelector("#aiTheme").value,
				Number(container.querySelector("#aiMinutes").value)
			);
			list.replaceChildren(...items.map(item => this.#buildEntry(item)));
			insert.hidden = items.length === 0;
		}
		catch (err)
		{
			this.#flashMessageHandler.showError(err.message);
		}
		finally
		{
			waitOverlay.stop();
		}
	}

	#buildEntry(item)
	{
		const entry = this.#itemTemplate.content.cloneNode(true).firstElementChild;
		entry.setAttribute("data-media-id", item.media_id);
		entry.querySelector("img").src = item.thumbnail;
		entry.querySelector("img").alt = item.filename;
		entry.querySelector(".ai-suggestion-name").textContent = item.filename;
		entry.querySelector(".ai-suggestion-duration").textContent = Utils.formatSecondsToTime(item.seconds);

		return entry;
	}

	/**
	 * One after the other in the order of the suggestion: an error stops, the ones before stay inserted.
	 */
	async #insertChecked(container)
	{
		const checked = [...container.querySelectorAll("#aiSuggestion li")].filter(entry => entry.querySelector("input").checked);
		let inserted  = 0;
		const waitOverlay = new WaitOverlay();
		waitOverlay.start();
		try
		{
			for (const entry of checked)
			{
				const result = await this.#itemsService.insertMedia(entry.getAttribute("data-media-id"), this.#playlistId, this.#dropTarget.children.length + 1);
				if (result.success === false)
				{
					this.#flashMessageHandler.showError(result.error_message);
					break;
				}

				this.#itemList.createPlaylistItem(result.data.item);
				this.#itemList.displayPlaylistMetrics(result.data.playlist_metrics);
				entry.remove();
				inserted++;
			}
		}
		finally
		{
			waitOverlay.stop();
		}

		if (inserted === 0)
			return;

		PlaylistsProperties.notifySave();
		this.#flashMessageHandler.showSuccess(this.#lang.ai_inserted);
		container.querySelector("#aiInsertChecked").hidden = container.querySelectorAll("#aiSuggestion li").length === 0;
	}
}
