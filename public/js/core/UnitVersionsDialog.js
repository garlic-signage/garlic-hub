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
import {UnitVersionsService} from "./UnitVersionsService.js";
import {FetchClient}         from "./FetchClient.js";
import {FlashMessageHandler} from "./FlashMessageHandler.js";

/**
 * The versions dialog of a unit, the same for every module with versions. It is opened from the
 * context menu of a unit, the entry "versions" is shown when the acls endpoint answers can_version.
 * A restore asks first, then the browser goes where the server says (e.g. the composer).
 */
export class UnitVersionsDialog
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
		return new UnitVersionsDialog(module, new UnitVersionsService(new FetchClient()), new FlashMessageHandler());
	}

	/**
	 * The entry of a context menu: removed while the versioning is off, otherwise it opens the dialog.
	 */
	attachTo(menuItem, unitId, canVersion)
	{
		if (!canVersion)
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
		dialog.className = "unit-versions";
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

		if (data.versions.length === 0)
		{
			const empty = document.createElement("p");
			empty.className   = "unit-versions-empty";
			empty.textContent = data.labels.empty;
			dialog.appendChild(empty);
			return dialog;
		}

		const list = document.createElement("ul");
		list.className = "unit-versions-list";
		for (const version of data.versions)
		{
			list.appendChild(this.#buildVersion(version, data.labels));
		}
		dialog.appendChild(list);

		return dialog;
	}

	#buildVersion(version, labels)
	{
		const item = document.createElement("li");

		const text = document.createElement("span");
		text.textContent = version.version + " · " + version.created_at + " · " + version.username;

		const restore = document.createElement("button");
		restore.type        = "button";
		restore.textContent = labels.restore;
		restore.addEventListener("click", async () =>
		{
			if (!confirm(labels.restore_confirm))
				return;

			restore.disabled = true;
			const result = await this.#service.restore(this.#module, this.#unitId, version.version);
			if (result.success)
			{
				window.location.href = result.redirect;
				return;
			}

			restore.disabled = false;
			this.#flashMessageHandler.showError(result.error_message);
		});

		item.append(text, restore);
		return item;
	}
}
