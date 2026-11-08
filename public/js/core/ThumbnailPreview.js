/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2025 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

/**
 * Shows a thumbnail uncropped in its original size while hovering it or while a finger rests on it:
 * the items of the playlist and the media and templates of the selectors.
 * One shared preview element per container, events are delegated to the container.
 * Inside a modal dialog the preview belongs to the dialog: on the body it would lie below the dialog.
 */
export class ThumbnailPreview
{
	static #GAP = 8;

	#container      = null;
	#thumbnailClass = "";
	#preview        = null;
	#isTouch        = false;

	/**
	 * @param {HTMLElement} container      the thumbnails are inside, now or later
	 * @param {string}      thumbnailClass the class of the images which are previewed
	 */
	constructor(container, thumbnailClass = "thumbnail")
	{
		this.#container      = container;
		this.#thumbnailClass = thumbnailClass;
	}

	init()
	{
		this.#preview = document.createElement("img");
		this.#preview.classList.add("thumbnail-preview");
		this.#preview.alt = "";
		(this.#container.closest("dialog") ?? document.body).appendChild(this.#preview);

		this.#container.addEventListener("mouseover", (event) => {
			if (!this.#isTouch)
				this.#show(event.target);
		});
		this.#container.addEventListener("mouseout", (event) => {
			if (!this.#isTouch && this.#isThumbnail(event.target))
				this.#hide();
		});

		this.#container.addEventListener("touchstart", (event) => {
			this.#isTouch = true;
			this.#show(event.target);
		}, {passive: true});
		this.#container.addEventListener("touchmove", () => this.#hide(), {passive: true}); // dragging or scrolling begins
		this.#container.addEventListener("touchend", () => this.#endTouch());
		this.#container.addEventListener("touchcancel", () => this.#endTouch());

		// long press would open the browser's image menu
		this.#container.addEventListener("contextmenu", (event) => {
			if (this.#isTouch && this.#isThumbnail(event.target))
				event.preventDefault();
		});

		this.#container.addEventListener("mousedown", () => this.#hide()); // dragula starts dragging
		// capture: a list inside the container scrolls, too (scroll does not bubble)
		this.#container.addEventListener("scroll", () => this.#hide(), true);
	}

	#show(target)
	{
		if (!this.#isThumbnail(target) || target.getAttribute("src") === "")
			return;

		if (document.body.classList.contains("gu-unselectable")) // a drag is in progress
			return;

		this.#preview.src = target.src;
		this.#preview.classList.add("visible");
		this.#position(target);
	}

	#position(target)
	{
		const rect    = target.getBoundingClientRect();
		const width   = this.#preview.offsetWidth;
		const height  = this.#preview.offsetHeight;
		const gap     = ThumbnailPreview.#GAP;

		let left = rect.right + gap;
		if (left + width > window.innerWidth)
			left = Math.max(gap, rect.left - width - gap);

		let top = rect.top + (rect.height - height) / 2;
		top = Math.min(Math.max(gap, top), window.innerHeight - height - gap);

		this.#preview.style.left = left + "px";
		this.#preview.style.top  = top + "px";
	}

	#endTouch()
	{
		this.#hide();
		// the browser emulates mouse events after touchend, so reset the flag afterward
		setTimeout(() => { this.#isTouch = false; }, 500);
	}

	#hide()
	{
		this.#preview.classList.remove("visible");
	}

	#isThumbnail(target)
	{
		return target instanceof HTMLImageElement && target.classList.contains(this.#thumbnailClass);
	}
}
