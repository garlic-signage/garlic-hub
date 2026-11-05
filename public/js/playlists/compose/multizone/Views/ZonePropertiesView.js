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

export class ZonePropertiesView
{
	#name        = document.getElementById("zone_name");
	#left        = document.getElementById("zone_left");
	#top         = document.getElementById("zone_top");
	#width       = document.getElementById("zone_width");
	#height      = document.getElementById("zone_height");
	#bgcolor     = document.getElementById("zone_bgcolor");
	#properties  = document.getElementById("zone_properties");
	#transparent = document.getElementById("zone_transparent");

	get name()        { return this.#name; }
	get left()        { return this.#left; }
	get top()         { return this.#top; }
	get width()       { return this.#width; }
	get height()      { return this.#height; }
	get bgcolor()     { return this.#bgcolor; }
	get transparent() { return this.#transparent; }

	show(name, left, top, width, height)
	{
		this.#name.value   = name;
		this.#left.value   = left;
		this.#top.value    = top;
		this.#width.value  = width;
		this.#height.value = height;
	}

	clear()
	{
		this.show("", "", "", "", "");
		this.#bgcolor.value = "";
	}

	enable()
	{
		this.#properties.disabled = false;
	}

	disable()
	{
		this.#properties.disabled = true;
	}

	showTransparent()
	{
		this.#transparent.checked = true;
		this.#bgcolor.disabled    = true;
	}

	/**
	 * The HTML5 color picker does not know transparency, so it gets the color only without.
	 */
	showColor(color)
	{
		this.#transparent.checked = false;
		this.#bgcolor.disabled    = false;
		this.#bgcolor.value       = color;
	}

	enableColor(isEnabled)
	{
		this.#bgcolor.disabled = !isEnabled;
	}
}
