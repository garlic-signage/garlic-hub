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


export class GroupPropertiesController
{
	#groupPropertiesView;
	#groupPropertiesService;

	constructor(groupPropertiesView, groupPropertiesService)
	{
		this.#groupPropertiesView = groupPropertiesView;
		this.#groupPropertiesService = groupPropertiesService;

		this.#groupPropertiesView.group.getElement().addEventListener("click", () =>
		{
			let object = this.#groupPropertiesService._getActiveObject();
			if (object.type === 'activeSelection')
			{
				this.#groupPropertiesService.toGroup(object);
				this.#groupPropertiesView.ungrouping();
			}
			else if (object.type === 'group')
			{
				this.#groupPropertiesService.toActiveSelection(object);
				this.#groupPropertiesView.grouping();
			}
		});

	}

	activate(object)
	{
		this.#groupPropertiesView.show(object.type);
	}

	deactivate()
	{
		this.#groupPropertiesView.hide();
	}


}
