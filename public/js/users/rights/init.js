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
import {UserService}         from "../UserService.js";
import {UnitShareService}    from "../../core/UnitShareService.js";
import {FetchClient}         from "../../core/FetchClient.js";
import {FlashMessageHandler} from "../../core/FlashMessageHandler.js";
import {AutocompleteFactory} from "../../core/AutocompleteFactory.js";
import {Utils}               from "../../core/Utils.js";
import {PageTabs}            from "../../core/PageTabs.js";

/**
 * The rights page of a user: a tab per module with the roles and the units of the user.
 * A change is saved at once. A changed role reloads the page, because the role decides
 * which units and unit roles are shown.
 */
document.addEventListener("DOMContentLoaded", function()
{
	const page                = document.getElementById("userRights");
	const UID                 = page.dataset.uid;
	const userService         = new UserService(new FetchClient());
	const unitShareService    = new UnitShareService(new FetchClient());
	const flashMessageHandler = new FlashMessageHandler();
	const autocompleteFactory = new AutocompleteFactory();

	PageTabs.init(page);

	for (const tab of page.querySelectorAll(".tab-content"))
	{
		const module = tab.dataset.module;
		initRoles(tab, module);

		const list = tab.querySelector(".rights-unit-list");
		if (list === null)
			continue;

		for (const item of list.children)
			initUnitRole(tab, module, item);

		initAddUnit(tab, module, list);
	}

	function initRoles(tab, module)
	{
		for (const checkbox of tab.querySelectorAll(".rights-role"))
		{
			checkbox.addEventListener("change", async () =>
			{
				// removing editor or viewer removes the units of the role, too
				const units = tab.querySelectorAll(`.rights-unit-role option[value="${checkbox.value}"]:checked`);
				if (!checkbox.checked && units.length > 0 && !await Utils.confirmAction(page.dataset.confirmRemoveRole))
				{
					checkbox.checked = true;
					return;
				}

				const result = await userService.setRole(UID, module, checkbox.value, checkbox.checked);
				if (!result.success)
				{
					checkbox.checked = !checkbox.checked;
					flashMessageHandler.showError(result.error_message);
					return;
				}

				location.reload();
			});
		}
	}

	function initUnitRole(tab, module, item)
	{
		const select = item.querySelector(".rights-unit-role");
		let saved    = select.value;

		select.addEventListener("change", async () =>
		{
			select.disabled = true;
			const result = await unitShareService.save(module, item.dataset.unitId, UID, select.value);
			select.disabled = false;
			if (!result.success)
			{
				select.value = saved;
				flashMessageHandler.showError(result.error_message);
				return;
			}

			saved = select.value;
			if (saved === "")
			{
				item.remove();
				showNoUnits(tab);
			}
		});
	}

	function initAddUnit(tab, module, list)
	{
		const autocomplete = autocompleteFactory.create("unit_" + module, UnitShareService.findUrl(module));
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

			const result = await unitShareService.save(module, unitId, UID, select.value);
			if (!result.success)
			{
				item.remove();
				flashMessageHandler.showError(result.error_message);
				return;
			}

			initUnitRole(tab, module, item);
			showNoUnits(tab);
		});
	}

	function showNoUnits(tab)
	{
		tab.querySelector(".rights-no-units").hidden = tab.querySelector(".rights-unit-list").children.length > 0;
	}
});
