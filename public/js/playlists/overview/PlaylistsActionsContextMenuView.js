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

export class PlaylistsActionsContextMenuView
{
	#template = document.getElementById("playlistsActionsContextMenuTemplate");
	#composer;
	#settings;
	#shareMenuItem;
	#deleteMenuItem;
	#menu;

	constructor()
	{
		this.#menu = this.#template.content.cloneNode(true).firstElementChild;
	}

	initMenuItems()
	{
		this.#composer       = this.#menu.querySelector(".composer");
		this.#settings       = this.#menu.querySelector(".settings");
		this.#shareMenuItem  = this.#menu.querySelector(".share");
		this.#deleteMenuItem = this.#menu.querySelector(".delete-playlist");
	}

	placeMenu(x, y)
	{
		const menuWidth = this.#menu.offsetWidth;
		this.#menu.style.left = `${x - menuWidth}px`;
		this.#menu.style.top = `${y + window.scrollY}px`;
	}

	setComposerLink(playlistId)
	{
		this.#composer.querySelector('a').href = "/playlists/compose/" + playlistId;
	}

	setSettingsLink(playlistId)
	{
		this.#settings.querySelector('a').href = "/playlists/settings/" + playlistId;
	}

	get menu()
	{
		return this.#menu;
	}

	get shareMenuItem()
	{
		return this.#shareMenuItem;
	}

	get deleteMenuItem()
	{
		return this.#deleteMenuItem;
	}
}
