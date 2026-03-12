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
'use strict';

import {ComposerEventBus} from "../Utils/ComposerEventBus.js";

export class LoadService
{
	#fabricWrapper;
	#templatesService;
	#fontCollector;
	#waitOverlay;

	constructor(fabricWrapper, templatesService, fontCollector, waitOverlay)
	{
		this.#fabricWrapper    = fabricWrapper;
		this.#templatesService = templatesService;
		this.#fontCollector    = fontCollector;
		this.#waitOverlay      = waitOverlay;
	}


	async resetFromTemplateDataBase(templateId)
	{
		this.#fabricWrapper.clear();
		await this.loadFromTemplateDataBase(templateId);
	}

	async loadFromTemplateDataBase(templateId)
	{
		this.#waitOverlay.start();

		const jsonResponse = await this.#templatesService.loadTemplateContent(templateId);
		await this.#loadJsonFromString(jsonResponse.content);
		this.#waitOverlay.stop();
	}

	async loadFromPlaylistItemDataBase(itemId)
	{
		this.#waitOverlay.start();

		const jsonResponse = await this.#templatesService.loadPlaylistItemContent(itemId);
		await this.#loadJsonFromString(jsonResponse.content);

		this.#waitOverlay.stop();
	}

	async #loadJsonFromString(jsonContent)
	{
		if (jsonContent.length === 0)
			jsonContent = "{\"objects\": [],\"viewport\":{\"width\":1920,\"height\":1080,\"scale\":100}}";

		let j = JSON.parse(jsonContent);
		this.#fontCollector.traverseObjects(j.objects);
		console.log("Collected all fonts");

		await this.#fontCollector.preloadUsedFonts();

		await this.#fabricWrapper.load(jsonContent);
		this.#fabricWrapper.setWidth(j.viewport.width);
		this.#fabricWrapper.setHeight(j.viewport.height);
		this.#fabricWrapper.historySaveAction();
		this.#fabricWrapper.resetChange();

		ComposerEventBus.dispatchEvent(new CustomEvent("canvasUpdated"));
	}
}
