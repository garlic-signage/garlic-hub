/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2025 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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
import {AdjustMenus} from "./core/AdjustMenus.js";

document.addEventListener("DOMContentLoaded", function()
{
	document.getElementById("language_select").addEventListener("mouseover", function()
	{
		const dropdownContent = document.querySelector("#language_select ul");

		AdjustMenus.adjustDropdownPosition(dropdownContent);
	});

});

// the close button of the messages of the server and of the template for new ones
document.addEventListener("click", function(event)
{
	const button = event.target.closest(".message-bar .close-button");
	if (button !== null)
		button.parentElement.style.display = "none";
});
