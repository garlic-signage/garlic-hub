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

export class SaveView
{
	#saveButton    = document.getElementById("save");
	#exportFormat  = document.getElementById("exportFormat");
	#exportQuality = document.getElementById("exportQuality");
	#resetButton   = document.getElementById("reset");
	#closeButton   = document.getElementById("close");

	constructor() {}


	get saveButton()
	{
		return this.#saveButton;
	}

	setSaveNotify()
	{
		return this.#saveButton.classList.add("notify-save");
	}

	unsetSaveNotify()
	{
		return this.#saveButton.classList.remove("notify-save");
	}


	get exportFormat()
	{
		return this.#exportFormat;
	}

	getExportFormatValue()
	{
		return this.#exportFormat?.value ?? 'jpg';	}


	getExportQualityValue()
	{
		return this.#exportQuality?.value ?? 80;
	}

	hideExportQuality()
	{
		this.#exportQuality.style.display = 'none';
	}

	showExportQuality()
	{
		this.#exportQuality.style.display = 'block';
	}

	get resetButton()
	{
		return this.#resetButton;
	}

	get closeButton()
	{
		return this.#closeButton;
	}
}
