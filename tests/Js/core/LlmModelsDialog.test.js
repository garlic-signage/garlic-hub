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

import {assertEquals} from "@std/assert";
import {createDom}    from "@support/dom.js";
import {LlmModelsDialog} from "@js/core/LlmModelsDialog.js";

const LABELS = 'data-title="Models" data-filter="Filter models" data-empty="No list here" data-close="Close"';

// a row of a task (templates/generic/llm_settings.mustache) and the form of a provider (llm_connection_form.mustache)
const PAGE = `
	<select id="llm_connection_tagging"><option value="3" selected>OpenAI</option></select>
	<input type="text" id="llm_model_tagging" value="old-model">
	<button type="button" id="taskButton" class="llm-models-button" data-action="/async/llm/models"
		data-connection-field="llm_connection_tagging" data-model-field="llm_model_tagging" ${LABELS}>Load</button>

	<form id="providerForm">
		<input name="connection_id" value="">
		<input name="api_type" value="openai">
		<input name="base_url" value="https://api.example.com/v1">
		<input name="api_key" value="secret">
		<button type="button" id="formButton" class="llm-models-button" data-action="/async/llm/test" ${LABELS}>Test</button>
	</form>`;

/**
 * Answers with the given result; the test can let it wait to see the locked button.
 */
class FakeModelsService
{
	loads  = [];
	answer = {success: true, models: ['gpt-4o', 'gpt-4o-mini', 'o3']};
	wait   = null;

	async load(url, provider)
	{
		this.loads.push([url, provider]);
		if (this.wait !== null)
			await this.wait;

		return this.answer;
	}
}

function createPage()
{
	const dom      = createDom(PAGE);
	const document = dom.document;
	const service  = new FakeModelsService();

	// linkedom knows neither button.form nor form.elements
	const form = document.getElementById('providerForm');
	Object.defineProperty(document.getElementById('formButton'), 'form', {
		value: {elements: {namedItem: (name) => form.querySelector(`[name="${name}"]`)}}
	});

	return {
		document, service,
		modelField: document.getElementById('llm_model_tagging'),
		dialogFor:  (buttonId) => new LlmModelsDialog(document.getElementById(buttonId), service),
		[Symbol.dispose]: () => dom[Symbol.dispose]()
	};
}

function openDialogs(page)
{
	return [...page.document.body.querySelectorAll('dialog.llm-models')];
}

function shownModels(page)
{
	return [...page.document.querySelectorAll('.llm-models-list li')].filter(item => !item.hidden).map(item => item.textContent);
}

function filter(page, text)
{
	const input = page.document.querySelector('.llm-models-filter');
	input.value = text;
	input.dispatchEvent(new Event('input'));
}

Deno.test('every button of the page opens its own dialog', async () =>
{
	using page = createPage();

	LlmModelsDialog.attachTo(page.document);
	page.document.getElementById('formButton').dispatchEvent(new Event('click'));
	await new Promise(resolve => setTimeout(resolve, 0));

	assertEquals(openDialogs(page).length, 1);
	assertEquals(page.document.querySelectorAll('.llm-models-list button').length, 0);
});

Deno.test('the row of a task asks for the models of the chosen provider', async () =>
{
	using page = createPage();

	await page.dialogFor('taskButton').open();

	assertEquals(page.service.loads, [['/async/llm/models', {connection_id: '3'}]]);
});

Deno.test('the form of a provider sends its address, type and key', async () =>
{
	using page = createPage();

	await page.dialogFor('formButton').open();

	assertEquals(page.service.loads, [['/async/llm/test', {
		connection_id: '', api_type: 'openai', base_url: 'https://api.example.com/v1', api_key: 'secret'
	}]]);
});

Deno.test('the button is locked while the models are loaded', async () =>
{
	using page   = createPage();
	const answer = Promise.withResolvers();
	page.service.wait = answer.promise;
	const button = page.document.getElementById('taskButton');

	const opening = page.dialogFor('taskButton').open();
	assertEquals(button.disabled, true);

	answer.resolve();
	await opening;
	assertEquals(button.disabled, false);
});

Deno.test('the dialog lists the models with a filter', async () =>
{
	using page = createPage();

	await page.dialogFor('taskButton').open();

	const [dialog] = openDialogs(page);
	assertEquals(dialog.hasAttribute('open'), true);
	assertEquals(dialog.querySelector('header span').textContent, 'Models');
	assertEquals(dialog.querySelector('header button').getAttribute('aria-label'), 'Close');
	const filterField = dialog.querySelector('.llm-models-filter');
	assertEquals([filterField.placeholder, filterField.getAttribute('aria-label')], ['Filter models', 'Filter models']);
	assertEquals(shownModels(page), ['gpt-4o', 'gpt-4o-mini', 'o3']);
});

Deno.test('a model of a task row is taken into the field of the task', async () =>
{
	using page = createPage();
	let focused = 0;
	page.modelField.focus = () => focused++;
	await page.dialogFor('taskButton').open();

	page.document.querySelectorAll('.llm-models-list button')[1].dispatchEvent(new Event('click'));

	assertEquals(page.modelField.value, 'gpt-4o-mini');
	assertEquals(openDialogs(page), []);
	assertEquals(focused, 1);
});

Deno.test('the test of a provider only lists the models', async () =>
{
	using page = createPage();

	await page.dialogFor('formButton').open();

	assertEquals(page.document.querySelectorAll('.llm-models-list button').length, 0);
	assertEquals(shownModels(page), ['gpt-4o', 'gpt-4o-mini', 'o3']);
});

Deno.test('the filter finds the models by a part of the name, without case and spaces', async () =>
{
	using page = createPage();
	await page.dialogFor('taskButton').open();

	filter(page, ' MINI ');
	assertEquals(shownModels(page), ['gpt-4o-mini']);

	filter(page, '4o');
	assertEquals(shownModels(page), ['gpt-4o', 'gpt-4o-mini']);

	filter(page, '');
	assertEquals(shownModels(page), ['gpt-4o', 'gpt-4o-mini', 'o3']);
});

Deno.test('an address without a list says so', async () =>
{
	using page = createPage();
	page.service.answer = {success: true, models: []};

	await page.dialogFor('formButton').open();

	const [dialog] = openDialogs(page);
	assertEquals(dialog.querySelector('.llm-models-message').textContent, 'No list here');
	assertEquals(dialog.querySelector('.llm-models-error'), null);
	assertEquals(dialog.querySelector('.llm-models-filter'), null);
});

Deno.test('a failed connection shows its error in the dialog', async () =>
{
	using page = createPage();
	page.service.answer = {success: false, error_message: 'The key was refused'};

	await page.dialogFor('formButton').open();

	const [dialog] = openDialogs(page);
	assertEquals(dialog.querySelector('.llm-models-error').textContent, 'The key was refused');
	assertEquals(dialog.querySelector('.llm-models-list'), null);
});

Deno.test('opening again replaces the dialog before, closing removes it', async () =>
{
	using page   = createPage();
	const dialog = page.dialogFor('taskButton');

	await dialog.open();
	await dialog.open();
	assertEquals(openDialogs(page).length, 1);

	openDialogs(page)[0].querySelector('header button').dispatchEvent(new Event('click'));
	assertEquals(openDialogs(page), []);
});
