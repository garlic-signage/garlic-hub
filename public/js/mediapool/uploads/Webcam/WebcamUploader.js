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

export class WebcamUploader extends LocalFilesUploader
{
    #webcam         = null;
    #capture        = new MediaCapture();
    #lang           = null;

    /**
     * @param {Object} lang the texts of the page, start_record_video and stop_record_video for the record button
     */
    constructor(webcam, filePreviews, domElements, directoryView, uploaderDialog, fetchClient, lang)
    {
		super(filePreviews, domElements, directoryView, uploaderDialog, fetchClient);
        this.#webcam       = webcam;
        this.#lang         = lang;

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
		const button = event.target;
		if (this.#capture.isRecording())
		{
			this.#capture.stop();
			return;
		}

		this.#capture.start(
			this.domElements.webcamVideo.srcObject,
			(file, duration) => this.filePreviews.addFile(file, {"duration": duration, "origin": "Camera video", "page_url": ""}),
			() => button.textContent = this.#lang.start_record_video
		);
		button.textContent = this.#lang.stop_record_video;
	}

	#capturePhoto()
	{
		const file = MediaCapture.fileFromDataUrl(this.#webcam.capturePhoto(document.createElement("canvas")), "webcam_shoot.jpg");
		this.filePreviews.addFile(file, {"origin": "Camera screenshot", "page_url": ""});
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