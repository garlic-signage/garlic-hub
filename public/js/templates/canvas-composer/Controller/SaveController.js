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

export class SaveController
{
	#saveView;
	#composerContext;
	#saveService;
	#loadService;
	#viewportService;
	#redirectUrl;

	constructor(saveView, composerContext, saveService, loadService, viewportService)
	{
		this.#saveView = saveView;
		this.#composerContext = composerContext;
		this.#saveService = saveService;
		this.#loadService = loadService;
		this.#viewportService = viewportService;

		const isPlaylist = this.#composerContext.itemId !== 0;

		if (isPlaylist)
			this.#redirectUrl = "/playlists/compose/" + this.#composerContext.playlistId;
		else
			this.#redirectUrl = "/templates/";


		this.#saveView.saveButton.addEventListener('click', () =>
		{
			this.#saveService.validateImageData(this.#saveView.getExportFormatValue(),  this.#saveView.getExportQualityValue());

			if (isPlaylist)
				this.#saveService.save(this.#viewportService.width, this.#viewportService.height, true, this.#composerContext.itemId);
			else
				this.#saveService.save(this.#viewportService.width, this.#viewportService.height, false, this.#composerContext.templateId);
		});
		ComposerEventBus.addEventListener("setChanged", (e) =>
		{
			this.#saveView.setSaveNotify();
		})
		ComposerEventBus.addEventListener("resetChanged", (e) =>
		{
			this.#saveView.unsetSaveNotify();
		})
		this.#saveView.resetButton?.addEventListener('click', () =>
		{
			this.#loadService.resetFromTemplateDataBase(this.#composerContext.templateId);
		});
		this.#saveView.exportFormat?.addEventListener('change', () =>
		{
			if (this.#saveView.getExportFormatValue() === 'bmp')
				this.#saveView.hideExportQuality();
			else
				this.#saveView.showExportQuality();
		});
		this.#saveView.closeButton.addEventListener('click', () =>
		{
			if (this.#saveService.hasChanged() && confirm(this.#composerContext.getLangByKey('confirm_close')) === false)
				return;

			window.location.href = this.#redirectUrl;

		});
	}
}
