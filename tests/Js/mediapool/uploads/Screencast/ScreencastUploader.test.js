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

import {assertEquals}       from "@std/assert";
import {stub}               from "@std/testing/mock";
import {FakeTime}           from "@std/testing/time";
import {createDom}          from "@support/dom.js";
import {FakeFetchClient}    from "@support/FakeFetchClient.js";
import {replaceGlobals}     from "@support/globals.js";
import {ScreencastElements} from "@js/mediapool/uploads/Screencast/ScreencastElements.js";
import {ScreencastUploader} from "@js/mediapool/uploads/Screencast/ScreencastUploader.js";

// the texts of the page (LANG_JS of the ShowController)
const LANG = {start_record_video: 'Start recording', stop_record_video: 'Stop recording'};

// the screencast tab of templates/mediapool/overview.mustache
const PAGE = `
	<video id="screencastVideo" autoplay playsinline></video>
	<input id="toggleScreencast" type="checkbox">
	<button id="captureScreenshot" disabled>Screenshot</button>
	<button id="recordingScreencast" disabled>Record</button>
	<div id="previewScreencastsArea"></div>
	<button id="startScreencastsUpload">Upload</button>`;

const SHOT = 'data:image/jpeg;base64,' + btoa('jpeg');

/**
 * SpicyCast of public/js/external/spicy-camcast.min.js.
 */
class FakeScreencast
{
	calls      = [];
	startError = null;

	async startScreencast()
	{
		this.calls.push(['startScreencast']);
		if (this.startError !== null)
			throw this.startError;
	}

	stopError  = null;

	stopScreencast()
	{
		this.calls.push(['stopScreencast']);
		if (this.stopError !== null)
			throw this.stopError;
	}

	capturePhoto(canvas)
	{
		this.calls.push(['capturePhoto', canvas.tagName]);
		return SHOT;
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

function createPage()
{
	const dom        = createDom(PAGE);
	const globals    = replaceGlobals({MediaRecorder: FakeMediaRecorder});
	const screencast = new FakeScreencast();
	const previews   = new FakePreviews();
	const elements   = new ScreencastElements();
	const uploader   = new ScreencastUploader(screencast, previews, elements, {getActiveNodeId: () => 0},
		{disableActions() {}, enableActions() {}}, new FakeFetchClient({success: true}), LANG);

	return {
		document: dom.document, screencast, previews, elements, uploader,
		[Symbol.dispose]: () => { globals[Symbol.dispose](); dom[Symbol.dispose](); }
	};
}

function click(element)
{
	element.dispatchEvent(new Event('click'));
}

async function switchScreencast(page, on)
{
	const toggle = page.elements.toggleScreencast;
	toggle.checked = on;
	toggle.dispatchEvent(new Event('click'));
	await new Promise(resolve => setTimeout(resolve, 0));
}

Deno.test('the uploader starts with a disabled upload button', () =>
{
	using page = createPage();

	assertEquals(page.elements.startFileUpload.disabled, true);
	assertEquals(page.uploader.filePreviews, page.previews);
});

Deno.test('the started screencast enables screenshot and recording', async () =>
{
	using page = createPage();

	await switchScreencast(page, true);

	assertEquals(page.screencast.calls, [['startScreencast']]);
	assertEquals(page.elements.captureScreenshot.disabled, false);
	assertEquals(page.elements.recordingScreencast.disabled, false);
});

Deno.test('the stopped screencast disables screenshot and recording', async () =>
{
	using page = createPage();
	await switchScreencast(page, true);

	await switchScreencast(page, false);

	assertEquals(page.screencast.calls, [['startScreencast'], ['stopScreencast']]);
	assertEquals(page.elements.captureScreenshot.disabled, true);
	assertEquals(page.elements.recordingScreencast.disabled, true);
});

Deno.test('a screencast that cannot be started is logged, unchecked and enables nothing', async () =>
{
	using page      = createPage();
	using errorStub = stub(console, 'error');
	const error     = new Error('NotAllowedError');
	page.screencast.startError = error;

	await switchScreencast(page, true);

	assertEquals(errorStub.calls[0].args, ['Screencast can not be started:', error]);
	assertEquals(page.elements.toggleScreencast.checked, false);
	assertEquals(page.elements.captureScreenshot.disabled, true);
	assertEquals(page.elements.recordingScreencast.disabled, true);
});

Deno.test('a screencast that cannot be stopped is logged and disables screenshot and recording', async () =>
{
	using page      = createPage();
	using errorStub = stub(console, 'error');
	await switchScreencast(page, true);
	const error     = new Error('already ended');
	page.screencast.stopError = error;

	await switchScreencast(page, false);

	assertEquals(errorStub.calls[0].args, ['Screencast can not be stopped:', error]);
	assertEquals(page.elements.captureScreenshot.disabled, true);
	assertEquals(page.elements.recordingScreencast.disabled, true);
});

Deno.test('a screenshot is added as JPEG to the previews', async () =>
{
	using page = createPage();

	click(page.elements.captureScreenshot);

	assertEquals(page.screencast.calls, [['capturePhoto', 'CANVAS']]);
	const [{file, metadata}] = page.previews.added;
	assertEquals([file.name, file.type, await file.text()], ['screencast_shoot.jpg', 'image/jpeg', 'jpeg']);
	assertEquals(metadata, {origin: 'Screencast screenshot', page_url: ''});
});

Deno.test('the record button starts and stops a video of the screen', async () =>
{
	using time   = new FakeTime();
	using page   = createPage();
	const stream = {id: 'screen'};
	page.elements.screencastVideo.srcObject = stream;
	const button = page.elements.recordingScreencast;

	click(button);
	assertEquals(button.textContent, 'Stop recording');
	assertEquals(FakeMediaRecorder.last.stream, stream);

	time.tick(2500);
	click(button);

	assertEquals(button.textContent, 'Start recording');
	const [{file, metadata}] = page.previews.added;
	assertEquals([file.name, await file.text()], ['recorded-video.webm', 'video']);
	assertEquals(metadata, {duration: 2.5, origin: 'Screencast video', page_url: ''});
});

Deno.test('the upload button uploads the previews into the chosen directory', () =>
{
	using page      = createPage();
	using alertStub = stub(globalThis, 'alert');
	page.previews.fileList = {'preview-1': new File(['jpeg'], 'screencast_shoot.jpg')};

	click(page.elements.startFileUpload);

	assertEquals(alertStub.calls[0].args, ['Choose a directory first.']);
});
