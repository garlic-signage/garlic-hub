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

import {assertEquals, assertMatch} from "@std/assert";
import {createDom}                 from "@support/dom.js";
import {FakeFetchClient}           from "@support/FakeFetchClient.js";
import {AiImageElements}           from "@js/mediapool/uploads/AiImage/AiImageElements.js";
import {AiImageUploader}           from "@js/mediapool/uploads/AiImage/AiImageUploader.js";

// the tab of templates/mediapool/overview.mustache and the flash messages of the layout
const PAGE = `
	<meta name="csrf-token" content="the-token">
	<template id="flashMessageTemplate">
		<div class="message-bar"><span></span><button class="close-button"></button></div>
	</template>
	<section class="tab-content" id="aiImageTab">
		<form id="aiImageForm" class="ai-image-form">
			<textarea id="aiImagePrompt" required></textarea>
			<textarea id="aiImageNegativePrompt"></textarea>
			<select id="aiImageFormat">
				<option value="square">Square</option>
				<option value="landscape">Landscape</option>
			</select>
			<button type="submit" id="generateAiImage" class="primary">Generate</button>
		</form>
		<div id="previewAiImagesArea" class="preview-media"></div>
		<button id="startAiImagesUpload" class="upload-button">Start</button>
	</section>`;

const PNG = 'data:image/png;base64,' + btoa('png bytes');

/**
 * The previews of the BasePreviewHandler: they get the generated file with its metadata.
 */
class FakeFilePreviews
{
	added        = [];
	fileList     = {};
	metaDataList = {};

	addFile(file, metadata)
	{
		this.added.push({file, metadata});
	}
}

class FakeUploaderDialog
{
	calls = [];

	disableActions() { this.calls.push('disableActions'); }
	enableActions()  { this.calls.push('enableActions'); }
}

/**
 * Answers like the FakeFetchClient, the test can let it wait to see the state during the generation.
 */
class WaitingFetchClient extends FakeFetchClient
{
	wait = null;

	async fetchData(url, options)
	{
		const answer = super.fetchData(url, options);
		if (this.wait !== null)
			await this.wait;
		return answer;
	}
}

function createPage(answer = {success: true, image: PNG})
{
	const dom      = createDom(PAGE);
	const document = dom.document;
	const elements = new AiImageElements();
	const previews = new FakeFilePreviews();
	const dialog   = new FakeUploaderDialog();
	const client   = new WaitingFetchClient(answer);
	new AiImageUploader(previews, elements, {getActiveNodeId: () => 5}, dialog, client);

	return {
		document, elements, previews, dialog, client,
		[Symbol.dispose]: () => dom[Symbol.dispose]()
	};
}

function describe(page, prompt, negativePrompt = '', format = 'square')
{
	page.elements.prompt.value         = prompt;
	page.elements.negativePrompt.value = negativePrompt;
	page.elements.format.value         = format;
}

/**
 * The submit of the form; it returns the event to see whether the page stays.
 */
async function generate(page)
{
	const event = new Event('submit', {cancelable: true});
	page.elements.form.dispatchEvent(event);
	await new Promise(resolve => setTimeout(resolve, 0));
	return event;
}

function errors(page)
{
	return [...page.document.querySelectorAll('.message-bar--error span')].map(span => span.textContent);
}

Deno.test('the form asks the AI for an image of the description in the chosen format', async () =>
{
	using page = createPage();
	describe(page, '  A lobby with plants  ', ' people ', 'landscape');

	const event = await generate(page);

	assertEquals(event.defaultPrevented, true);
	const {url, options} = page.client.requests[0];
	assertEquals(url, '/async/mediapool/generateImage');
	assertEquals(options.method, 'POST');
	assertEquals(options.timeout, 185000);
	assertEquals([...options.body.entries()], [
		['prompt', 'A lobby with plants'], ['negative_prompt', 'people'], ['format', 'landscape'], ['csrf_token', 'the-token']
	]);
});

Deno.test('an empty description asks for nothing', async () =>
{
	using page = createPage();
	describe(page, '   ');

	const event = await generate(page);

	assertEquals(event.defaultPrevented, true);
	assertEquals(page.client.requests, []);
	assertEquals(page.dialog.calls, []);
});

Deno.test('the image comes into the previews with the description for the medium', async () =>
{
	using page = createPage();
	describe(page, 'A lobby');

	await generate(page);

	const [{file, metadata}] = page.previews.added;
	assertMatch(file.name, /^ai_image_\d+\.png$/);
	assertEquals(file.type, 'image/png');
	assertEquals(await file.text(), 'png bytes');
	assertEquals(metadata, {origin: 'AI image', page_url: '', description: 'A lobby'});
});

Deno.test('a JPEG gets the extension jpg', async () =>
{
	using page = createPage({success: true, image: 'data:image/jpeg;base64,' + btoa('jpeg bytes')});
	describe(page, 'A lobby');

	await generate(page);

	assertMatch(page.previews.added[0].file.name, /^ai_image_\d+\.jpg$/);
	assertEquals(page.previews.added[0].file.type, 'image/jpeg');
});

Deno.test('during the generation the uploader and the button are locked and an overlay waits', async () =>
{
	using page   = createPage();
	const answer = Promise.withResolvers();
	page.client.wait = answer.promise;
	describe(page, 'A lobby');

	const generating = generate(page);
	await new Promise(resolve => setTimeout(resolve, 0));
	assertEquals(page.elements.generateButton.disabled, true);
	assertEquals(page.dialog.calls, ['disableActions']);
	assertEquals(page.document.getElementById('waitOverlay') !== null, true);

	answer.resolve();
	await generating;
	await new Promise(resolve => setTimeout(resolve, 0));

	assertEquals(page.elements.generateButton.disabled, false);
	assertEquals(page.dialog.calls, ['disableActions', 'enableActions']);
	assertEquals(page.document.getElementById('waitOverlay'), null);
});

Deno.test('a refused generation shows the message of the backend and adds no image', async () =>
{
	using page = createPage({success: false, error_message: 'The AI refused the description'});
	describe(page, 'A lobby');

	await generate(page);

	assertEquals(errors(page), ['The AI refused the description']);
	assertEquals(page.previews.added, []);
	assertEquals(page.elements.generateButton.disabled, false);
	assertEquals(page.dialog.calls, ['disableActions', 'enableActions']);
	assertEquals(page.document.getElementById('waitOverlay'), null);
});

Deno.test('a timeout shows its message and unlocks everything', async () =>
{
	using page = createPage(new Error('Timeout: Server did not respond.'));
	describe(page, 'A lobby');

	await generate(page);

	assertEquals(errors(page), ['Timeout: Server did not respond.']);
	assertEquals(page.elements.generateButton.disabled, false);
	assertEquals(page.dialog.calls, ['disableActions', 'enableActions']);
});

Deno.test('a failed answer without message shows an empty error', async () =>
{
	for (const answer of [{success: false}, null])
	{
		using page = createPage(answer);
		describe(page, 'A lobby');

		await generate(page);

		assertEquals(errors(page), [''], JSON.stringify(answer));
		assertEquals(page.previews.added, [], JSON.stringify(answer));
	}
});
