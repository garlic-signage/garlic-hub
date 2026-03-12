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

export class ToggleButton
{
	constructor(domElement)
	{
		this.el = domElement
	}

	update(type, value)
	{
		const children = this.el.children;
		if (type === 'display')
		{
			for (let i = 0; i < children.length; i++)
			{
				children[i].style.display = children[i].getAttribute("id") === value ? "block" : "none";
			}
		}
		if (type === "active")
		{
			this.el.style.fill = this.el.getAttribute("id") === value ? "#269CC0" : "#000";
		}
	}

	getElement()
	{
		return this.el
	}

	show(value)
	{
		this.el.style.display = "block";
	}

	hide(value)
	{
		this.el.style.display =  "none";
	}

}