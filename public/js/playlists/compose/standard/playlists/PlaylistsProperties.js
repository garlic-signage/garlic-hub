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
import {Utils} from "../../../../core/Utils.js";

export class PlaylistsProperties
{
	#playlistDuration = document.getElementById('playlistDuration');
	#totalItems       = document.getElementById('totalItems');
	#totalFilesize    = document.getElementById('totalFilesize');
	#toggleShuffle    = document.getElementById("toggleShuffle");
	#shufflePicking   = document.getElementById("shufflePicking");
	#playerExport     = document.getElementById("playerExport");
	#pickingValue     = 0;
	#playlistsService = null;
	#flashMessageHandler = null;
	#lang             = null;

	constructor(playlistsService, flashMessageHandler, lang)
	{
		this.#playlistsService = playlistsService;
		this.#flashMessageHandler = flashMessageHandler;
		this.#lang = lang;
	}

	init(playlistId)
	{
		this.#toggleShuffle.addEventListener('click', async () => {
			const result = await this.#sendSafe(() => this.#playlistsService.toggleShuffle(playlistId));
			if (result === null)
			{
				this.#toggleShuffle.checked = !this.#toggleShuffle.checked; // undo the click
				return;
			}

			if (this.#toggleShuffle.checked)
				this.#shufflePicking.parentElement.style.display = "block";
			else
				this.#shufflePicking.parentElement.style.display = "none";

			this.display(result.playlist_metrics);
			PlaylistsProperties.notifySave();
		});
		this.#shufflePicking.addEventListener('change', async () => {

			const result = await this.#sendSafe(() => this.#playlistsService.shufflePicking(playlistId, this.#shufflePicking.value));
			if (result === null)
			{
				this.#shufflePicking.value = this.#pickingValue; // undo the change
				return;
			}

			this.#pickingValue = this.#shufflePicking.value;
			this.display(result.playlist_metrics);

			PlaylistsProperties.notifySave();

		});
		this.#playerExport.addEventListener('click', async () =>
		{
			const result = await this.#sendSafe(() => this.#playlistsService.export(playlistId));
			if (result !== null)
				PlaylistsProperties.removeSave();
		});
	}

	static notifySave()
	{
		document.getElementById("playerExport").classList.add("notify-save");
	}

	static removeSave()
	{
		document.getElementById("playerExport").classList.remove("notify-save");
	}

	setOptions(playlist)
	{
		this.#toggleShuffle.checked = playlist.shuffle === 1;
		this.#pickingValue = playlist.shuffle_picking;

		if (playlist.export_time < playlist.last_update)
			PlaylistsProperties.notifySave();
	}

	display(playlistMetrics)
	{
		this.#playlistDuration.innerHTML = Utils.formatSecondsToTime(playlistMetrics.duration);
		this.#totalItems.innerHTML       = playlistMetrics.count_items;
		this.#totalFilesize.innerHTML    = Utils.formatBytes(playlistMetrics.filesize);

		this.#shufflePicking.disabled    = !this.#toggleShuffle.checked;

		if (this.#shufflePicking.disabled || playlistMetrics.count_items === this.#shufflePicking.length)
			return

		if (this.#pickingValue > playlistMetrics.count_items) // if something is deleted
			this.#pickingValue = playlistMetrics.count_items;

		this.#shufflePicking.innerHTML = "";
		const countItems = playlistMetrics.count_items

		for (let i = 1; i <= countItems; i++)
		{
			const option = document.createElement("option");
			option.value = i.toString();
			if(i < countItems)
				option.textContent = i.toString();
			else
				option.textContent = this.#lang.picking_all;
			if (i === this.#pickingValue)
				option.selected = true;

			this.#shufflePicking.appendChild(option);
		}

		this.#calculatePickingValue(countItems);
	}

	#calculatePickingValue(countItems)
	{
		if (this.#pickingValue > countItems)
		{
			 this.#pickingValue = countItems;
		}
	}

	/**
	 * Sends the request and shows errors as flash message.
	 * Returns the result on success, otherwise null.
	 */
	async #sendSafe(request)
	{
		try
		{
			const result = await request();
			if (result.success === true)
				return result;

			this.#flashMessageHandler.showError(result.error_message);
		}
		catch (e)
		{
			this.#flashMessageHandler.showError(e.message);
		}

		return null;
	}
}
