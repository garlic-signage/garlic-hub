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


export class GroupPropertiesView
{
	#group;

	constructor(toggleButtonFactory)
	{
		this.#group         = toggleButtonFactory.create(document.getElementById("object_group"));
	}

	hide()
	{
		this.#group.hide();
	}

	show(type)
	{
		this.update('display', type === 'group' ? 'ungroup' : 'group');
		this.#group.show();
	}

	grouping()
	{
		this.update('display', 'group');
	}

	ungrouping()
	{
		this.update('display', 'ungroup');
	}

	update(type, value)
	{
		this.#group.update(type, value);
	}

	get group()
	{
		return this.#group;
	}

	getGroupElement()
	{
		return this.#group.getElement();
	}
}
