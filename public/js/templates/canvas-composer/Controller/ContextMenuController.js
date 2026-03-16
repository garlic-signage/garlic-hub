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

import {ComposerEventBus} from "../Utils/ComposerEventBus.js";

export class ContextMenuController
{
	#contextMenuView;
	#contextMenuService;
	#mediaDialogController;

	constructor(contextMenuView, contextMenuService, mediaDialogController)
	{
		this.#contextMenuView = contextMenuView;
		this.#contextMenuService = contextMenuService;
		this.#mediaDialogController = mediaDialogController;
		this.#initEvents();
	}


	#initEvents()
	{
		ComposerEventBus.addEventListener('mouseLeftUp', (e) =>
		{
			this.#contextMenuView.hide();
		});

		ComposerEventBus.addEventListener('mouseRightUp', (e) =>
		{
			const object = e.detail.target;
			if (!object)
			{
				this.#contextMenuView.hide();
				return;
			}

			if (object.type === "activeSelection" || object.type === "group")
				return;

			const isShape = ["text", "i-text", "textbox", "circle", "rect", "triangle", "polygon"]
				.includes(object.type);
			if (this.#contextMenuView.toggleLock !== null)
			{
				if (this.#contextMenuService.isLocked())
					this.#contextMenuView.showUnLocked();
				else
					this.#contextMenuView.showLocked();
			}

			if (this.#contextMenuView.toggleSelectable !== null)
			{
				if (this.#contextMenuService.isSelectable())
					this.#contextMenuView.showUnSelectable();
				else
					this.#contextMenuView.showSelectable();
			}

			if (isShape)
				this.#contextMenuView.hideReplaceImage();
			else
				this.#contextMenuView.showReplaceImage();

			const mouseup = e.detail.e;
			this.#contextMenuView.show(mouseup.clientX, mouseup.clientY);
		});

		this.#contextMenuView.replaceImage.addEventListener("click", () =>
		{
			ComposerEventBus.dispatchEvent(new CustomEvent("openMediaDialogForReplace"));
			this.#contextMenuView.hide();
		});
		this.#contextMenuView.duplicate.addEventListener("click", () =>
		{
			this.#contextMenuService.duplicate();
			this.#contextMenuView.hide();
		});
		this.#contextMenuView.delete.addEventListener("click", () =>
		{
			this.#contextMenuService.remove();
			this.#contextMenuView.hide();
		});

		if (this.#contextMenuView.toggleLock !== null)
		{
			this.#contextMenuView.toggleLock.addEventListener("click", () =>
			{
				this.#contextMenuService.toggleLockedStatus()
				this.#contextMenuView.hide();
			});
		}
		if (this.#contextMenuView.toggleSelectable !== null)
		{
			this.#contextMenuView.toggleSelectable.addEventListener("click", () =>
			{
				this.#contextMenuService.toggleSelectableStatus()
				this.#contextMenuView.hide();
			});
		}
		this.#contextMenuView.moveBackground.addEventListener("click", () =>
		{
			this.#contextMenuService.sendToBack();
			this.#contextMenuView.hide();
		});

		this.#contextMenuView.moveBack.addEventListener("click", () =>
		{
			this.#contextMenuService.sendBackwards();
			this.#contextMenuView.hide();
		});
		this.#contextMenuView.moveFront.addEventListener("click", () =>
		{
			this.#contextMenuService.bringForward();
			this.#contextMenuView.hide();
		});
		this.#contextMenuView.moveForeground.addEventListener("click", () =>
		{
			this.#contextMenuService.bringToFront();
			this.#contextMenuView.hide();
		});
	}


}
