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

import {assertEquals}                 from "@std/assert";
import {assertSameElement, createDom} from "@support/dom.js";
import {WebcamElements}               from "@js/mediapool/uploads/Webcam/WebcamElements.js";

// the camera tab of templates/mediapool/overview.mustache
const PAGE = `
	<video id="webcamVideo"></video>
	<button id="detectCameras"></button>
	<select id="selectCamera" disabled><option value="-">-</option></select>
	<input id="toggleWebcam" type="checkbox">
	<button id="capturePhoto"></button>
	<button id="recordCamera"></button>
	<div id="previewRecordsArea"></div>
	<button id="startCameraUpload"></button>`;

const IDS = {
	detectCameras: 'detectCameras', capturePhotoButton: 'capturePhoto', startRecordingButton: 'recordCamera',
	webcamVideo: 'webcamVideo', previewRecordsArea: 'previewRecordsArea', selectCamera: 'selectCamera',
	toggleCamera: 'toggleWebcam', startFileUpload: 'startCameraUpload'
};

Deno.test('the elements of the camera tab are read from the page', () =>
{
	using dom = createDom(PAGE);

	const elements = new WebcamElements();

	for (const [getter, id] of Object.entries(IDS))
		assertSameElement(elements[getter], dom.document.getElementById(id), getter);
});

Deno.test('a page without the camera tab has no elements', () =>
{
	using _dom = createDom('');

	const elements = new WebcamElements();

	for (const getter of Object.keys(IDS))

		assertSameElement(elements[getter], null, getter);
});

Deno.test('a camera is added to the choice with its id and name as text', () =>
{
	using dom      = createDom(PAGE);
	const elements = new WebcamElements();

	elements.addCameraToSelect('cam-1', 'Front <USB>');

	const options = [...dom.document.querySelectorAll('#selectCamera option')];
	assertEquals(options.map(option => [option.value, option.textContent]), [['-', '-'], ['cam-1', 'Front <USB>']]);
	assertEquals(options[1].children.length, 0);
});
