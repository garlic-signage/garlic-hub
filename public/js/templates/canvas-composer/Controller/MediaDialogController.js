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
'use strict';

import {ComposerEventBus} from "../Utils/ComposerEventBus.js";

export class MediaDialogController
{
	#mediaDialogView;
	#mediaSelector;
	#insertService;
	#isOpen = false;

	constructor(mediaDialogView, mediaSelector, insertService)
	{
		this.#mediaDialogView = mediaDialogView;
		this.#mediaSelector   = mediaSelector;
		this.#insertService   = insertService;

		ComposerEventBus.addEventListener("openMediaDialogForInsert", async () =>
		{
			this.#mediaDialogView.addMedia.style.display = "inline";
			this.#mediaDialogView.applyMedia.style.display = "none";
			this.#mediaDialogView.dialogName.innerText = lang.add_image;
			this.#mediaSelector.enableMultiSelect();

			await this.#mediaSelector.showSelector(this.#mediaDialogView.mediaSelectorElement);
			this.#mediaDialogView.mediaSelectorDialog.showModal();

			this.#isOpen = true;
		})
		ComposerEventBus.addEventListener("openMediaDialogForReplace", async () =>
		{
			this.#mediaDialogView.addMedia.style.display = "none";
			this.#mediaDialogView.applyMedia.style.display = "inline";
			this.#mediaDialogView.dialogName.innerText = lang.replace_image;
			this.#mediaSelector.disableMultiSelect();

			await this.#mediaSelector.showSelector(this.#mediaDialogView.mediaSelectorElement);
			this.#mediaDialogView.mediaSelectorDialog.showModal();

			this.#isOpen = true;
		})
		this.#mediaDialogView.addMedia.addEventListener("click", async (event) =>
		{
			let selectedMediaList = this.#mediaSelector.getSelectedMedia();
			for (const [i, { id, src }] of selectedMediaList.entries())
			{
				await this.#insertService.insertImage(id, src, i);
			}
			this.remove();
		}, { once: true });
		this.#mediaDialogView.applyMedia.addEventListener("click", async (event) =>
		{
			let selectedMedia = this.#mediaSelector.getSelectedMedia()[0];
			await this.#insertService.replaceImage(selectedMedia.id, selectedMedia.src);
			this.remove();
		}, { once: true });

		this.#mediaDialogView.closeEditMediaDialog.addEventListener("click",() =>
		{
			this.remove();
		});
		this.#mediaDialogView.closeDialogButton.addEventListener("click", () =>
		{
			this.remove();
		});

	}

	remove()
	{
		this.#mediaDialogView.mediaSelectorDialog.close();

		this.#isOpen = false;
	}


	get isOpen()
	{
		return this.#isOpen;
	}

	async displayMediaSelector()
	{
		await this.#mediaSelector.showSelector(this.#mediaDialogView.mediaSelectorElement);
		this.#mediaDialogView.mediaSelectorDialog.showModal();

		this.#isOpen = true;
	}

}
