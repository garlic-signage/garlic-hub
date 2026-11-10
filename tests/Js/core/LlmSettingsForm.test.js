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
import {createDom}       from "@support/dom.js";
import {LlmSettingsForm} from "@js/core/LlmSettingsForm.js";

// templates/generic/llm_settings.mustache: a provider and a task of the company
const PAGE = `
	<form>
		<button class="llm-delete-button" data-confirm="Delete the provider?">Delete</button>
		<div class="llm-task">
			<select class="llm-task-mode">
				<option value="none">None</option>
				<option value="company_all">Company for all</option>
				<option value="company_admins">Company for admins</option>
				<option value="user_own">Own of the users</option>
			</select>
			<select class="llm-task-provider"></select><input class="llm-task-model"><button class="llm-models-button">Models</button>
		</div>
	</form>`;

function disabled(document)
{
	return [...document.querySelectorAll('.llm-task-provider, .llm-task-model, .llm-models-button')].map(field => field.disabled);
}

Deno.test('a provider is deleted only after the confirmation', () =>
{
	for (const [answer, prevented] of [[true, false], [false, true]])
	{
		using dom         = createDom(PAGE);
		using confirmStub = stub(globalThis, 'confirm', () => answer);
		LlmSettingsForm.attachTo(dom.document);
		const event = new Event('click', {cancelable: true});

		dom.document.querySelector('.llm-delete-button').dispatchEvent(event);

		assertEquals(confirmStub.calls[0].args, ['Delete the provider?']);
		assertEquals(event.defaultPrevented, prevented);
	}
});

Deno.test('a task asks for provider and model only while the company provides the AI', async (t) =>
{
	const cases = {none: true, company_all: false, company_admins: false, user_own: true};

	for (const [mode, isDisabled] of Object.entries(cases))
	{
		await t.step(mode, () =>
		{
			using dom = createDom(PAGE);
			const select = dom.document.querySelector('.llm-task-mode');
			LlmSettingsForm.attachTo(dom.document);

			select.value = mode;
			select.dispatchEvent(new Event('change'));

			assertEquals(disabled(dom.document), [isDisabled, isDisabled, isDisabled]);
		});
	}
});

Deno.test('the fields follow the stored mode at once', () =>
{
	using dom = createDom(PAGE);
	dom.document.querySelector('.llm-task-mode').value = 'company_all';

	LlmSettingsForm.attachTo(dom.document);

	assertEquals(disabled(dom.document), [false, false, false]);
});
