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

export class ContextMenuView
{
	#composerContextMenu = document.getElementById("composerContextMenu");

	// menu points
	#replaceImage = document.getElementById("replaceImage");
	#duplicate = document.getElementById("duplicate");
	#delete = document.getElementById("delete");
	#toggleLock = document.getElementById("toggleLock");
	#lock = document.getElementById("lock");
	#unlock = document.getElementById("unlock");
	#toggleSelectable = document.getElementById("toggleSelectable");
	#selectable = document.getElementById("selectable");
	#unselectable = document.getElementById("unselectable");
	#moveBackground = document.getElementById("moveBackground");
	#moveBack = document.getElementById("moveBack");
	#moveFront = document.getElementById("moveFront");
	#moveForeground = document.getElementById("moveForeground");

	constructor()
	{
		this.#composerContextMenu.style.position = "fixed";
		this.#composerContextMenu.style.zIndex = 1000;
		this.#composerContextMenu.style.display = "none";
	};


	show(x, y)
	{
		this.#composerContextMenu.style.visibility = "hidden";
		this.#composerContextMenu.style.display = "block";

		// correct position when clicked in edges
		const rect         = this.#composerContextMenu.getBoundingClientRect();
		const windowHeight = window.innerHeight;
		const windowWidth  = window.innerWidth;

		if (y + rect.height > windowHeight)
			y = windowHeight - rect.height;

		if (x + rect.width > windowWidth)
			x = windowWidth - rect.width;

		this.#composerContextMenu.style.left = x + "px";
		this.#composerContextMenu.style.top = y + "px";
		this.#composerContextMenu.style.visibility = "visible";
	}

	hide()
	{
		this.#composerContextMenu.style.display = "none";
	}

	showLocked()
	{
		this.#lock.style.display = "inline";
		this.#unlock.style.display = "none";
	}

	showUnLocked()
	{
		this.#lock.style.display = "none";
		this.#unlock.style.display = "inline";
	}

	showSelectable()
	{
		this.#selectable.style.display = "inline";
		this.#unselectable.style.display = "none";
	}

	showUnSelectable()
	{
		this.#selectable.style.display = "none";
		this.#unselectable.style.display = "inline";
	}


	get replaceImage()
	{
		return this.#replaceImage;
	}

	hideReplaceImage()
	{
		this.#replaceImage.style.display = "none";
	}

	showReplaceImage()
	{
		this.#replaceImage.style.display = "block";
	}

	get duplicate()
	{
		return this.#duplicate;
	}

	get delete()
	{
		return this.#delete;
	}

	get toggleLock()
	{
		return this.#toggleLock;
	}

	get toggleSelectable()
	{
		return this.#toggleSelectable;
	}


	get moveBackground()
	{
		return this.#moveBackground;
	}

	get moveBack()
	{
		return this.#moveBack;
	}

	get moveFront()
	{
		return this.#moveFront;
	}

	get moveForeground()
	{
		return this.#moveForeground;
	}
}
