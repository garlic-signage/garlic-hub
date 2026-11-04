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

export class ScreencastUploader extends LocalFilesUploader
{
    #maxRecordTime  = 300; // 5min
    #screencast     = null;
    #mediaRecorder  = null;
    #recordedChunks = [];
    #stopTimer      = null;

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
        if (this.#mediaRecorder === null || this.#mediaRecorder.state !== 'recording')
        {
            let stream = this.domElements.screencastVideo.srcObject;
            this.#recordedChunks = [];
	        this.#mediaRecorder = new MediaRecorder(stream);

            this.#mediaRecorder.start(1000); // 1000ms = 1 sec per chunk
			const startTime = Date.now();
            event.target.textContent = "Stop Aufnahme";

            // the recorder of this recording: a stop by hand clears the timer, a later recording has its own
            const recorder = this.#mediaRecorder;
            this.#stopTimer = setTimeout(() => {
                if (recorder.state === 'recording')
                {
                    recorder.stop();
                    event.target.textContent = "Start Aufnahme";
                }
            }, this.#maxRecordTime * 1000);

            this.#mediaRecorder.ondataavailable = (event) => {
                this.#recordedChunks.push(event.data);
            };

            this.#mediaRecorder.onstop = () => {
                const blob = new Blob(this.#recordedChunks, { type: "video/webm;" });
				const endTime = Date.now();
				const durationInSeconds = (endTime - startTime) / 1000; // ms to sec

				const file = new File([blob], "recorded-video.webm", { type: blob.type });
				const metadata = {"duration":  durationInSeconds, "origin": "Screencast video", "page_url": "" };
				this.filePreviews.addFile(file, metadata);
				this.#mediaRecorder = null;

            };
        }
        else
        {
            clearTimeout(this.#stopTimer);
            this.#mediaRecorder.stop();
            event.target.textContent = "Start Aufnahme";
            this.#mediaRecorder = null;
        }
    }

	#captureScreenshot()
	{
		const dataURL = this.#screencast.capturePhoto(document.createElement("canvas"))

		const [header, base64] = dataURL.split(',');
		const mimeType = header.match(/:(.*?);/)[1]; // MIME-Typ extrahieren

		const binary = atob(base64);
		const array = new Uint8Array(binary.length);
		for (let i = 0; i < binary.length; i++)
		{
			array[i] = binary.charCodeAt(i);
		}

		const file = new File([array], "screencast_shoot.jpg", { type: mimeType });

		const metadata = {"origin": "Screencast screenshot", "page_url": ""};
		this.filePreviews.addFile(file, metadata);
	}

}