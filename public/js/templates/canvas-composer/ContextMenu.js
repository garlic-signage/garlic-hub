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

export class ContextMenu
{
	#canvasView = {};
	#mediaDialog = {};
	options;
	context_menu;

	constructor(canvasView, mediaDialog)
	{
		this.#canvasView = canvasView;
		this.#mediaDialog = mediaDialog;
	}

	show(options)
	{
		// open when multiple objects are selected or grouped
		if (options.target.type === "activeSelection" || options.target.type === "group")
			return;

		if (options.target.type === "text" || options.target.type === "i-text" || options.target.type === "textbox" ||
			options.target.type === "circle" || options.target.type === "rect" || options.target.type === "triangle" || options.target.type === "polygon"
		)
		{
			this.build(options)
			document.getElementById("change_image").style.display = 'none';
		}
		else {
			this.build(options)
			this.initLoadImageEvent();
		}
		this.initDublicateEvent();
		this.initRemoveEvent();
		this.initZIndexEvent();
		this.initLockEvent();
	}

	build(options)
	{
		this.options = options;

		this.context_menu = document.createElement("div");
		this.context_menu.style.position = "absolute";
		this.context_menu.style.zIndex = 1000;
		this.context_menu.style.left = this.options.e.pageX + "px";
		this.context_menu.style.top = this.options.e.pageY + "px";
		this.context_menu.innerHTML = document.getElementById("context-menu").innerHTML;
		document.body.append(this.context_menu);
	}

	initZIndexEvent() {
		let move_background = document.getElementById("move_background");
		move_background.onclick = () => {
			this.#canvasView.getCanvas().getActiveObject().sendToBack();
			this.remove();
		}
		let move_back = document.getElementById("move_back");
		move_back.onclick = () => {
			this.#canvasView.getCanvas().getActiveObject().sendBackwards();
			this.remove();
		}
		let move_front = document.getElementById("move_front");
		move_front.onclick = () => {
			this.#canvasView.getCanvas().getActiveObject().bringForward();
			this.remove();
		}
		let move_foreground = document.getElementById("move_foreground");
		move_foreground.onclick = () => {
			this.#canvasView.getCanvas().getActiveObject().bringToFront();
			this.remove();
		}
	}

	initLoadImageEvent() {
		let change_image = document.getElementById("change_image");
		change_image.style.display = 'block';
		change_image.onclick = () => {
			this.remove();
			this.#mediaDialog.displayMediaSelector();
			this.#mediaDialog.initCancelEvent();
			this.#mediaDialog.initReplaceEvent(this.options.target);
		}
	}

	initDublicateEvent() {
		let duplicate_item = document.getElementById("duplicate_item");
		duplicate_item.onclick = () => {
			this.#canvasView.dublicateActiveObject();
			this.#canvasView.getCanvas()._historySaveAction()
			this.remove();
		}
	}

	initRemoveEvent() {
		let delete_item = document.getElementById("delete_item");
		delete_item.onclick = () => {
			this.#canvasView.removeActiveObject();
			this.#canvasView.getCanvas()._historySaveAction();
			this.remove();
		}
	}

	initLockEvent() {
		let lock_unlock = document.getElementById("lock_unlock");
		let lock = document.getElementById("lock");
		let unlock = document.getElementById("unlock");
		if (this.#canvasView.isCurrentLocked())
		{
			lock.style.display = "none";
			unlock.style.display = "inline";
		}
		else
		{
			lock.style.display = "inline";
			unlock.style.display = "none";
		}
		lock_unlock.onclick = () => {
			if (this.#canvasView.isCurrentLocked())
				this.#canvasView.setCurrentLockedStatus(false);
			else
				this.#canvasView.setCurrentLockedStatus(true);

			this.#canvasView.getCanvas()._historySaveAction()
			this.remove();
		}
	}
	remove() {
		if (this.context_menu !== undefined)
			this.context_menu.remove();
	}

}