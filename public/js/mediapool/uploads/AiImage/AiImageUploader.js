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

import { LocalFilesUploader } from "../Local/LocalFilesUploader.js";
import { MediaCapture } from "../MediaCapture.js";
import { UploadApiConfig } from "../UploadApiConfig.js";
import { Utils } from "../../../core/Utils.js";
import { WaitOverlay } from "../../../core/WaitOverlay.js";
import { FlashMessageHandler } from "../../../core/FlashMessageHandler.js";
import { FetchClient } from "../../../core/FetchClient.js";

/**
 * The AI makes an image from a description. It comes into the previews like a photo of the camera and is uploaded
 * from there into the current folder, the description becomes the description of the medium.
 */
export class AiImageUploader extends LocalFilesUploader
{
	#flashMessageHandler = new FlashMessageHandler();

	constructor(filePreviews, domElements, directoryView, uploaderDialog, fetchClient)
	{
		super(filePreviews, domElements, directoryView, uploaderDialog, fetchClient);

		this.domElements.form.addEventListener("submit", async (event) => {
			event.preventDefault();
			await this.#generate();
		});
	}

	async #generate()
	{
		const prompt = this.domElements.prompt.value.trim();
		if (prompt === "")
			return;

		const formData = new FormData();
		formData.append("prompt", prompt);
		formData.append("format", this.domElements.format.value);
		formData.append("csrf_token", Utils.csrfToken());

		const waitOverlay = new WaitOverlay();
		waitOverlay.start();
		this.uploaderDialog.disableActions();
		this.domElements.generateButton.disabled = true;
		try
		{
			const result = await this.fetchClient.fetchData(UploadApiConfig.GENERATE_IMAGE, {method: "POST", body: formData, timeout: FetchClient.IMAGE_TIMEOUT});
			if (!result?.success)
				throw new Error(result?.error_message ?? "");

			const extension = result.image.substring(11, result.image.indexOf(";")).replace("jpeg", "jpg");
			const file      = MediaCapture.fileFromDataUrl(result.image, "ai_image_" + Date.now() + "." + extension);
			this.filePreviews.addFile(file, {"origin": "AI image", "page_url": "", "description": prompt});
		}
		catch (error)
		{
			this.#flashMessageHandler.showError(error.message);
		}
		finally
		{
			this.domElements.generateButton.disabled = false;
			this.uploaderDialog.enableActions();
			waitOverlay.stop();
		}
	}
}
