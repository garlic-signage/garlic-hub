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
export class TemplatesActions
{
	#deleteActions = document.getElementsByClassName("delete-template");
	#templatesService = null;

	constructor(templatesService)
	{
		this.#templatesService = templatesService;
	}
	
	init()
	{
		for (const action of this.#deleteActions)
		{
			action.addEventListener('click', async (event) =>
			{
				if (!confirm(event.target.dataset.confirm))
					return;

				await this.#templatesService.delete(event.target.dataset.deleteId);
				window.location.href = "/templates";
			});
		}
	}
}
