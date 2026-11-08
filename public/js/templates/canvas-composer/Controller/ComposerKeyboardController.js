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

export class ComposerKeyboardController
{
	#composerKeyboardView;
	#transformService;
	#contextMenuService;
	#propertiesController
	#fabricWrapper;


	constructor(composerKeyboardView, transformservice, contextMenuService, propertiesController, fabricWrapper)
	{
		this.#composerKeyboardView = composerKeyboardView;
		this.#transformService = transformservice;
		this.#contextMenuService = contextMenuService;
		this.#propertiesController = propertiesController;
		this.#fabricWrapper = fabricWrapper;

		this.#initKeyboardEvents();
	}

	#initKeyboardEvents()
	{
		this.#composerKeyboardView.canvasWrap.addEventListener("keydown", (event) =>
		{
			// while a text is edited the keys belong to the text: Delete, the arrows, Ctrl+C/V
			if (this.#fabricWrapper.getActiveObject()?.isEditing === true)
				return;

			if (event.shiftKey &&
				(event.key === "ArrowLeft" || event.key === "ArrowRight" || event.key === "ArrowUp" || event.key === "ArrowDown"))
			{
				this.#transformService.moveActiveObject(event.key, 50);
			}
			else if (event.ctrlKey && !event.shiftKey && event.key.toUpperCase() === "Z")
			{
				this.#fabricWrapper.undo();
				this.#propertiesController.deactivateAllProperties();
			}
			else if ((event.ctrlKey && event.key.toUpperCase() === "Y") ||
				(event.ctrlKey && event.shiftKey && event.key.toUpperCase() === "Z"))
			{
				this.#fabricWrapper.redo();
				this.#propertiesController.deactivateAllProperties();
			}
			else if (event.ctrlKey && event.key.toUpperCase() === "D")
			{
				// not the bookmark of the browser
				event.preventDefault();
				this.#contextMenuService.duplicate()
			}
			else if (event.ctrlKey && event.key.toUpperCase() === "C")
			{
				this.#contextMenuService.copy();
			}
			else if (event.ctrlKey && event.key.toUpperCase() === "V")
			{
				this.#contextMenuService.paste();
			}
			else
			{
				switch (event.key) {
					case "Delete":
						this.#contextMenuService.remove();
						break;
					case "ArrowLeft":
					case "ArrowRight":
					case "ArrowUp":
					case "ArrowDown":
						this.#transformService.moveActiveObject(event.key, 1);
						break;
					default:
						break;
				}
			}
		}, false);
	}}
