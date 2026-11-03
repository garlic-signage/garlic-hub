/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
 This file is part of the garlic-hub source code

 This program is free software: you can redistribute it and/or  modify
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

/**
 * The preview player (public/js/player/preview, the WASM player of garlic-player) in an overlay. It plays as the
 * chosen player, so it gets its playlist from the SMIL index like the device; the cache stays in memory.
 *
 * The player sits in an iframe: the CSP of the CMS pages allows no WebAssembly, and its keys and styles stay apart.
 * The frame has the proportions of the layout (the player sends them when it has read the index); its width is
 * dragged at the lower right corner. No full screen: it is a preview, not a player.
 */
export class PreviewOverlay
{
	#dialog = document.getElementById("playerPreviewDialog");
	#frame  = this.#dialog.querySelector(".preview-frame");
	#title  = this.#dialog.querySelector(".dialog-name");
	#iframe = null;
	#cover  = document.createElement("div");

	constructor()
	{
		this.#cover.className = "drag-cover";
		this.#dialog.querySelector(".close-button").addEventListener("click", () => this.#dialog.close());
		// a new iframe for every preview: closing ends the player and frees its memory
		this.#dialog.addEventListener("close", () => {
			this.#iframe?.remove();
			this.#iframe = null;
		});

		// The resize corner belongs to the frame, the iframe would take the mouse while dragging over it: a cover
		// lies over it until the button is released.
		this.#frame.addEventListener("pointerdown", (event) => {
			if (event.target !== this.#frame)
				return;
			this.#frame.append(this.#cover);
			window.addEventListener("pointerup", () => this.#cover.remove(), { once: true });
		});

		window.addEventListener("message", (event) => this.#layoutOfPlayer(event));
	}

	open(uuid, playerName)
	{
		const params = new URLSearchParams({
			index: location.origin + "/smil-index",
			uuid: uuid,
			name: playerName,
			storage: "memory"
		});

		this.#title.textContent = playerName;
		this.#frame.style.aspectRatio = "16 / 9"; // until the player knows the layout
		this.#iframe = document.createElement("iframe");
		this.#iframe.allow = "autoplay"; // the click on the menu allows sound in the preview
		this.#iframe.src = "/js/player/preview/index.html?" + params.toString();
		this.#frame.append(this.#iframe);
		this.#dialog.showModal();
	}

	/**
	 * The player sends the size of its root-layout, e.g. 1920x1080.
	 */
	#layoutOfPlayer(event)
	{
		if (event.origin !== location.origin || this.#iframe === null || event.source !== this.#iframe.contentWindow)
			return;
		if (event.data?.type !== "garlic-preview-layout")
			return;

		const width  = Number(event.data.width);
		const height = Number(event.data.height);
		if (width > 0 && height > 0)
			this.#frame.style.aspectRatio = width + " / " + height;
	}
}
