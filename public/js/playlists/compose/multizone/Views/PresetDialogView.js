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

/**
 * The dialog with the presets as tiles. The preview of a tile is drawn as SVG from the percent of the zones
 * in the aspect ratio of the current resolution, so a portrait screen shows its real result.
 */
export class PresetDialogView
{
	static #SVG_NS       = "http://www.w3.org/2000/svg";
	static #PREVIEW_SIZE = 120;

	#openButton = document.getElementById("open_presets");
	#dialog     = null;

	get openButton() { return this.#openButton; }

	open(presets, width, height, labels, onSelect)
	{
		this.#dialog?.remove();
		this.#dialog = this.#build(presets, width, height, labels, onSelect);
		document.body.appendChild(this.#dialog);
		this.#dialog.showModal();
	}

	close()
	{
		this.#dialog?.close();
	}

	#build(presets, width, height, labels, onSelect)
	{
		const dialog = document.createElement("dialog");
		dialog.className = "multizone-presets";
		dialog.addEventListener("close", () => dialog.remove());

		const header = document.createElement("header");
		const title  = document.createElement("span");
		title.textContent = labels.title;
		const close = document.createElement("button");
		close.type      = "button";
		close.className = "close-button bi bi-x";
		close.setAttribute("aria-label", labels.close);
		close.addEventListener("click", () => dialog.close());
		header.append(title, close);
		dialog.appendChild(header);

		const list = document.createElement("ul");
		list.className = "multizone-presets-list";
		for (const preset of presets)
		{
			const item   = document.createElement("li");
			const button = document.createElement("button");
			button.type  = "button";
			button.title = preset.name;
			const name = document.createElement("span");
			name.textContent = preset.name;
			button.append(this.#buildPreview(preset, width, height), name);
			button.addEventListener("click", () => onSelect(preset));
			item.appendChild(button);
			list.appendChild(item);
		}
		dialog.appendChild(list);

		return dialog;
	}

	#buildPreview(preset, width, height)
	{
		// the longer side of the screen gets the full size of the preview
		const ratio   = width > 0 && height > 0 ? width / height : 16 / 9;
		const svgWidth  = ratio >= 1 ? PresetDialogView.#PREVIEW_SIZE : Math.round(PresetDialogView.#PREVIEW_SIZE * ratio);
		const svgHeight = ratio >= 1 ? Math.round(PresetDialogView.#PREVIEW_SIZE / ratio) : PresetDialogView.#PREVIEW_SIZE;

		const svg = document.createElementNS(PresetDialogView.#SVG_NS, "svg");
		svg.setAttribute("width", svgWidth);
		svg.setAttribute("height", svgHeight);
		svg.setAttribute("viewBox", "0 0 100 100");
		svg.setAttribute("preserveAspectRatio", "none");
		svg.setAttribute("aria-hidden", "true");

		for (const zone of preset.zones)
		{
			const rect = document.createElementNS(PresetDialogView.#SVG_NS, "rect");
			rect.setAttribute("x", zone.left);
			rect.setAttribute("y", zone.top);
			rect.setAttribute("width", zone.width);
			rect.setAttribute("height", zone.height);
			rect.setAttribute("fill", zone.bgcolor);
			// the stroke keeps its width, the viewBox is stretched to the ratio
			rect.setAttribute("vector-effect", "non-scaling-stroke");
			svg.appendChild(rect);
		}

		return svg;
	}
}
