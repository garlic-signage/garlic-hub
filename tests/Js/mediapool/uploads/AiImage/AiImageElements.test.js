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
import {AiImageElements}              from "@js/mediapool/uploads/AiImage/AiImageElements.js";

// the tab of templates/mediapool/overview.mustache, only there with an AI for images
const PAGE = `
	<section class="tab-content" id="aiImageTab">
		<form id="aiImageForm" class="ai-image-form">
			<textarea id="aiImagePrompt" required></textarea>
			<textarea id="aiImageNegativePrompt"></textarea>
			<select id="aiImageFormat"><option value="square">Square</option></select>
			<button type="submit" id="generateAiImage" class="primary">Generate</button>
		</form>
		<div id="previewAiImagesArea" class="preview-media"></div>
		<button id="startAiImagesUpload" class="upload-button">Start</button>
	</section>`;

const IDS = {
	form: 'aiImageForm', prompt: 'aiImagePrompt', negativePrompt: 'aiImageNegativePrompt', format: 'aiImageFormat',
	generateButton: 'generateAiImage', previewArea: 'previewAiImagesArea', startFileUpload: 'startAiImagesUpload'
};

Deno.test('the elements of the tab are read from the page', () =>
{
	using dom = createDom(PAGE);

	const elements = new AiImageElements();

	for (const [getter, id] of Object.entries(IDS))
		assertSameElement(elements[getter], dom.document.getElementById(id), getter);
});

Deno.test('the AI images are available only with their tab', () =>
{
	{
		using _dom = createDom(PAGE);
		assertEquals(AiImageElements.isAvailable(), true);
	}
	{
		using _dom = createDom('<section class="tab-content" id="linkTab"></section>');
		assertEquals(AiImageElements.isAvailable(), false);
	}
});
