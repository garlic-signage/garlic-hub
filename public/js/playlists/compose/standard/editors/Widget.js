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

export class Widget
{
	#editDialog = null;
	#widgetForm = null;
	#widgetsService = null;
	#widgetData =  null;

	constructor(editDialog, widgetForm, widgetsService)
	{
		this.#editDialog = editDialog;
		this.#widgetForm = widgetForm;
		this.#widgetsService = widgetsService;
	}

	async fetch (itemId)
	{
		this.#widgetData = await this.#widgetsService.fetchConfiguration(itemId)
	}

	initDialog()
	{
		this.#editDialog.setTitle(this.#widgetData.data.item_name);
		this.#editDialog.setId(this.#widgetData.data.item_id);
		this.#widgetForm.parsePreferences(this.#widgetData.data.preferences, this.#widgetData.data.values, this.#widgetData.data.item_id);
		this.#editDialog.setContent(this.#widgetForm.getForm());

		let saveCallBack = async (e) =>
		{
			e.preventDefault();
			let values = this.#widgetForm.collectValues();
			let result = await this.#widgetsService.storeWidgetValues(this.#widgetData.data.item_id, values);
			if (result.success === false)
			{
				this.#editDialog.setErrorMessage(result.error_message);
			}
			else
			{
				this.#editDialog.closeDialog();
				PlaylistsProperties.notifySave();
			}

		}
		this.#editDialog.onSave(saveCallBack);
		this.#editDialog.onCancel();

		this.#editDialog.openDialog();

	}




}
