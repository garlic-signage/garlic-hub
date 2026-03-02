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

export class CanvasDialog
{
	#mediaSelector = {};
	MySvgItemsParser = {};

	#mediaSelectorElement;
	#closeEditMediaDialog;
	#closeDialogButton;
	#mediaSelectorDialog;
	#isOpen = false;

	constructor(MyMediaSelector, MySvgItemsParser)
	{
		this.#mediaSelector = MyMediaSelector;
		this.MySvgItemsParser = MySvgItemsParser;
		this.#mediaSelectorElement = document.getElementById("mediaSelectorInstance");
		this.#closeEditMediaDialog = document.getElementById("closeEditMediaDialog");
		this.#closeDialogButton = document.getElementById("closeDialogButton");
		this.#mediaSelectorDialog = document.getElementById("mediaSelectorDialog");
	}

	remove()
	{
		this.#mediaSelectorDialog.close();

		this.#isOpen = false;
	}

	get isOpen()
	{
		return this.#isOpen;
	}

	async displayMediaSelector()
	{
		await this.#mediaSelector.showSelector(this.#mediaSelectorElement);
		this.#mediaSelectorDialog.showModal();
/*
		this.edit_dialog = document.createElement("div");
		this.edit_dialog.className = 'dialog_overlay_wrapper';

		this.edit_dialog.innerHTML = template_editor;
*/
		this.#isOpen = true;
	}

	initCancelEvent()
	{
		this.#closeEditMediaDialog.onclick = () =>
		{
			this.remove();
		}
		this.#closeDialogButton.onclick = () =>
		{
			this.remove();
		}
	}

	initInsertEvent(MyCanvasView)
	{
/*		let edit_insert = document.getElementById("element_edit_insert");
		edit_insert.style.display = "inline";
		edit_insert.onclick = () =>
		{
			let link = this.MyMediaSelector.getSelectedMediaLink().replace("preview", "original");
			fabric.Image.fromURL(link, (img) =>
			{
				let scale = this.MySvgItemsParser.calculateImageScaleByCanvasInPerCent(img.width, img.height);
				img.scale(scale/100); // 1 is 100%
				MyCanvasView.getCanvas().add(img);
				MyCanvasView.getCanvas().renderAll();
				this.MyMediaSelector.destroyTreeView();
				edit_insert.style.display = "none";
				this.remove();
			},{crossOrigin: 'anonymous'});
		}
*/
	}

	initTransferEvent(target, MyCanvasView)
	{
		let edit_transfer = document.getElementById("element_edit_transfer");
		edit_transfer.style.display = "inline";
		edit_transfer.onclick = () =>
		{
			// must before set src cause then we have a new target
			let w = target.width  * target.scaleX;
			let h = target.height * target.scaleY;

			let link = this.#mediaSelector.getSelectedMediaLink().replace("preview", "original");
			target.setSrc(link, () =>
			{
				target.scaleX = 1;
				target.scaleY = 1;
				// do not know why both must be set
				target.scaleToWidth(w, true);
				target.scaleToHeight(h, true);
				MyCanvasView.getCanvas().renderAll();
				this.#mediaSelector.destroyTreeView();
				edit_transfer.style.display = "none";
				this.edit_dialog.remove();
			},{crossOrigin: 'anonymous'});
		}
	}
}