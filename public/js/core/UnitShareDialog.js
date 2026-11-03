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
import {UnitShareService}    from "./UnitShareService.js";
import {FetchClient}         from "./FetchClient.js";
import {FlashMessageHandler} from "./FlashMessageHandler.js";

/**
 * The share dialog of a unit, the same for every module. It is opened from the context menu
 * of a unit, the entry "share" is shown when /async/<module>/acls answers can_share.
 *
 * Every user with the role editor or viewer in the module gets a select: none, viewer or editor,
 * limited to the roles he has. A change is saved at once. An editor may use the unit, too.
 */
export class UnitShareDialog
{
	#module              = "";
	#unitId              = "";
	#service             = null;
	#flashMessageHandler = null;
	#dialog              = null;

	constructor(module, service, flashMessageHandler)
	{
		this.#module              = module;
		this.#service             = service;
		this.#flashMessageHandler = flashMessageHandler;
	}

	static create(module)
	{
		return new UnitShareDialog(module, new UnitShareService(new FetchClient()), new FlashMessageHandler());
	}

	/**
	 * The entry of a context menu: removed without the right, otherwise it opens the dialog.
	 */
	attachTo(menuItem, unitId, canShare)
	{
		if (!canShare)
		{
			menuItem.remove();
			return;
		}

		menuItem.addEventListener("click", () => this.open(unitId));
	}

	async open(unitId)
	{
		this.#unitId = unitId;
		const result = await this.#service.load(this.#module, unitId);
		if (!result.success)
		{
			this.#flashMessageHandler.showError(result.error_message);
			return;
		}

		this.#dialog?.remove();
		this.#dialog = this.#build(result);
		document.body.appendChild(this.#dialog);
		this.#dialog.showModal();
	}

	#build(data)
	{
		const dialog = document.createElement("dialog");
		dialog.className = "unit-share";
		dialog.addEventListener("close", () => dialog.remove());

		const header = document.createElement("header");
		const title  = document.createElement("span");
		title.textContent = data.labels.title;
		const close = document.createElement("button");
		close.type      = "button";
		close.className = "close-button bi bi-x";
		close.setAttribute("aria-label", data.labels.close);
		close.addEventListener("click", () => dialog.close());
		header.append(title, close);
		dialog.appendChild(header);

		if (data.users.length === 0)
		{
			const empty = document.createElement("p");
			empty.className   = "unit-share-empty";
			empty.textContent = data.labels.no_users;
			dialog.appendChild(empty);
			return dialog;
		}

		const filter = document.createElement("input");
		filter.type        = "search";
		filter.className   = "unit-share-filter";
		filter.placeholder = data.labels.filter;
		dialog.appendChild(filter);

		const list = document.createElement("ul");
		list.className = "unit-share-users";
		for (const user of data.users)
		{
			list.appendChild(this.#buildUser(user, data.labels));
		}
		dialog.appendChild(list);

		filter.addEventListener("input", () =>
		{
			const search = filter.value.trim().toLowerCase();
			for (const item of list.children)
			{
				item.hidden = search !== "" && !item.dataset.username.includes(search);
			}
		});

		return dialog;
	}

	#buildUser(user, labels)
	{
		const item = document.createElement("li");
		item.dataset.username = user.username.toLowerCase();

		const name = document.createElement("label");
		name.textContent = user.username;
		const select = document.createElement("select");
		select.id      = "unit-share-" + user.UID;
		name.htmlFor   = select.id;

		select.appendChild(new Option(labels.none, ""));
		for (const role of ["viewer", "editor"])
		{
			if (user.roles.includes(role))
				select.appendChild(new Option(labels[role], role));
		}
		select.value = user.role;

		let saved = user.role;
		select.addEventListener("change", async () =>
		{
			select.disabled = true;
			const result = await this.#service.save(this.#module, this.#unitId, user.UID, select.value);
			select.disabled = false;
			if (result.success)
			{
				saved = select.value;
				return;
			}

			select.value = saved;
			this.#flashMessageHandler.showError(result.error_message);
		});

		item.append(name, select);
		return item;
	}
}
