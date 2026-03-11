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
	#saveButton = document.getElementById("save_template");
	#exportFormat = document.getElementById("export_format");
	#exportQuality = document.getElementById("export_quality");
	#resetButton = document.getElementById("reset_template");
	#closeButton = document.getElementById("close_template");

	constructor() {}


	get saveButton()
	{
		return this.#saveButton;
	}

	get exportFormat()
	{
		return this.#exportFormat;
	}

	getExportFormatValue()
	{
		return this.#exportFormat.value;
	}

	get exportQuality()
	{
		return this.#exportQuality.value;
	}

	getExportQualityValue()
	{
		return this.#exportQuality.value;
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
