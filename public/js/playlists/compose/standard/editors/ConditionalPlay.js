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
'use strict';

import {PlaylistsProperties} from "../playlists/PlaylistsProperties.js";

export class ConditionalPlay
{
	#editDialog = null;
	#conditionalPlayForm = null;
	#conditionalPlayService = null;
	#itemData = null;
	#html = null;

	constructor(editDialog, conditionalPlayForm, conditionalPlayService)
	{
		this.#editDialog             = editDialog;
		this.#conditionalPlayForm    = conditionalPlayForm;
		this.#conditionalPlayService = conditionalPlayService;
	}

	async fetch (itemId)
	{
		const result = await this.#conditionalPlayService.fetchEditor(itemId);
		if (result.success === true)
		{
			this.#itemData = result.data;
			this.#html = result.html;
		}
		return result;
	}

	initDialog(actionButton)
	{
		this.#editDialog.setTitle(this.#itemData.item_name);
		this.#editDialog.setId(this.#itemData.item_id);
		this.#editDialog.setContent(this.#html);

		this.#conditionalPlayForm.init(this.#itemData.conditional);

		let saveCallBack = async (e) =>
		{
			e.preventDefault();
			let values = this.#conditionalPlayForm.collectValues();
			let result = await this.#conditionalPlayService.store(this.#itemData.item_id, values);
			if (result.success === false)
			{
				this.#editDialog.setErrorMessage(result.error_message);
			}
			else
			{
				this.#editDialog.closeDialog();
				PlaylistsProperties.notifySave();
				if (values.hasOwnProperty("date"))
					actionButton.classList.add("icon-values-setted");
				else
					actionButton.classList.remove("icon-values-setted");
			}
		}

		this.#editDialog.onSave(saveCallBack);
		this.#editDialog.onCancel();

		this.#editDialog.openDialog();

	}
}