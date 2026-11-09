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
 * The panel left of the canvas: the list of the objects and the fields of the selected one.
 */
export class ObjectPanelView
{
	static #ICONS = {
		text:     "bi-fonts",
		image:    "bi-image",
		group:    "bi-collection",
		circle:   "bi-circle",
		triangle: "bi-triangle",
		polygon:  "bi-pentagon",
		rect:     "bi-square"
	};

	#panel       = document.getElementById("objectPanel");
	#toggle      = document.getElementById("toggleObjectPanel");
	#list        = document.getElementById("objectList");
	#geometry    = document.getElementById("objectGeometry");
	#fields      = {
		left:   document.getElementById("objectLeft"),
		top:    document.getElementById("objectTop"),
		width:  document.getElementById("objectWidth"),
		height: document.getElementById("objectHeight"),
		angle:  document.getElementById("objectAngle")
	};

	get toggle()
	{
		return this.#toggle;
	}

	get list()
	{
		return this.#list;
	}

	/**
	 * @return {{left: HTMLInputElement, top: HTMLInputElement, width: HTMLInputElement, height: HTMLInputElement, angle: HTMLInputElement}}
	 */
	get fields()
	{
		return this.#fields;
	}

	toggleCollapsed()
	{
		this.#panel.classList.toggle("collapsed");
	}

	/**
	 * @return {number|null} the index on the canvas of the clicked entry
	 */
	getIndexFromEvent(event)
	{
		const entry = event.target.closest("li[data-index]");
		if (entry === null || entry.classList.contains("unselectable"))
			return null;

		return Number(entry.dataset.index);
	}

	/**
	 * @param {{index: number, shape: string, kind: string, label: string, isLocked: boolean, isSelectable: boolean, isActive: boolean}[]} entries
	 * @param {string} emptyText
	 */
	renderList(entries, emptyText)
	{
		const items = entries.map(entry => this.#createEntry(entry));
		if (items.length === 0)
		{
			const empty = document.createElement("li");
			empty.className   = "object-list-empty";
			empty.textContent = emptyText;
			items.push(empty);
		}
		this.#list.replaceChildren(...items);
	}

	/**
	 * @param {{left: number, top: number, width: number, height: number, angle: number}|null} geometry null empties
	 * the fields
	 * @param {{left: boolean, top: boolean, width: boolean, height: boolean, angle: boolean}} editable
	 */
	showGeometry(geometry, editable)
	{
		this.#geometry.disabled = geometry === null;
		for (const [name, field] of Object.entries(this.#fields))
		{
			field.value    = geometry === null ? "" : geometry[name];
			field.disabled = !editable[name];
		}
	}

	#createEntry(entry)
	{
		const item = document.createElement("li");
		item.dataset.index = String(entry.index);
		item.classList.toggle("active", entry.isActive);
		item.classList.toggle("unselectable", !entry.isSelectable);

		const icon = document.createElement("i");
		icon.className = "bi " + (ObjectPanelView.#ICONS[entry.kind] ?? ObjectPanelView.#ICONS[entry.shape] ?? "bi-square");
		const label = document.createElement("span");
		label.textContent = entry.label;
		label.title       = entry.label;
		item.append(icon, label);

		if (entry.isLocked)
		{
			const lock = document.createElement("i");
			lock.className = "bi bi-lock-fill";
			item.append(lock);
		}

		return item;
	}
}
