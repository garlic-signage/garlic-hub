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

import {BaseTypes} from "./BaseTypes.js";

export class TouchEdit extends BaseTypes
{
	#valueField = null;
	#touchableMedialist =null;
	
	set touchableMedialist(value)
	{
		this.#touchableMedialist = value;
	}

	init(data)
	{
		this.cloneNode("touchTemplate")
		this.addRemoveListener();

		this.#valueField = this.node.querySelector(".edit-touch-item");
		this.node.querySelector(".remove").addEventListener("click", function(event)
		{
			event.target.closest('ul').remove();
		});

		for (const item of this.#touchableMedialist)
		{
			const option = document.createElement('option');
			option.value = item.item_id;
			option.textContent = item.item_name;
			this.#valueField.appendChild(option);
		}

		if (data.hasOwnProperty("touch_item_id"))
			this.#valueField.value = data.touch_item_id;

	}

	getValues()
	{
		let obj = {}
		obj.touch_item_id  = this.#valueField.value;

		return obj;
	}

}
