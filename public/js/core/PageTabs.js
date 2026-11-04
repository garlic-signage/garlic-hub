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
 * The tabs of a page (class page-tabs): buttons .tab-button with data-tab, sections .tab-content with
 * data-module. The open tab is in the url (#module), so it stays after a reload or a redirect.
 */
export class PageTabs
{
	static init(container)
	{
		const buttons = container.querySelectorAll(".tab-button");
		const show    = (module) =>
		{
			for (const button of buttons)
				button.classList.toggle("active", button.dataset.tab === module);
			for (const tab of container.querySelectorAll(".tab-content"))
				tab.classList.toggle("active", tab.dataset.module === module);
		};

		for (const button of buttons)
		{
			button.addEventListener("click", () =>
			{
				show(button.dataset.tab);
				history.replaceState(null, "", "#" + button.dataset.tab);
			});
		}

		const fromHash = decodeURIComponent(location.hash.substring(1));
		const exists   = [...buttons].some(button => button.dataset.tab === fromHash);
		show(exists ? fromHash : buttons[0]?.dataset.tab);
	}
}
