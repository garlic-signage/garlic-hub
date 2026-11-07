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

export class AiImageElements
{
	#form            = document.getElementById("aiImageForm");
	#prompt          = document.getElementById("aiImagePrompt");
	#format          = document.getElementById("aiImageFormat");
	#generateButton  = document.getElementById("generateAiImage");
	#previewArea     = document.getElementById("previewAiImagesArea");
	#startFileUpload = document.getElementById("startAiImagesUpload");

	/**
	 * The tab is there only when the user has an AI for images.
	 */
	static isAvailable()
	{
		return document.getElementById("aiImageTab") !== null;
	}

	get form() { return this.#form; }

	get prompt() { return this.#prompt; }

	get format() { return this.#format; }

	get generateButton() { return this.#generateButton; }

	get previewArea() { return this.#previewArea; }

	get startFileUpload() { return this.#startFileUpload; }
}
