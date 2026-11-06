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

/**
 * Opens the dialog of the presets and puts the chosen one on the canvas. Existing zones are replaced
 * only after a question, nothing is saved before the button save.
 */
export class PresetController
{
	#view;
	#presetService;
	#viewportService;
	#propertiesController;
	#fabricWrapper;

	constructor(view, presetService, viewportService, propertiesController, fabricWrapper, presets)
	{
		this.#view                 = view;
		this.#presetService        = presetService;
		this.#viewportService      = viewportService;
		this.#propertiesController = propertiesController;
		this.#fabricWrapper        = fabricWrapper;

		this.#view.openButton.addEventListener("click", () =>
			this.#view.open(
				presets,
				Number(this.#viewportService.width),
				Number(this.#viewportService.height),
				{title: lang["presets"], close: lang["close"]},
				(preset) => this.#select(preset)
			)
		);
	}

	#select(preset)
	{
		if (this.#presetService.hasZones() && !confirm(lang["confirm_preset"]))
			return;

		this.#view.close();
		this.#presetService.apply(preset);
		this.#propertiesController.deactivate();
		this.#fabricWrapper.renderAll();
		this.#fabricWrapper.changed();
	}
}
