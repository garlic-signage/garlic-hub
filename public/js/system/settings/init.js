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
import {PageTabs} from "../../core/PageTabs.js";

/**
 * The settings page: every tab is a form which is sent to the server. A setting which depends on a switch
 * is greyed out while the switch is off.
 */
document.addEventListener("DOMContentLoaded", function()
{
	const page = document.getElementById("systemSettings");
	PageTabs.init(page);

	for (const setting of page.querySelectorAll("[data-depends-on]:not([data-depends-on=''])"))
	{
		const toggle = document.getElementById(setting.dataset.dependsOn);
		if (toggle === null)
			continue;

		toggle.addEventListener("change", () => setting.disabled = !toggle.checked);
	}
});
