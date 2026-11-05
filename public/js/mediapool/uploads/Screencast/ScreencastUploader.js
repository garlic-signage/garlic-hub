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

import { LocalFilesUploader } from "../Local/LocalFilesUploader.js";
import { MediaCapture } from "../MediaCapture.js";

export class ScreencastUploader extends LocalFilesUploader
{
    #screencast     = null;
    #capture        = new MediaCapture();

    constructor(screencast, filePreviews, domElements, directoryView, uploaderDialog, fetchClient)
    {
		super(filePreviews, domElements, directoryView, uploaderDialog, fetchClient);
		this.#screencast = screencast;

		this.domElements.toggleScreencast.addEventListener("click", (event) => this.#toggleScreencast(event));
		this.domElements.captureScreenshot.addEventListener("click", () => this.#captureScreenshot());
		this.domElements.recordingScreencast.addEventListener("click", (event) => this.#toggleRecording(event));
	}

	async #toggleScreencast(event)
	{
		try
		{
			if(event.target.checked)
			{
				await this.#screencast.startScreencast();
				this.domElements.captureScreenshot.disabled = false;
				this.domElements.recordingScreencast.disabled = false;

			}
			else
			{
				this.#screencast.stopScreencast();
				this.domElements.captureScreenshot.disabled = true;
				this.domElements.recordingScreencast.disabled = true;
			}

		}
		catch (err)
		{
			console.error("Error during start / stop of screencasts:", err);
		}
	}


	#toggleRecording(event)
	{
		const button = event.target;
		if (this.#capture.isRecording())
		{
			this.#capture.stop();
			return;
		}

		this.#capture.start(
			this.domElements.screencastVideo.srcObject,
			(file, duration) => this.filePreviews.addFile(file, {"duration": duration, "origin": "Screencast video", "page_url": ""}),
			() => button.textContent = "Start Aufnahme"
		);
		button.textContent = "Stop Aufnahme";
	}

	#captureScreenshot()
	{
		const file = MediaCapture.fileFromDataUrl(this.#screencast.capturePhoto(document.createElement("canvas")), "screencast_shoot.jpg");
		this.filePreviews.addFile(file, {"origin": "Screencast screenshot", "page_url": ""});
	}

}