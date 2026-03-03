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
	#addMedia;
	#applyMedia;
	#dialogName;

	constructor(mediaSelector, MySvgItemsParser)
	{
		this.#mediaSelector = mediaSelector;
		this.MySvgItemsParser = MySvgItemsParser;
		this.#mediaSelectorElement = document.getElementById("mediaSelectorInstance");
		this.#closeEditMediaDialog = document.getElementById("closeEditMediaDialog");
		this.#closeDialogButton = document.getElementById("closeDialogButton");
		this.#mediaSelectorDialog = document.getElementById("mediaSelectorDialog");
		this.#addMedia = document.getElementById("addMedia");
		this.#applyMedia = document.getElementById("applyMedia");
		this.#dialogName = document.getElementsByClassName("dialog-name")[0];
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
		this.#addMedia.style.display = "inline";
		this.#applyMedia.style.display = "none";
		this.#dialogName.innerText = lang.add_image;
		this.#mediaSelector.enableMultiSelect();

		this.#addMedia.addEventListener("click", (event) =>
		{
			let selectedMediaList = this.#mediaSelector.getSelectedMedia();
			selectedMediaList.forEach(({ id, src }, i) => {
				fabric.Image.fromURL(src.replace("thumbs", "originals"), (img) =>
				{
					let scale = this.MySvgItemsParser.calculateImageScaleByCanvasInPerCent(img.width, img.height);
					img.scale(scale/150);
					img.set({
						mediaId: id,
						left: i * 50,
						top:  i * 50
					});
					MyCanvasView.getCanvas().add(img);
					img.bringToFront();
					MyCanvasView.getCanvas().renderAll();
				},{crossOrigin: 'anonymous'});
			});
			this.remove();
		}, { once: true });
	}

	initReplaceEvent(target, MyCanvasView)
	{
		this.#applyMedia.style.display = "inline";
		this.#addMedia.style.display = "none";
		this.#mediaSelector.disableMultiSelect();
		this.#dialogName.innerText = lang.replace_image;

		this.#applyMedia.addEventListener("click", (event) =>
		{
			// must before set src cause then we have a new target
			let w = target.width  * target.scaleX;
			let h = target.height * target.scaleY;

			let selectedMedia = this.#mediaSelector.getSelectedMedia()[0];
			let link = this.#mediaSelector.getSelectedMedia();
			target.setSrc(selectedMedia.src.replace("thumbs", "originals"), () =>
			{
				target.scaleX = 1;
				target.scaleY = 1;
				target.set({ mediaId: selectedMedia.id });
				// do not know why both must be set
				target.scaleToWidth(w, true);
				target.scaleToHeight(h, true);
				MyCanvasView.getCanvas().renderAll();
				this.remove();
			},{crossOrigin: 'anonymous'});
		}, { once: true });
	}
}