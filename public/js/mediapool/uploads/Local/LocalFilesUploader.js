/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
 This file is part of the garlic-hub source code

 This program is free software: you can redistribute it and/or  modify
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
import { UploadApiConfig } from '../UploadApiConfig.js';

export class LocalFilesUploader extends BaseUploader
{
    #filePreviews   = null;

    constructor(filePreviews, domElements, directoryView, uploaderDialog, fetchClient)
    {
		super(domElements, directoryView, uploaderDialog, fetchClient);

		this.#filePreviews    = filePreviews;
		this.domElements.startFileUpload.addEventListener('click', () => this.uploadFiles());
    }

	get filePreviews() { return this.#filePreviews; }

	uploadFiles()
	{
		const fileList = this.#filePreviews.fileList;
		if (fileList.length === 0)
		{
			alert("No files selected for upload.");
			return;
		}

		if (this.directoryView.getActiveNodeId() === 0)
		{
			alert("Choose a directory first.");
			return;
		}

		(async () => {
			for (const [id, file] of /** @type {Object.<string, File>} */ Object.entries(fileList))
			{
				// maybe some files in the queue where deleted.
				let container = document.querySelector(`[data-preview-id="${id}"]`);
				if (!container)
					continue;
				try
				{

					this.uploaderDialog.disableActions();
					let metadata = {};
					if (this.#filePreviews.metaDataList[id])
						metadata = this.#filePreviews.metaDataList[id];
					const formData = new FormData();
					formData.append("file", file);
					formData.append("node_id", this.directoryView.getActiveNodeId());
					formData.append("metadata", JSON.stringify(this.#filePreviews.metaDataList[id]));

					const options  = {method: "POST", body: formData};

					const progressBar = this.createProgressbar(container);

					this.fetchClient.initUploadWithProgress();
					let xhr = this.fetchClient.getUploadProgressHandle();
					this.#filePreviews.setUploadHandler(xhr, id);
					/**
					 * @type {{ error_message?: string, success: boolean }}
					 */
					const results = await this.fetchClient.uploadWithProgress(UploadApiConfig.UPLOAD_LOCAL_FILE, options, (progress) => {
						progressBar.style.display = "block";
						progressBar.style.width = progress + "%";
						progressBar.textContent = Math.round(progress) + "%";
					});

					if (!results.success)
						console.error('Error for file:', file.name, results.error_message || 'Unknown error');
					else
						this.#filePreviews.removeFromPreview(id);

				}
				catch(error)
				{
					if (error.message === 'Upload aborted.')
						console.log('Upload aborted for file:', file.name);
					else
					{
						console.log('Upload failed for file:', file.name, '\n', error.message);
						container.className = "previewContainerError";
					}
					this.uploaderDialog.enableActions()
				}
				finally
				{
					this.uploaderDialog.enableActions()
				}

			}

		})();
	}
}
