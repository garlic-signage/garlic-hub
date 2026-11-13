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
export class AdjustMenus
{
	static adjustDropdownPosition(dropdownElement)
	{
		const rect          = dropdownElement.getBoundingClientRect();
		const viewportWidth = window.innerWidth || document.documentElement.clientWidth;
		const viewportHeight = window.innerHeight || document.documentElement.clientHeight;

		if (rect.right > viewportWidth)
		{
			dropdownElement.style.left = "auto"; 
			dropdownElement.style.right = "0";   
		}
		else if (rect.left < 0)
		{
			dropdownElement.style.left = "0";
			dropdownElement.style.right = "auto";
		}

		// Stryker disable next-line EqualityOperator: exactly at the edge the overlap is 0, the top stays
		if (rect.bottom > viewportHeight)
		{
			const overlap = rect.bottom - viewportHeight;
			const newTop = parseFloat(dropdownElement.style.top) - overlap
			dropdownElement.style.top = newTop + "px";

		}
	}

}
