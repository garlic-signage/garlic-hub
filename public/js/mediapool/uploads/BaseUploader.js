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

import { UploadApiConfig } from './UploadApiConfig.js';
import {Utils} from "../../core/Utils.js";

export class BaseUploader
{
    #directoryView   = null;
    #domElements     = null;
    #fetchClient     = null;
    #uploaderDialog  = null;

    constructor(domElements, directoryView, uploaderDialog, fetchClient)
    {
		this.#domElements     = domElements;
        this.#directoryView   = directoryView;
        this.#uploaderDialog  = uploaderDialog;
        this.#fetchClient     = fetchClient;

		this.#domElements.startFileUpload.disabled = true;
	}

	async uploadExternalFile(filePath, metadata = null)
	{
		if (this.directoryView.getActiveNodeId() === 0)
		{
			alert("Choose a directory first.");
			return;
		}

		try
		{
			this.uploaderDialog.disableActions();
			const formData = new FormData();
			formData.append("node_id", String(this.directoryView.getActiveNodeId()));
			formData.append("external_link", filePath);
			formData.append("csrf_token", Utils.csrfToken());
			if (metadata !== null)
				formData.append("metadata", JSON.stringify(metadata));

			const options  = {method: "POST", body: formData};

			const result = await this.fetchClient.fetchData(UploadApiConfig.UPLOAD_FROM_URL, options);

			if (!result || !result.success)
				console.error('Error for file:', filePath, result?.error_message || 'Unknown error');
			else
				this.disableUploadButton();
		}
		catch(error)
		{
			if (error.message === 'Upload aborted.')
				console.log('Upload aborted for file:', filePath);
			else
				console.log('Upload failed for file:', filePath, '\n', error.message);
		}
		finally
		{
			this.uploaderDialog.enableActions()
		}
	}

	/**
	 * @type {DirectoryView}
	 */
	get directoryView()	{ return this.#directoryView; }

	get domElements() {	return this.#domElements; }

	/**
	 * @type {UploaderDialog}
	 */
	get uploaderDialog() { return this.#uploaderDialog; }

	/**
	 * @type {FetchClient}
	 */
	get fetchClient() {	return this.#fetchClient;}

	disableUploadButton()
	{
		this.#domElements.startFileUpload.disabled = true;
	}

	enableUploadButton()
	{
		this.#domElements.startFileUpload.disabled = false;
	}

	createProgressbar(container)
	{
		let progressContainer = document.createElement('div');
		progressContainer.id = "progressContainer";
		let progressBar = document.createElement('progress');
		progressBar.id = "progressBar";
		progressContainer.appendChild(progressBar);
		container.appendChild(progressContainer);

		return progressBar;
	}

	#disableActions()
	{
		this.#uploaderDialog.disableActions();
		this.enableUploadButton();
		document.getElementById("linkTab").disabled = true;
	}

	#enableActions()
	{
		this.#uploaderDialog.enableActions();
		this.disableUploadButton();
		document.getElementById("linkTab").disabled = false;
	}
}
