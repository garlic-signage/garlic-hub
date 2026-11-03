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

export class PlayerActionsContextMenuView
{
	#template = document.getElementById("playerActionsContextMenuTemplate");
	#assignMenuItem;
	#unassignMenuItem;
	#pushMenuItem;
	#gotoMenuItem;
	#previewMenuItem;
	#deleteMenuItem;
	#menu;

	constructor()
	{
		this.#menu = this.#template.content.cloneNode(true).firstElementChild;
	}

	initMenuItems()
	{
		this.#deleteMenuItem = this.#menu.querySelector(".delete");
		this.#assignMenuItem = this.#menu.querySelector(".assign");
		this.#unassignMenuItem = this.#menu.querySelector(".unassign");
		this.#pushMenuItem = this.#menu.querySelector(".push");
		this.#gotoMenuItem = this.#menu.querySelector(".goto");
		this.#previewMenuItem = this.#menu.querySelector(".preview");
	}

	placeMenu(x, y)
	{
		const menuWidth = this.#menu.offsetWidth;
		this.#menu.style.left = `${x - menuWidth}px`;
		this.#menu.style.top = `${y + window.scrollY}px`;
	}

	setGotoLink(playlistId)
	{
		this.#gotoMenuItem.querySelector('a').href = "playlists/compose/" + playlistId;
	}

	/**
	 * The preview player (public/js/player/preview) in a new tab: it plays as this player, so it gets its playlist
	 * from the SMIL index like the device. The cache stays in memory.
	 */
	setPreviewLink(uuid, playerName)
	{
		const params = new URLSearchParams({
			index: location.origin + "/smil-index",
			uuid: uuid,
			name: playerName,
			storage: "memory"
		});
		this.#previewMenuItem.querySelector('a').href = "/js/player/preview/index.html?" + params.toString();
	}


	get menu()
	{
		return this.#menu;
	}

	get assignMenuItem()
	{
		return this.#assignMenuItem;
	}

	get unassignMenuItem()
	{
		return this.#unassignMenuItem;
	}

	get pushMenuItem()
	{
		return this.#pushMenuItem;
	}

	get gotoMenuItem()
	{
		return this.#gotoMenuItem;
	}

	get previewMenuItem()
	{
		return this.#previewMenuItem;
	}

	get deleteMenuItem()
	{
		return this.#deleteMenuItem;
	}
}