/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

import { BaseUploader } from "../BaseUploader.js";

export class ExternalFileUploader extends BaseUploader
{
	constructor(domElements, directoryView, uploaderDialog, fetchClient)
    {
		super(domElements, directoryView, uploaderDialog, fetchClient);

		this.domElements.startFileUpload.addEventListener("click", () => this.uploadFile());
		this.domElements.externalLinkField.addEventListener("input", () => this.#handleUploadButton());
        this.disableUploadButton();
    }

	async uploadFile()
	{
		const metadata = {"origin": "External link", "page_url": this.domElements.externalLinkField.value};
		// after a failure the address stays for a correction or a second try
		if (await this.uploadExternalFile(this.domElements.externalLinkField.value, metadata))
			this.domElements.externalLinkField.value = "";

	}

	#handleUploadButton()
	{
		if (this.domElements.externalLinkField.validity.valid)
			this.enableUploadButton();
		else
			this.disableUploadButton();

	}

}