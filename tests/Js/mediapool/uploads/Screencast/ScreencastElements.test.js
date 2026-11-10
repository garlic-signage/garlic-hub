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
import {ScreencastElements}           from "@js/mediapool/uploads/Screencast/ScreencastElements.js";

// the screencast tab of templates/mediapool/overview.mustache
const PAGE = `
	<video id="screencastVideo"></video>
	<input id="toggleScreencast" type="checkbox">
	<button id="captureScreenshot" disabled></button>
	<button id="recordingScreencast" disabled></button>
	<div id="previewScreencastsArea"></div>
	<button id="startScreencastsUpload"></button>`;

const IDS = {
	toggleScreencast: 'toggleScreencast', screencastVideo: 'screencastVideo', startFileUpload: 'startScreencastsUpload',
	previewScreencastsArea: 'previewScreencastsArea', captureScreenshot: 'captureScreenshot',
	recordingScreencast: 'recordingScreencast'
};

Deno.test('the elements of the screencast tab are read from the page', () =>
{
	using dom = createDom(PAGE);

	const elements = new ScreencastElements();

	for (const [getter, id] of Object.entries(IDS))
		assertSameElement(elements[getter], dom.document.getElementById(id), getter);
});

Deno.test('a page without the screencast tab has no elements', () =>
{
	using _dom = createDom('');

	const elements = new ScreencastElements();

	assertEquals(Object.keys(IDS).map(getter => elements[getter]), Object.keys(IDS).map(() => null));
});
