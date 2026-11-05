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

/**
 * The list of the zones beside the canvas, an entry has the id of its zone.
 */
export class ZoneListView
{
	#container = document.getElementById("zones_container");

	add(id, name, onClick)
	{
		let list = this.#container.querySelector('ul');
		if (!list)
		{
			list    = document.createElement('ul');
			list.id = "zones_list"
			this.#container.appendChild(list);
		}

		const item       = document.createElement('li');
		item.id          = id;
		item.textContent = name;
		item.addEventListener("click", () => onClick(id));

		list.appendChild(item);
	}

	highlight(id)
	{
		this.#container.querySelectorAll('li').forEach((item) => item.classList.remove('active'));
		this.#find(id)?.classList.add('active');
	}

	remove(id)
	{
		this.#find(id)?.remove();
	}

	rename(id, name)
	{
		const item = this.#find(id);
		if (item)
			item.textContent = name;
	}

	#find(id)
	{
		return this.#container.querySelector(`li[id="${CSS.escape(id)}"]`);
	}
}
