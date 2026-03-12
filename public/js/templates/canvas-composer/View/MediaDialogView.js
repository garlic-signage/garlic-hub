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

export class MediaDialogView
{
	#mediaSelectorElement = document.getElementById("mediaSelectorInstance");
	#closeEditMediaDialog = document.getElementById("closeEditMediaDialog");
	#closeDialogButton    = document.getElementById("closeDialogButton");
	#mediaSelectorDialog  = document.getElementById("mediaSelectorDialog");
	#addMedia   = document.getElementById("addMedia");
	#applyMedia = document.getElementById("applyMedia");
	#dialogName = document.getElementsByClassName("dialog-name")[0];

	constructor() {}

	get mediaSelectorElement()
	{
		return this.#mediaSelectorElement;
	}

	get closeEditMediaDialog()
	{
		return this.#closeEditMediaDialog;
	}

	get closeDialogButton()
	{
		return this.#closeDialogButton;
	}

	get mediaSelectorDialog()
	{
		return this.#mediaSelectorDialog;
	}

	get addMedia()
	{
		return this.#addMedia;
	}

	get applyMedia()
	{
		return this.#applyMedia;
	}

	get dialogName()
	{
		return this.#dialogName;
	}
}
