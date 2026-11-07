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

/**
 * The AI forms of the account and of the company (templates/generic/llm_settings.mustache): a provider is deleted
 * only after a confirmation, and a task of the company asks for a provider and a model only while the company
 * provides the AI for it.
 */
export class LlmSettingsForm
{
	static attachTo(container)
	{
		for (const button of container.querySelectorAll(".llm-delete-button"))
		{
			button.addEventListener("click", (event) =>
			{
				if (!confirm(button.dataset.confirm))
					event.preventDefault();
			});
		}

		for (const mode of container.querySelectorAll(".llm-task-mode"))
		{
			const task = mode.closest(".llm-task");
			const update = () =>
			{
				const isCompany = mode.value === "company_all" || mode.value === "company_admins";
				for (const field of task.querySelectorAll(".llm-task-provider, .llm-task-model, .llm-models-button"))
					field.disabled = !isCompany;
			};
			mode.addEventListener("change", update);
			update();
		}
	}
}
