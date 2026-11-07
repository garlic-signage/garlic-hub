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
import {LlmModelsService} from "./LlmModelsService.js";
import {FetchClient}      from "./FetchClient.js";

/**
 * The models of an AI provider (templates/generic/llm_settings.mustache). In the row of a task the server asks the
 * chosen provider, a click takes a model into the field of the task. In the form of a provider it is the test of
 * the connection: the server asks the address of the form, the dialog only lists the models. When the address has
 * no list, the dialog tells why and the model is entered by hand.
 */
export class LlmModelsDialog
{
	#button  = null;
	#service = null;
	#dialog  = null;

	constructor(button, service)
	{
		this.#button  = button;
		this.#service = service;
	}

	static attachTo(container)
	{
		for (const button of container.querySelectorAll(".llm-models-button"))
		{
			const dialog = new LlmModelsDialog(button, new LlmModelsService(new FetchClient()));
			button.addEventListener("click", () => dialog.open());
		}
	}

	async open()
	{
		this.#button.disabled = true;
		const result = await this.#service.load(this.#button.dataset.action, this.#collectProvider());
		this.#button.disabled = false;

		this.#dialog?.remove();
		this.#dialog = this.#build(result);
		document.body.appendChild(this.#dialog);
		this.#dialog.showModal();
	}

	#collectProvider()
	{
		const providerField = this.#button.dataset.connectionField;
		if (providerField !== undefined)
			return {connection_id: document.getElementById(providerField).value};

		const fields = this.#button.form.elements;
		return {
			connection_id: fields.namedItem("connection_id").value,
			api_type:      fields.namedItem("api_type").value,
			base_url:      fields.namedItem("base_url").value,
			api_key:       fields.namedItem("api_key").value
		};
	}

	#build(result)
	{
		const labels = this.#button.dataset;
		const dialog = document.createElement("dialog");
		dialog.className = "llm-models";
		dialog.addEventListener("close", () => dialog.remove());

		const header = document.createElement("header");
		const title  = document.createElement("span");
		title.textContent = labels.title;
		const close = document.createElement("button");
		close.type      = "button";
		close.className = "close-button bi bi-x";
		close.setAttribute("aria-label", labels.close);
		close.addEventListener("click", () => dialog.close());
		header.append(title, close);
		dialog.appendChild(header);

		if (!result.success)
		{
			dialog.appendChild(this.#message(result.error_message, "llm-models-message llm-models-error"));
			return dialog;
		}

		if (result.models.length === 0)
		{
			dialog.appendChild(this.#message(labels.empty, "llm-models-message"));
			return dialog;
		}

		const list = document.createElement("ul");
		list.className = "llm-models-list";
		for (const model of result.models)
		{
			list.appendChild(this.#buildModel(model, dialog));
		}

		dialog.append(this.#buildFilter(list, labels.filter), list);

		return dialog;
	}

	#buildModel(model, dialog)
	{
		const item       = document.createElement("li");
		const modelField = this.#button.dataset.modelField;
		if (modelField === undefined)
		{
			item.textContent = model;
			return item;
		}

		const choose = document.createElement("button");
		choose.type        = "button";
		choose.textContent = model;
		choose.addEventListener("click", () =>
		{
			const field = document.getElementById(modelField);
			field.value = model;
			dialog.close();
			field.focus();
		});
		item.appendChild(choose);

		return item;
	}

	#buildFilter(list, placeholder)
	{
		const filter = document.createElement("input");
		filter.type        = "search";
		filter.className   = "llm-models-filter";
		filter.placeholder = placeholder;
		filter.setAttribute("aria-label", placeholder);
		filter.autofocus   = true;
		filter.addEventListener("input", () =>
		{
			const search = filter.value.trim().toLowerCase();
			for (const item of list.children)
				item.hidden = !item.textContent.toLowerCase().includes(search);
		});

		return filter;
	}

	#message(text, className)
	{
		const paragraph = document.createElement("p");
		paragraph.className   = className;
		paragraph.textContent = text;

		return paragraph;
	}
}
