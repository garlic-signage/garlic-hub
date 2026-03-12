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

export class InsertView
{
	#insertImage     = document.getElementById("insertImage");
	#insertText      = document.getElementById("insertText");
	#insertCircle    = document.getElementById("insertCircle");
	#insertTriangle  = document.getElementById("insertTriangle");
	#insertRectangle = document.getElementById("insertRectangle");
	#insertPolygon   = document.getElementById("insertPolygon");
	#insertHexagon   = document.getElementById("insertHexagon");
	#insertOctagon   = document.getElementById("insertOctagon");


	get insertImage()
	{
		return this.#insertImage;
	}

	get insertText()
	{
		return this.#insertText;
	}

	get insertCircle()
	{
		return this.#insertCircle;
	}

	get insertTriangle()
	{
		return this.#insertTriangle;
	}

	get insertRectangle()
	{
		return this.#insertRectangle;
	}

	get insertPolygon()
	{
		return this.#insertPolygon;
	}

	get insertHexagon()
	{
		return this.#insertHexagon;
	}

	get insertOctagon()
	{
		return this.#insertOctagon;
	}
}
