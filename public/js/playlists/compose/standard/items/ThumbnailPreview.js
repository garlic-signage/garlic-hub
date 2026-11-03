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
 * Shows the thumbnail of a playlist item uncropped in its original size
 * while hovering it or while a finger rests on it.
 * One shared preview element, events are delegated to the playlist.
 */
export class ThumbnailPreview
{
	static #GAP = 8;

	#playlist = null;
	#preview  = null;
	#isTouch  = false;

	constructor(playlist)
	{
		this.#playlist = playlist;
	}

	init()
	{
		this.#preview = document.createElement("img");
		this.#preview.classList.add("thumbnail-preview");
		this.#preview.alt = "";
		document.body.appendChild(this.#preview);

		this.#playlist.addEventListener("mouseover", (event) => {
			if (!this.#isTouch)
				this.#show(event.target);
		});
		this.#playlist.addEventListener("mouseout", (event) => {
			if (!this.#isTouch && this.#isThumbnail(event.target))
				this.#hide();
		});

		this.#playlist.addEventListener("touchstart", (event) => {
			this.#isTouch = true;
			this.#show(event.target);
		}, {passive: true});
		this.#playlist.addEventListener("touchmove", () => this.#hide(), {passive: true}); // dragging or scrolling begins
		this.#playlist.addEventListener("touchend", () => this.#endTouch());
		this.#playlist.addEventListener("touchcancel", () => this.#endTouch());

		// long press would open the browser's image menu
		this.#playlist.addEventListener("contextmenu", (event) => {
			if (this.#isTouch && this.#isThumbnail(event.target))
				event.preventDefault();
		});

		this.#playlist.addEventListener("mousedown", () => this.#hide()); // dragula starts dragging
		this.#playlist.addEventListener("scroll", () => this.#hide());
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
		return target instanceof HTMLImageElement && target.classList.contains("thumbnail");
	}
}
