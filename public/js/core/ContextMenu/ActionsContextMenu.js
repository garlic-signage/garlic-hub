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
import {Utils} from "../Utils.js";

/**
 * The context menu of a row in a datatable: it asks the server what the user may do with the unit,
 * builds the entries, shows the menu and closes it with the next click.
 *
 * A module extends it with determineRights() and prepare(). Without a right an entry is removed.
 */
export class ActionsContextMenu
{
	// a second click before the rights of the first came back must not open a second menu
	static #current      = null;
	#view                = null;
	#flashMessageHandler = null;
	#controller          = new AbortController();
	#unitId              = 0;

	constructor(view, flashMessageHandler)
	{
		this.#view                = view;
		this.#flashMessageHandler = flashMessageHandler;
	}

	/**
	 * One context menu per click on the buttons with the class, e.g. "player-contextmenu".
	 */
	static bindTo(className, createMenu)
	{
		for (const button of document.getElementsByClassName(className))
		{
			button.addEventListener("click", async (event) =>
			{
				event.preventDefault();
				await createMenu().init(event);
			});
		}
	}

	get view()
	{
		return this.#view;
	}

	get flashMessageHandler()
	{
		return this.#flashMessageHandler;
	}

	get unitId()
	{
		return this.#unitId;
	}

	/**
	 * The listeners of the entries end with the menu.
	 */
	get signal()
	{
		return this.#controller.signal;
	}

	async init(event)
	{
		this.#unitId = Number(event.target.dataset.actionId);
		const rights = await this.determineRights(this.#unitId);
		if (rights.success === false)
		{
			this.#flashMessageHandler.showError(rights.error_message);
			return;
		}
		if (!this.isShown(rights))
			return;

		this.prepare(rights);

		ActionsContextMenu.#current?.close();
		ActionsContextMenu.#current = this;
		document.body.appendChild(this.#view.menu);
		this.#view.placeMenu(event.clientX, event.clientY);

		document.addEventListener("click", () => this.close(), {once: true});
	}

	close()
	{
		this.#controller.abort();
		this.#view.menu.remove();
		if (ActionsContextMenu.#current === this)
			ActionsContextMenu.#current = null;
	}

	/**
	 * @return {Promise<Object>} the answer of the acls endpoint of the module
	 */
	async determineRights(unitId)
	{
		throw new Error("determineRights() is not implemented for unit " + unitId);
	}

	isShown(rights)
	{
		return rights.can_edit === true;
	}

	/**
	 * Builds the entries of the module.
	 */
	prepare(rights)
	{
		throw new Error("prepare() is not implemented");
	}

	/**
	 * The delete entry (class "delete"): asks, deletes and removes the row of the unit.
	 *
	 * @param {boolean} canDelete
	 * @param {function(number): Promise<{success: boolean, error_message?: string}>} deleteUnit
	 * @param {string|null} confirmText the text of data-confirm when null
	 */
	addDelete(canDelete, deleteUnit, confirmText = null)
	{
		const item = this.#view.item("delete");
		if (!canDelete)
		{
			item.remove();
			return;
		}

		item.addEventListener("click", async (event) =>
		{
			event.preventDefault();
			if (!await Utils.confirmAction(confirmText ?? item.dataset.confirm))
				return;

			const result = await deleteUnit(this.#unitId);
			if (result.success)
				document.querySelector(`ul[data-id="${this.#unitId}"]`)?.closest("li")?.remove();
			else
				this.#flashMessageHandler.showError(result.error_message);
		}, {signal: this.#controller.signal});
	}
}
