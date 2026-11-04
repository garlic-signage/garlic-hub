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
 * The context menu of a row, cloned from a <template>. The entries are found by their class.
 */
export class ActionsContextMenuView
{
	#menu;

	constructor(templateId)
	{
		this.#menu = document.getElementById(templateId).content.cloneNode(true).firstElementChild;
	}

	get menu()
	{
		return this.#menu;
	}

	item(className)
	{
		return this.#menu.querySelector("." + className);
	}

	setLink(className, href)
	{
		this.item(className).querySelector("a").href = href;
	}

	remove(className)
	{
		this.item(className)?.remove();
	}

	placeMenu(x, y)
	{
		const menuWidth = this.#menu.offsetWidth;
		this.#menu.style.left = `${x - menuWidth}px`;
		this.#menu.style.top = `${y + window.scrollY}px`;
	}
}
