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
import {Utils} from "../../core/Utils.js";

export class TemplatesActionsContextMenu
{
	#templatesService = null;
	#flashMessageHandler = null;
	#contextMenuView = null;
	#controller = null;
	#currentTemplateId = 0;
	#shareDialog = null;

	constructor(contextMenuView, flashMessageHandler, templatesService, shareDialog)
	{
		this.#shareDialog           = shareDialog;
		this.#contextMenuView       = contextMenuView;
		this.#flashMessageHandler   = flashMessageHandler;
		this.#templatesService      = templatesService;
		this.#controller            = new AbortController();
	}

	async init(event)
	{
		this.#currentTemplateId = Number(event.target.dataset.actionId);
		const responseData = await this.#templatesService.determineRights(this.#currentTemplateId );

		if (!responseData.can_edit)
			return;

		this.#contextMenuView.initMenuItems();
		this.#contextMenuView.setComposerLink(this.#currentTemplateId);
		this.#contextMenuView.setSettingsLink(this.#currentTemplateId);
		this.#shareDialog.attachTo(this.#contextMenuView.shareMenuItem, this.#currentTemplateId, responseData.can_share);

		if (!responseData.can_delete)
			this.#contextMenuView.deleteMenuItem.remove();
		else
			this.#deleteEventListener();

		document.body.appendChild(this.#contextMenuView.menu);
		this.#contextMenuView.placeMenu(event.clientX, event.clientY)

		document.addEventListener('click', () => {
			this.#controller.abort(); // Killt delete-Listener
			this.#contextMenuView.menu.remove();
		}, { once: true });

	}

	#deleteEventListener()
	{
		this.#contextMenuView.deleteMenuItem.addEventListener('click', async (e) => {
			e.preventDefault();
			const ok = await Utils.confirmAction(this.#contextMenuView.deleteMenuItem.dataset.confirm);
			if (ok)
			{
				const result = await this.#templatesService.delete(this.#currentTemplateId);
				if (result.success)
					document.querySelector(`ul[data-id="${this.#currentTemplateId}"]`)?.closest('li')?.remove();
				else
					this.#flashMessageHandler.showError(result.error_message);
			}
		}, { signal: this.#controller.signal });
	}

}