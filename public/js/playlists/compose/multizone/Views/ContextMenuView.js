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
 * The context menu of a zone, built from the template of the page where the mouse was clicked.
 */
export class ContextMenuView
{
	#template = document.getElementById("context-menu");
	#menu     = null;

	show(x, y)
	{
		this.#menu = document.createElement("div");
		this.#menu.style.position = "absolute";
		this.#menu.style.zIndex   = 1000;
		this.#menu.style.left     = x + "px";
		this.#menu.style.top      = y + "px";
		this.#menu.appendChild(this.#template.content.cloneNode(true));
		this.#menu.addEventListener('contextmenu', e => e.preventDefault());
		document.body.append(this.#menu);
	}

	remove()
	{
		this.#menu?.remove();
		this.#menu = null;
	}

	/**
	 * An entry of the menu which is shown: duplicate_zone, delete_zone, move_background …
	 */
	onClick(entryId, handler)
	{
		this.#menu.querySelector("#" + entryId).onclick = handler;
	}
}
