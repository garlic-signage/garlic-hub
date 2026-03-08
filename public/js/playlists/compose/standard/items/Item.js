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
import {PlaylistsProperties} from "../playlists/PlaylistsProperties.js";

export class Item
{
	#itemData = null
	#itemsService = null;
	#widgetFactory = null;
	#conditionalPlayFactory = null;
	#triggerFactory = null;
	#playlistItem = null;
	#cmsEdition = document.getElementById("cms_edition").value;
	#belongsToMasterPlaylist = false;
	#linkPlaylistAction    = null;
	#editTemplateAction    = null;
	#conditionalPlayAction = null;
	#editTriggerAction     = null;
	#editWidgetAction      = null;
	#editSettingsAction    = null;
	#copyItemAction        = null;
	#deleteItemAction      = null;
	#itemName     = null;
	#editItemName = false;
	#itemDuration = null;
	#isItemDurationInProcess = false;

	constructor(itemData, itemsService, widgetFactory, conditionalPlayFactory, triggerFactory)
	{
		this.#itemData = itemData;
		this.#itemsService = itemsService;
		this.#widgetFactory = widgetFactory;
		this.#conditionalPlayFactory = conditionalPlayFactory;
		this.#triggerFactory = triggerFactory;
	}


	set belongsToMasterPlaylist(value)
	{
		this.#belongsToMasterPlaylist = value;
	}

	get linkPlaylistAction()
	{
		return this.#linkPlaylistAction;
	}

	get editTemplateAction()
	{
		return this.#editTemplateAction;
	}

	get conditionalPlayAction()
	{
		return this.#conditionalPlayAction;
	}

	get editTriggerAction()
	{
		return this.#editTriggerAction;
	}

	get editWidgetAction()
	{
		return this.#editWidgetAction;
	}

	get editSettingsAction()
	{
		return this.#editSettingsAction;
	}

	get copyItemAction()
	{
		return this.#copyItemAction;
	}

	get deleteItemAction()
	{
		return this.#deleteItemAction;
	}

