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

	init(openActions)
	{
		for (let i = 0; i < openActions.length; i++)
		{
			openActions[i].addEventListener('click', async (event) => {
				event.preventDefault();
				const currentId    = Number(event.target.dataset.id);
				const responseData = await this.#playerService.determineRights(currentId);
				let currentMenu    = this.#menu;
				if (responseData.can_edit === false)
					return;
				const deletes = currentMenu.querySelectorAll(".delete");
				if (responseData.can_delete === false)
				{
					deletes.forEach(el => el.remove());
					return;
				}
				else
				{
					deletes.forEach(el => {
						el.addEventListener('click', async (event) => {
							const ok = await Utils.confirmAction(el.dataset.confirm);
							if (ok)
							{
								const result = await this.#playerService.delete(currentId);
								if (result.success === true)
								{
									const ul = document.querySelector('ul[data-id="'+currentId+'"]');
									const li = ul?.closest('li');
									li?.remove();
								}
								else
									this.#flashMessagehandler.showError(result.error_message);
							}
							event.preventDefault();
						});
					});

				}
				currentMenu.style.left = `${event.pageX}px`;
				currentMenu.style.top = `${event.pageY}px`;
				document.body.appendChild(currentMenu);
				event.stopPropagation(); // not to close menu immediately

				const links = currentMenu.querySelectorAll('a');
				links.forEach(link =>
				{
					link.href = link.href + currentId;
				});

				document.addEventListener('click', () => currentMenu.remove(), { once: true });});
		}

	}
}