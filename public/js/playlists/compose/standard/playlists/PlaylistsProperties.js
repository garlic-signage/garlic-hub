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

			this.#pickingValue = Number(this.#shufflePicking.value);
			this.display(result.playlist_metrics);

			PlaylistsProperties.notifySave();

		});
		this.#playerExport.addEventListener('click', async () =>
		{
			if (PlaylistsProperties.#isRelease())
			{
				const result = await this.#sendSafe(() => this.#playlistsService.requestRelease(playlistId));
				if (result !== null)
					PlaylistsProperties.#showDone(lang.release_requested);
				return;
			}

			const result = await this.#sendSafe(() => this.#playlistsService.export(playlistId));
			if (result !== null)
				PlaylistsProperties.removeSave();
		});
	}

	/**
	 * A change: the export is due, or while the release is on, the request for it again.
	 */
	static notifySave()
	{
		const button = document.getElementById("playerExport");
		button.classList.add("notify-save");
		// a change of the admin: the playlist does not wait anymore
		PlaylistsProperties.#showWaiting(false);
		if (!PlaylistsProperties.#isRelease())
			return;

		button.disabled = false;
		button.querySelector(".label").textContent = lang.request_release;
	}

	static removeSave()
	{
		document.getElementById("playerExport").classList.remove("notify-save");
		PlaylistsProperties.#showWaiting(false);
	}

	setOptions(playlist)
	{
		this.#toggleShuffle.checked = playlist.shuffle === 1;
		this.#pickingValue = Number(playlist.shuffle_picking);

		// while the release is on, the button asks for it: the server knows whether this is done already,
		// or whether the admin released the playlist and nothing changed since
		if (PlaylistsProperties.#isRelease())
		{
			if (playlist.release_status === "ready")
				PlaylistsProperties.#showDone(lang.release_requested);
			else if (playlist.release_status === "released")
				PlaylistsProperties.#showDone(lang.released);
			else
				PlaylistsProperties.notifySave();
		}
		else if (playlist.export_time < playlist.last_update)
			PlaylistsProperties.notifySave();

		PlaylistsProperties.#showWaiting(playlist.release_status === "ready");
	}

	/**
	 * The user may not export: the button asks for the release.
	 */
	static #isRelease()
	{
		return document.getElementById("playerExport").dataset.release === "1";
	}

	/**
	 * Only the admin has the hint, and only while the release is on.
	 */
	static #showWaiting(waiting)
	{
		const hint = document.getElementById("releaseWaiting");
		if (hint !== null)
			hint.hidden = !waiting;
	}

	/**
	 * Nothing to ask for: the release waits or is done. A change asks again (see notifySave).
	 */
	static #showDone(label)
	{
		const button = document.getElementById("playerExport");
		PlaylistsProperties.removeSave();
		button.disabled = true;
		button.querySelector(".label").textContent = label;
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
