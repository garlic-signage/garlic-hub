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

export class WebcamUploader extends LocalFilesUploader
{
    #maxRecordTime  = 300; // 5min
    #webcam         = null;
    #mediaRecorder  = null;
    #recordedChunks = [];
    #stopTimer      = null;

    constructor(webcam, filePreviews, domElements, directoryView, uploaderDialog, fetchClient)
    {
		super(filePreviews, domElements, directoryView, uploaderDialog, fetchClient);
        this.#webcam       = webcam;

		this.domElements.detectCameras.addEventListener("click", () => this.#detectCameras());
        this.domElements.capturePhotoButton.addEventListener("click", () => this.#capturePhoto());
        this.domElements.startRecordingButton.addEventListener("click", (event) => this.#toggleRecording(event));
        this.domElements.selectCamera.addEventListener("change", (event) => this.#selectWebcam(event));
        this.domElements.toggleCamera.addEventListener("click", (event) => this.#toggleWebcam(event));

	}

    #selectWebcam(event)
    {
        const selectedValue = event.target.value;
        this.#webcam.selectCamera(selectedValue);
        this.domElements.toggleCamera.disabled = selectedValue === "-";

    }

    async #toggleWebcam(event)
    {
        if(event.target.checked)
        {
            try
            {
                await this.#webcam.startCamera();
            }
            catch (error)
            {
                // e.g. the permission is denied
                event.target.checked = false;
                console.error("Camera can not be started:", error);
                return;
            }
            this.domElements.selectCamera.disabled = true;
            this.domElements.capturePhotoButton.disabled = false;
            this.domElements.startRecordingButton.disabled = false;

        }
        else
        {
            this.#webcam.stopCamera();
            this.domElements.selectCamera.disabled = false;
            this.domElements.capturePhotoButton.disabled = true;
            this.domElements.startRecordingButton.disabled = true;
        }
    }

    #toggleRecording(event)
    {
        if (this.#mediaRecorder === null || this.#mediaRecorder.state !== 'recording')
        {
            let stream = this.domElements.webcamVideo.srcObject;
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
				const metadata = {"duration":  durationInSeconds, "origin": "Camera video", "page_url": "" };
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

    #capturePhoto()
    {
        const dataURL = this.#webcam.capturePhoto(document.createElement("canvas"))

        const [header, base64] = dataURL.split(',');
        const mimeType = header.match(/:(.*?);/)[1]; // MIME-Typ extrahieren

        const binary = atob(base64);
        const array = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++)
        {
            array[i] = binary.charCodeAt(i);
        }

        const file = new File([array], "webcam_shoot.jpg", { type: mimeType });

		const metadata = {"origin": "Camera screenshot", "page_url": ""};
        this.filePreviews.addFile(file, metadata);
    }

    #detectCameras()
    {
        this.#webcam.detectVideoDevices().then(videoDevices =>
        {
            // a second detection replaces the cameras of the first
            for (const option of [...this.domElements.selectCamera.options])
            {
                if (option.value !== "-")
                    option.remove();
            }
            for(let webcam of videoDevices)
            {
                this.domElements.addCameraToSelect(webcam.deviceId, webcam.label)
            }
			if (videoDevices.length > 0)
				this.domElements.selectCamera.disabled = false;
        })
        .catch(err => {console.error('Fehler:', err);});
    }

}