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
import {PlaylistsProperties} from "../playlists/PlaylistsProperties.js";

export class ItemList
{
	#itemFactory = null;
	#dropTarget = null;
	#itemsService = null;
	#isMasterPlaylist = false;
	#itemsList   = {};
	#playlistProperties = null;
	#playlistId = 0;
	#playlistPreview = null;

	constructor(itemFactory, itemsService, dropTarget, playlistProperties, playlistPreview)
	{
		this.#playlistPreview = playlistPreview;
		this.#itemFactory  = itemFactory;
		this.#dropTarget   = dropTarget;
		this.#itemsService = itemsService;
		this.#playlistProperties = playlistProperties;
	}

	async buildPlaylist(playlistId)
	{
		this.#playlistId = playlistId;
		const results = await this.#itemsService.loadByPlaylistId(playlistId);
		if (!results.success)
			return;

		this.#isMasterPlaylist = (results.data.playlist.playlist_mode === "master");
		for (const item of results.data.items)
		{
			this.createPlaylistItem(item);
		}

		this.#playlistProperties.setOptions(results.data.playlist); // must be first because of ShufflePicking
		this.displayPlaylistMetrics(results.data.playlist_metrics);

	}

	displayPlaylistMetrics(playlistMetrics)
	{
		this.#playlistProperties.display(playlistMetrics);
	}

	createPlaylistItem(itemData, position = null)
	{
		const item = this.#itemFactory.create(itemData, this.#itemsService);
		item.belongsToMasterPlaylist = this.#isMasterPlaylist;
		this.#itemsList[itemData.item_id] = item;
		
		// console.log('Listenlänge', this.#dropTarget.children.length, 'Position', position);

		if (position === null || this.#dropTarget.children.length < position)
			this.#dropTarget.appendChild(item.buildItemElement());
		else
		{
			const prevItem = this.#dropTarget.children[position - 1];
			this.#dropTarget.insertBefore(item.buildItemElement(), prevItem);
		}

		this.#createActions(item);
	}

	#createActions(item)
	{
		if (item.previewAction !== null)
		{
			item.previewAction.addEventListener('click', () => {
				const id = item.previewAction.parentElement.getAttribute('data-item-id');
				this.#playlistPreview.open(this.#playlistId, id);
			});
		}

		if (item.deleteItemAction !== null)
		{
			item.deleteItemAction.addEventListener('click', async () => {
				const id = item.deleteItemAction.parentElement.getAttribute('data-item-id');
				const results =  await this.#itemsService.delete(this.#playlistId, id);
				if (!results.success)
					return;

				this.displayPlaylistMetrics(results.data.playlist_metrics)
				PlaylistsProperties.notifySave();
				document.getElementById("itemId-" + id).remove();
			});
		}
	}

}