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

/**
 * The recording and the pictures of the webcam and of the screencast: a stream becomes a video file,
 * a picture of a canvas an image file.
 */
export class MediaCapture
{
	static MAX_RECORD_SECONDS = 300; // 5 min

	#recorder  = null;
	#chunks    = [];
	#stopTimer = null;

	isRecording()
	{
		return this.#recorder !== null && this.#recorder.state === "recording";
	}

	/**
	 * Stops by itself after MAX_RECORD_SECONDS.
	 *
	 * @param {MediaStream} stream
	 * @param {function(File, number)} onRecorded the video and its duration in seconds
	 * @param {function()} onStopped also when the time is up
	 */
	start(stream, onRecorded, onStopped)
	{
		const recorder  = new MediaRecorder(stream);
		const startTime = Date.now();
		this.#chunks    = [];
		this.#recorder  = recorder;

		recorder.ondataavailable = (event) => this.#chunks.push(event.data);
		recorder.onstop = () =>
		{
			clearTimeout(this.#stopTimer);
			const blob = new Blob(this.#chunks, { type: "video/webm;" });
			onRecorded(new File([blob], "recorded-video.webm", { type: blob.type }), (Date.now() - startTime) / 1000);
			onStopped();
			if (this.#recorder === recorder)
				this.#recorder = null;
		};

		recorder.start(1000); // 1000ms = 1 sec per chunk
		// the timer of this recording: a later recording has its own
		this.#stopTimer = setTimeout(() =>
		{
			if (recorder.state === "recording")
				recorder.stop();
		}, MediaCapture.MAX_RECORD_SECONDS * 1000);
	}

	stop()
	{
		clearTimeout(this.#stopTimer);
		if (this.isRecording())
			this.#recorder.stop();
	}

	/**
	 * @param {string} dataURL e.g. of canvas.toDataURL()
	 */
	static fileFromDataUrl(dataURL, fileName)
	{
		const [header, base64] = dataURL.split(',');
		const mimeType = header.match(/:(.*?);/)[1];

		const binary = atob(base64);
		const array  = new Uint8Array(binary.length);
		for (let i = 0; i < binary.length; i++)
			array[i] = binary.charCodeAt(i);

		return new File([array], fileName, { type: mimeType });
	}
}
