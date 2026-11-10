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
"use strict";
import {UnitShareService} from "../../core/UnitShareService.js";
import {Utils}            from "../../core/Utils.js";

/**
 * The rights page of a user: a tab per module with the roles and the units of the user.
 * A change is saved at once. A changed role reloads the page, because the role decides
 * which units and unit roles are shown.
 */
export class UserRightsPage
{
	#page;
	#UID;
	#userService;
	#unitShareService;
	#flashMessageHandler;
	#autocompleteFactory;

	constructor(page, userService, unitShareService, flashMessageHandler, autocompleteFactory)
	{
		this.#page                = page;
		this.#UID                 = page.dataset.uid;
		this.#userService         = userService;
		this.#unitShareService    = unitShareService;
		this.#flashMessageHandler = flashMessageHandler;
		this.#autocompleteFactory = autocompleteFactory;
	}

	init()
	{
		for (const tab of this.#page.querySelectorAll(".tab-content"))
		{
			const module = tab.dataset.module;
			this.#initRoles(tab, module);

			const list = tab.querySelector(".rights-unit-list");
			if (list === null)
				continue;

			for (const item of list.children)
				this.#initUnitRole(tab, module, item);

			this.#initAddUnit(tab, module, list);
		}
	}

	#initRoles(tab, module)
	{
		for (const checkbox of tab.querySelectorAll(".rights-role"))
		{
			checkbox.addEventListener("change", async () =>
			{
				// removing editor or viewer removes the units of the role, too
				const units = tab.querySelectorAll(`.rights-unit-role option[value="${checkbox.value}"]:checked`);
				if (!checkbox.checked && units.length > 0 && !await Utils.confirmAction(this.#page.dataset.confirmRemoveRole))
				{
					checkbox.checked = true;
					return;
				}

				const result = await this.#userService.setRole(this.#UID, module, checkbox.value, checkbox.checked);
				if (!result.success)
				{
					checkbox.checked = !checkbox.checked;
					this.#flashMessageHandler.showError(result.error_message);
					return;
				}

				location.reload();
			});
		}
	}

	#initUnitRole(tab, module, item)
	{
		const select = item.querySelector(".rights-unit-role");
		let saved    = select.value;

		select.addEventListener("change", async () =>
		{
			select.disabled = true;
			const result = await this.#unitShareService.save(module, item.dataset.unitId, this.#UID, select.value);
			select.disabled = false;
			if (!result.success)
			{
				select.value = saved;
				this.#flashMessageHandler.showError(result.error_message);
				return;
			}

			saved = select.value;
			if (saved === "")
			{
				item.remove();
				this.#showNoUnits(tab);
			}
		});
	}

	#initAddUnit(tab, module, list)
	{
		const autocomplete = this.#autocompleteFactory.create("unit_" + module, UnitShareService.findUrl(module));
		autocomplete.initWithExistingFields();

		autocomplete.getHiddenIdElement().addEventListener("selectedFromDataList", async (event) =>
		{
			const unitId = event.target.value;
			const name   = autocomplete.getEditFieldElement().value;
			autocomplete.setInputFields("", "");

			if (list.querySelector(`li[data-unit-id="${CSS.escape(unitId)}"]`) !== null)
				return;

			const item   = tab.querySelector(".rights-unit-template").content.firstElementChild.cloneNode(true);
			const select = item.querySelector(".rights-unit-role");
			// the first role the user has, viewer before editor
			select.selectedIndex = 1;

			// in the list before the request: a second select of the unit while it runs finds it
			item.dataset.unitId = unitId;
			item.querySelector(".rights-unit-name").textContent = name;
			list.prepend(item);

			const result = await this.#unitShareService.save(module, unitId, this.#UID, select.value);
			if (!result.success)
			{
				item.remove();
				this.#flashMessageHandler.showError(result.error_message);
				return;
			}

			this.#initUnitRole(tab, module, item);
			this.#showNoUnits(tab);
		});
	}

	#showNoUnits(tab)
	{
		tab.querySelector(".rights-no-units").hidden = tab.querySelector(".rights-unit-list").children.length > 0;
	}
}