	buildItemElement()
	{
		const template = document.getElementById("playlistItemTemplate");
		const playlistItem = template.content.cloneNode(true);
		this.#playlistItem = playlistItem;

		const listItem = playlistItem.querySelector(".playlist-item");
		listItem.dataset.mediaId = this.#itemData.file_resource;
		listItem.id = "itemId-" + this.#itemData.item_id;

		const thumbnail = playlistItem.querySelector('img');
		thumbnail.src = "/" + this.#itemData.paths.thumbnail.replace('public/', '');
		thumbnail.alt = this.#itemData.item_name;

		this.#itemName = playlistItem.querySelector('.item-name');
		this.#buildItemName()

		this.#itemDuration = playlistItem.querySelector('.item-duration');
		this.#buildItemDuration();

		playlistItem.querySelector('.actions').setAttribute('data-item-id', this.#itemData.item_id)
		this.#initActions();

		return playlistItem;
	}

	#buildItemName()
	{
		this.#itemName.textContent = this.#itemData.item_name;
		if (this.#itemData.item_type === "mediapool")
		{
			this.#itemName.contentEditable = true;
			this.#itemName.addEventListener('keydown', (event) => {
				if (event.key === 'Enter')
				{
					event.preventDefault(); // prevent a carriage return.
					this.#itemsService.editItem(this.#itemData.item_id, 'item_name', this.#itemName.textContent)
					this.#editItemName = false;
					this.#itemName.blur();
					PlaylistsProperties.notifySave();
				}
			});
			this.#itemName.addEventListener('focus', () => {
				this.#editItemName = true;
			});
			this.#itemName.addEventListener('blur', () => {
				if (this.#editItemName)
				{
					this.#itemsService.editItem(this.#itemData.item_id, 'item_name', this.#itemName.textContent)
					this.#editItemName = false;
					PlaylistsProperties.notifySave();
				}
			});
		}
	}

	#buildItemDuration()
	{
		this.#itemDuration.textContent = Utils.formatSecondsToTime(this.#itemData.item_duration);
		this.#itemDuration.dataset.seconds = this.#itemData.item_duration;
		this.#itemDuration.addEventListener('click', (event) => {
			const target = event.target;
			if (target.tagName === "SPAN")
			{
				const inputGroup = document.createElement("span");
				inputGroup.classList.add("item-duration-input");
				const inputField = document.createElement("input");
				inputField.type = 'number';
				inputField.min = 0;
				inputField.step = 1;
				inputField.value = target.dataset.seconds;
				inputGroup.appendChild(inputField);

				const resetButton = document.createElement("i");
				resetButton.classList.add("duration-reset");
				resetButton.classList.add("bi");
				resetButton.classList.add("bi-arrow-clockwise");
				inputGroup.appendChild(resetButton);

				resetButton.addEventListener('click', async () => {
					this.#isItemDurationInProcess = true;
					const result = await this.#itemsService.fetchDefaultSeconds(this.#itemData.item_id);
					if (!result.success)
						return;

					inputField.value = result.item.default_duration;
					await this.#saveDuration(inputField);
					resetButton.remove();
				});

				inputField.addEventListener('blur', () => {
					if (this.#isItemDurationInProcess)
					{
						this.#isItemDurationInProcess = false;
						return;
					}

					this.#saveDuration(inputField);
					resetButton.remove();
				});

				inputField.addEventListener('keydown', (event) => {
					if (event.key === 'Enter')
					{
						this.#isItemDurationInProcess = true;
						this.#saveDuration(inputField);
						resetButton.remove();
					}
				});

				target.parentNode.replaceChild(inputGroup, target);
				inputField.focus();
			}
		});
	}

	async #saveDuration(inputElement)
	{
		const result = await this.#itemsService.editItem(this.#itemData.item_id, 'item_duration', inputElement.value);
		if (!result.success)
			return;

		this.#itemDuration.dataset.seconds = result.data;
		this.#itemDuration.textContent = Utils.formatSecondsToTime(result.data);

		inputElement.parentNode.replaceChild(this.#itemDuration, inputElement);
		inputElement.remove();
		PlaylistsProperties.notifySave();
	}

	#initActions()
	{
		if (this.#itemData.item_type === "playlist")
		{
			this.#linkPlaylistAction = this.#playlistItem.querySelector('.link-playlist');
			this.#linkPlaylistAction.href = "/playlists/composer/" + this.#itemData.file_resource;
		}
		else
			this.#playlistItem.querySelector('.link-playlist').remove();

		if (this.#itemData.item_type === "template")
		{
			this.#editTemplateAction = this.#playlistItem.querySelector('.edit-template');
			this.#editTemplateAction.href = "/playlist-item/compose/" + this.#itemData.item_id;
		}
		else
			this.#playlistItem.querySelector('.edit-template').remove();

		if (this.#cmsEdition !== "edge")
		{
			this.#editSettingsAction    = this.#playlistItem.querySelector('.edit-settings');
		}
		else
		{
			this.#conditionalPlayAction = this.#playlistItem.querySelector('.conditional-play');
			if (this.#itemData.conditional === undefined || this.#itemData.conditional === "" || this.#itemData.conditional === "a:0:{}")
				this.#conditionalPlayAction.classList.remove("icon-values-setted");
			else
				this.#conditionalPlayAction.classList.add("icon-values-setted");

			if (this.#belongsToMasterPlaylist)
			{
				this.#editTriggerAction     = this.#playlistItem.querySelector('.edit-trigger');
				if (this.#itemData.begin_trigger === undefined || this.#itemData.begin_trigger === "" || this.#itemData.begin_trigger === "a:0:{}")
					this.#editTriggerAction.classList.remove("icon-values-setted");
				else
					this.#editTriggerAction.classList.add("icon-values-setted");
			}
			else
			{
				this.#playlistItem.querySelector('.edit-trigger').remove();
			}

			this.#playlistItem.querySelector('.edit-settings').remove();
		}

		if (this.#conditionalPlayAction !== null)
		{
			this.#conditionalPlayAction.addEventListener("click", async () =>
			{
				let conditionalPlay = this.#conditionalPlayFactory.create();
				await conditionalPlay.fetch(this.#itemData.item_id);
				conditionalPlay.initDialog(this.#conditionalPlayAction);
			});
		}
		if (this.#editTriggerAction !== null)
		{
			this.#editTriggerAction.addEventListener("click", async () =>
			{
				let trigger = this.#triggerFactory.create();
				await trigger.fetchBeginTrigger(this.#itemData.item_id);
				trigger.initDialog(this.#editTriggerAction);
			});
		}
		if (this.#itemData.mimetype === "application/widget" && this.#itemData.content_data !== "")
		{
			this.#editWidgetAction  = this.#playlistItem.querySelector('.edit-widget');
			this.#editWidgetAction.addEventListener("click", async () =>
			{
				let widget = this.#widgetFactory.create();
				await widget.fetch(this.#itemData.item_id);
				widget.initDialog();
			});
		}
		else
			this.#playlistItem.querySelector('.edit-widget').remove();

		if (this.#editTemplateAction !== null)
		{
			this.#editTemplateAction.addEventListener("click", async () =>
			{

			});
		}

		if (this.#cmsEdition !== "edge" && (this.#itemData.item_type === "template" || this.#itemData.item_type === "playlist"))
			this.#copyItemAction = this.#playlistItem.querySelector('.copy-item');
		else
			this.#playlistItem.querySelector('.copy-item').remove();

		this.#deleteItemAction = this.#playlistItem.querySelector('.delete-item');
	}


}