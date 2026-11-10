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

import {assertEquals, assertThrows} from "@std/assert";
import {FakeTime}                   from "@std/testing/time";
import {replaceGlobals}             from "@support/globals.js";
import {MediaCapture}               from "@js/mediapool/uploads/MediaCapture.js";

/**
 * The MediaRecorder of the browser: start() records, stop() delivers the last chunk and then calls onstop.
 * The test hands the chunks in with deliver(). With stopsLater the browser ends the recording only with finish(),
 * as it does a little later in a task of its own.
 */
class FakeMediaRecorder
{
	static created = [];

	stream;
	state     = 'inactive';
	timeslice = null;
	ondataavailable = null;
	onstop          = null;
	stopsLater      = false;

	constructor(stream)
	{
		this.stream = stream;
		FakeMediaRecorder.created.push(this);
	}

	start(timeslice)
	{
		this.state     = 'recording';
		this.timeslice = timeslice;
	}

	deliver(text)
	{
		this.ondataavailable({data: new Blob([text])});
	}

	stop()
	{
		this.state = 'inactive';
		if (!this.stopsLater)
			this.finish();
	}

	finish()
	{
		this.deliver('end');
		this.onstop();
	}
}

/**
 * A capture with the fake recorder; it records what the callbacks get.
 */
function createCapture()
{
	FakeMediaRecorder.created = [];
	const globals = replaceGlobals({MediaRecorder: FakeMediaRecorder});
	const results = {recorded: [], stopped: 0};

	return {
		capture: new MediaCapture(),
		results,
		start(capture, stream = {id: 'stream'})
		{
			capture.start(stream, (file, duration) => results.recorded.push({file, duration}), () => results.stopped++);
			return FakeMediaRecorder.created.at(-1);
		},
		[Symbol.dispose]: () => globals[Symbol.dispose]()
	};
}

Deno.test('a new capture records nothing', () =>
{
	using page = createCapture();

	assertEquals(page.capture.isRecording(), false);
});

Deno.test('the recording of the stream is made in chunks of a second', () =>
{
	using time = new FakeTime();
	using page = createCapture();
	const stream = {id: 'camera'};

	const recorder = page.start(page.capture, stream);

	assertEquals(recorder.stream, stream);
	assertEquals(recorder.timeslice, 1000);
	assertEquals(page.capture.isRecording(), true);
});

Deno.test('the stopped recording becomes a webm video with its duration', async () =>
{
	using time = new FakeTime();
	using page = createCapture();
	const recorder = page.start(page.capture);
	recorder.deliver('first ');
	time.tick(12500);

	page.capture.stop();

	assertEquals(page.capture.isRecording(), false);
	assertEquals(page.results.stopped, 1);
	const [{file, duration}] = page.results.recorded;
	assertEquals(file.name, 'recorded-video.webm');
	assertEquals(file.type, 'video/webm');
	assertEquals(await file.text(), 'first end');
	assertEquals(duration, 12.5);
});

Deno.test('a recording stops by itself after five minutes', () =>
{
	using time = new FakeTime();
	using page = createCapture();
	page.start(page.capture);

	time.tick(MediaCapture.MAX_RECORD_SECONDS * 1000 - 1);
	assertEquals(page.capture.isRecording(), true);

	time.tick(1);
	assertEquals(page.capture.isRecording(), false);
	assertEquals(page.results.stopped, 1);
	assertEquals(page.results.recorded[0].duration, 300);
});

Deno.test('a stopped recording has no timer left', () =>
{
	using time = new FakeTime();
	using page = createCapture();
	page.start(page.capture);
	page.capture.stop();

	time.tick(MediaCapture.MAX_RECORD_SECONDS * 1000);

	assertEquals(page.results.stopped, 1);
});

Deno.test('a stop without a recording does nothing', () =>
{
	using page = createCapture();

	page.capture.stop();

	assertEquals(page.results, {recorded: [], stopped: 0});
});

Deno.test('the next recording starts with new chunks', async () =>
{
	using time = new FakeTime();
	using page = createCapture();
	page.start(page.capture).deliver('old ');
	page.capture.stop();

	page.start(page.capture).deliver('new ');
	page.capture.stop();

	assertEquals(await page.results.recorded[1].file.text(), 'new end');
});

Deno.test('a recording started before the last one has ended keeps both videos apart', async () =>
{
	using time = new FakeTime();
	using page = createCapture();
	const first = page.start(page.capture);
	first.stopsLater = true;
	first.deliver('old ');
	page.capture.stop();

	const second = page.start(page.capture);
	second.deliver('new ');
	first.finish();

	assertEquals(await page.results.recorded[0].file.text(), 'old end');
	assertEquals(page.capture.isRecording(), true);
	// the button of the new recording stays a stop button
	assertEquals(page.results.stopped, 0);

	page.capture.stop();
	assertEquals(await page.results.recorded[1].file.text(), 'new end');
});

Deno.test('the late end of the last recording leaves the timer of the next one', () =>
{
	using time = new FakeTime();
	using page = createCapture();
	const first = page.start(page.capture);
	first.stopsLater = true;
	page.capture.stop();
	page.start(page.capture);

	first.finish();
	time.tick(MediaCapture.MAX_RECORD_SECONDS * 1000);

	assertEquals(page.capture.isRecording(), false);
	assertEquals(page.results.stopped, 1);
	assertEquals(page.results.recorded.length, 2);
});

Deno.test('a recording that ended by the browser leaves the capture ready for the next', () =>
{
	using time = new FakeTime();
	using page = createCapture();
	const recorder = page.start(page.capture);

	// e.g. the camera was switched off: the recorder stops without stop() of the capture
	recorder.stop();

	assertEquals(page.capture.isRecording(), false);
	assertEquals(page.results.stopped, 1);
	page.start(page.capture);
	assertEquals(page.capture.isRecording(), true);
});

Deno.test('a data URL of a canvas becomes a file with its type', async () =>
{
	const dataUrl = 'data:image/jpeg;base64,' + btoa('\xff\xd8\xff\x00jpeg');

	const file = MediaCapture.fileFromDataUrl(dataUrl, 'webcam_shoot.jpg');

	assertEquals(file.name, 'webcam_shoot.jpg');
	assertEquals(file.type, 'image/jpeg');
	assertEquals([...new Uint8Array(await file.arrayBuffer())], [0xff, 0xd8, 0xff, 0x00, 0x6a, 0x70, 0x65, 0x67]);
});

Deno.test('a text without a type is no data URL', () =>
{
	assertThrows(() => MediaCapture.fileFromDataUrl('data:,abc', 'shot.jpg'), TypeError);
});
