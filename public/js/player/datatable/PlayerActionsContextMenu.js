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

export class PlayerActionsContextMenu
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

	init(openActions)
	{
		for (let i = 0; i < openActions.length; i++)
		{
			openActions[i].addEventListener('click', async (event) => {
				event.preventDefault();
				const currentId = Number(event.target.dataset.actionId);
				const responseData = await this.#playerService.determineRights(currentId);

				if (!responseData.can_edit)
					return;

				const deleteMenuItem = this.#menu.querySelector(".delete");
				const assignMenuItem = this.#menu.querySelector(".assign");
				const unassignMenuItem = this.#menu.querySelector(".unassign");
				const pushMenuItem = this.#menu.querySelector(".push");
				const gotoMenuItem = this.#menu.querySelector(".goto");

				if (!responseData.can_delete)
				{
					deleteMenuItem.remove();
					return;
				}
				if (!responseData.has_playlist)
				{
					unassignMenuItem.remove();
					gotoMenuItem.remove();
				}

				if (!responseData.has_playlist && !responseData.is_intranet)
					pushMenuItem.remove();

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

				document.body.appendChild(this.#menu);

				const menuWidth = this.#menu.offsetWidth;
				this.#menu.style.left = `${event.clientX - menuWidth}px`;
				this.#menu.style.top = `${event.clientY}px`;

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