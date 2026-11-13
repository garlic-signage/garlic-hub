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
'use strict';

export class BaseTypes
{
	exists = false;
	node = null;
	#id = 0;

	set id(value)
	{
		this.#id = value;
	}

	get id()
	{
		return this.#id;
	}

	get exists()
	// Stryker disable next-line BlockStatement: the class field exists hides this getter
	{
		return this.exists;
	}

	cloneNode(elementName)
	{
		const template = document.getElementById(elementName);
		this.node = template.content.cloneNode(true);
	}

	addRemoveListener()
	{
		this.exists = true;
		this.node.querySelector(".remove").addEventListener("click", (event) => {
			this.exists = false;
			event.target.closest('.trigger-form').remove();
		});
	}

	getEditor()
	{
		return this.node;
	}

}
