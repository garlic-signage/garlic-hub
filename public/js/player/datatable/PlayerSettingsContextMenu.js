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

export class PlayerSettingsContextMenu
{
	#menu = {};
	#playerService;
	#flashMessagehandler

	constructor(contextMenuTemplate, flashMessagehandler, playerService)
	{
		this.#menu = contextMenuTemplate.content.cloneNode(true).firstElementChild;
		this.#flashMessagehandler = flashMessagehandler;
		this.#playerService = playerService;
	}

	init(openActions) {
		for (let i = 0; i < openActions.length; i++) {
			openActions[i].addEventListener('click', async (event) => {
				event.preventDefault();
				const currentId = Number(event.target.dataset.id);
				const responseData = await this.#playerService.determineRights(currentId);

				if (!responseData.can_edit) return;

				const deleteMenuItem = this.#menu.querySelector(".delete");

				if (!responseData.can_delete) {
					deleteMenuItem.remove();
					return;
				}

				const controller = new AbortController();

				deleteMenuItem.addEventListener('click', async (e) => {
					e.preventDefault();
					const ok = await Utils.confirmAction(deleteMenuItem.dataset.confirm);
					if (ok)
					{
						const result = await this.#playerService.delete(currentId);
						if (result.success)
							document.querySelector(`ul[data-id="${currentId}"]`)?.closest('li')?.remove();
						 else
							this.#flashMessagehandler.showError(result.error_message);
					}
				}, { signal: controller.signal });

				this.#menu.style.left = `${event.pageX}px`;
				this.#menu.style.top = `${event.pageY}px`;
				document.body.appendChild(this.#menu);

				this.#menu.querySelectorAll('a').forEach(link => {
					link.href = link.href + currentId;
				});

				document.addEventListener('click', () => {
					controller.abort(); // Killt delete-Listener
					this.#menu.remove();
				}, { once: true });

				event.stopPropagation();
			});
		}
	}
}