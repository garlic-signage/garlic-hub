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

import {assertEquals}    from "@std/assert";
import {stub}            from "@std/testing/mock";
import {FakeTime}        from "@std/testing/time";
import {createDom}       from "@support/dom.js";
import {FakeFetchClient} from "@support/FakeFetchClient.js";
import {replaceGlobals}  from "@support/globals.js";
import {WebcamElements}  from "@js/mediapool/uploads/Webcam/WebcamElements.js";
import {WebcamUploader}  from "@js/mediapool/uploads/Webcam/WebcamUploader.js";

// the camera tab of templates/mediapool/overview.mustache
const PAGE = `
	<video id="webcamVideo" autoplay playsinline></video>
	<button id="detectCameras" type="button">Detect</button>
	<select id="selectCamera" disabled><option value="-">-</option></select>
	<input id="toggleWebcam" type="checkbox" disabled>
	<button id="capturePhoto" disabled>Photo</button>
	<button id="recordCamera" disabled>Record</button>
	<div id="previewRecordsArea"></div>
	<button id="startCameraUpload">Upload</button>`;

const PHOTO = 'data:image/jpeg;base64,' + btoa('jpeg');

/**
 * SpicyCam of public/js/external/spicy-camcast.min.js.
 */
class FakeCamera
{
	calls   = [];
	devices = [{deviceId: 'cam-1', label: 'Front'}, {deviceId: 'cam-2', label: 'Back'}];
	startError = null;

	detectVideoDevices()
	{
		this.calls.push(['detectVideoDevices']);
		return this.devices instanceof Error ? Promise.reject(this.devices) : Promise.resolve(this.devices);
	}

	selectCamera(id)
	{
		this.calls.push(['selectCamera', id]);
	}

	async startCamera()
	{
		this.calls.push(['startCamera']);
		if (this.startError !== null)
			throw this.startError;
	}

	stopCamera()
	{
		this.calls.push(['stopCamera']);
	}

	capturePhoto(canvas)
	{
		this.calls.push(['capturePhoto', canvas.tagName]);
		return PHOTO;
	}
}

class FakePreviews
{
	added = [];

	addFile(file, metadata)
	{
		this.added.push({file, metadata});
	}
}

/**
 * The MediaRecorder of the browser: stop() delivers the chunk and calls onstop.
 */
class FakeMediaRecorder
{
	static last = null;

	state = 'inactive';
	stream;

	constructor(stream)
	{
		this.stream = stream;
		FakeMediaRecorder.last = this;
	}

	start()
	{
		this.state = 'recording';
	}

	stop()
	{
		this.state = 'inactive';
		this.ondataavailable({data: new Blob(['video'])});
		this.onstop();
	}
}

function createPage(nodeId = 5)
{
	const dom      = createDom(PAGE);
	const globals  = replaceGlobals({MediaRecorder: FakeMediaRecorder});
	const document = dom.document;
	const camera   = new FakeCamera();
	const previews = new FakePreviews();
	const elements = new WebcamElements();
	const uploader = new WebcamUploader(camera, previews, elements, {getActiveNodeId: () => nodeId},
		{disableActions() {}, enableActions() {}}, new FakeFetchClient({success: true}));

	return {
		document, camera, previews, elements, uploader,
		[Symbol.dispose]: () => { globals[Symbol.dispose](); dom[Symbol.dispose](); }
	};
}

function wait()
{
	return new Promise(resolve => setTimeout(resolve, 0));
}

function click(element)
{
	element.dispatchEvent(new Event('click'));
}

function cameraOptions(page)
{
	return [...page.elements.selectCamera.options].map(option => [option.value, option.textContent]);
}

/**
 * The handler reads event.target after an await: linkedom clears it at the end of the dispatch, the browser does not.
 */
async function switchCamera(page, on)
{
	const toggle = page.elements.toggleCamera;
	toggle.checked = on;
	toggle.dispatchEvent(new (class extends Event
	{
		get target() { return toggle; }
		set target(value) {}
	})('click'));
	await wait();
}

Deno.test('the uploader starts with a disabled upload button', () =>
{
	using page = createPage();

	assertEquals(page.elements.startFileUpload.disabled, true);
	assertEquals(page.uploader.filePreviews, page.previews);
});

Deno.test('the detected cameras can be chosen', async () =>
{
	using page = createPage();

	click(page.elements.detectCameras);
	await wait();

	assertEquals(cameraOptions(page), [['-', '-'], ['cam-1', 'Front'], ['cam-2', 'Back']]);
	assertEquals(page.elements.selectCamera.disabled, false);
});

Deno.test('a second detection replaces the cameras of the first', async () =>
{
	using page = createPage();
	click(page.elements.detectCameras);
	await wait();

	page.camera.devices = [{deviceId: 'cam-3', label: 'USB'}];
	click(page.elements.detectCameras);
	await wait();

	assertEquals(cameraOptions(page), [['-', '-'], ['cam-3', 'USB']]);
});

Deno.test('without a camera nothing can be chosen', async () =>
{
	using page = createPage();
	page.camera.devices = [];

	click(page.elements.detectCameras);
	await wait();

	assertEquals(cameraOptions(page), [['-', '-']]);
	assertEquals(page.elements.selectCamera.disabled, true);
});

Deno.test('a failed detection is logged', async () =>
{
	using page      = createPage();
	using errorStub = stub(console, 'error');
	const error     = new Error('Permission denied');
	page.camera.devices = error;

	click(page.elements.detectCameras);
	await wait();

	assertEquals(errorStub.calls[0].args, ['Fehler:', error]);
	assertEquals(page.elements.selectCamera.disabled, true);
});

Deno.test('a chosen camera can be switched on, the dash cannot', () =>
{
	using page   = createPage();
	const select = page.elements.selectCamera;
	page.elements.addCameraToSelect('cam-1', 'Front');

	select.value = 'cam-1';
	select.dispatchEvent(new Event('change'));
	assertEquals(page.elements.toggleCamera.disabled, false);

	select.value = '-';
	select.dispatchEvent(new Event('change'));
	assertEquals(page.elements.toggleCamera.disabled, true);

	assertEquals(page.camera.calls, [['selectCamera', 'cam-1'], ['selectCamera', '-']]);
});

Deno.test('the switched on camera enables photo and recording and locks the choice', async () =>
{
	using page = createPage();

	await switchCamera(page, true);

	assertEquals(page.camera.calls, [['startCamera']]);
	assertEquals(page.elements.selectCamera.disabled, true);
	assertEquals(page.elements.capturePhotoButton.disabled, false);
	assertEquals(page.elements.startRecordingButton.disabled, false);
});

Deno.test('the switched off camera disables photo and recording and frees the choice', async () =>
{
	using page = createPage();
	await switchCamera(page, true);

	await switchCamera(page, false);

	assertEquals(page.camera.calls, [['startCamera'], ['stopCamera']]);
	assertEquals(page.elements.selectCamera.disabled, false);
	assertEquals(page.elements.capturePhotoButton.disabled, true);
	assertEquals(page.elements.startRecordingButton.disabled, true);
});

Deno.test('a camera that cannot be started stays off', async () =>
{
	using page      = createPage();
	using errorStub = stub(console, 'error');
	const error     = new Error('NotAllowedError');
	page.camera.startError = error;

	await switchCamera(page, true);

	assertEquals(page.elements.toggleCamera.checked, false);
	assertEquals(errorStub.calls[0].args, ['Camera can not be started:', error]);
	assertEquals(page.elements.capturePhotoButton.disabled, true);
	assertEquals(page.elements.startRecordingButton.disabled, true);
});

Deno.test('a photo of the camera is added as JPEG to the previews', async () =>
{
	using page = createPage();

	click(page.elements.capturePhotoButton);

	assertEquals(page.camera.calls, [['capturePhoto', 'CANVAS']]);
	const [{file, metadata}] = page.previews.added;
	assertEquals([file.name, file.type, await file.text()], ['webcam_shoot.jpg', 'image/jpeg', 'jpeg']);
	assertEquals(metadata, {origin: 'Camera screenshot', page_url: ''});
});

Deno.test('the record button starts and stops a video of the camera', async () =>
{
	using time   = new FakeTime();
	using page   = createPage();
	const stream = {id: 'camera'};
	page.elements.webcamVideo.srcObject = stream;
	const button = page.elements.startRecordingButton;

	click(button);
	assertEquals(button.textContent, 'Stop Aufnahme');
	assertEquals(FakeMediaRecorder.last.stream, stream);

	time.tick(4000);
	click(button);

	assertEquals(button.textContent, 'Start Aufnahme');
	const [{file, metadata}] = page.previews.added;
	assertEquals([file.name, await file.text()], ['recorded-video.webm', 'video']);
	assertEquals(metadata, {duration: 4, origin: 'Camera video', page_url: ''});
});

Deno.test('the upload button uploads the previews into the chosen directory', () =>
{
	using page      = createPage(0);
	using alertStub = stub(globalThis, 'alert');
	page.previews.fileList = {'preview-1': new File(['jpeg'], 'webcam_shoot.jpg')};

	click(page.elements.startFileUpload);

	assertEquals(alertStub.calls[0].args, ['Choose a directory first.']);
});
