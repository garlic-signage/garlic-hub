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

export class SelectivePropertiesController
{
	#selectivePropertiesView;
	#selectivePropertiesService;

	constructor(selectivePropertiesView, selectivePropertiesService)
	{
		this.#selectivePropertiesView = selectivePropertiesView;
		this.#selectivePropertiesService = selectivePropertiesService;

		this.#selectivePropertiesView.fillColor.addEventListener("input", () =>
		{
			this.#selectivePropertiesService.setFillColor(this.#selectivePropertiesView.getfillColorValue())
		});
		this.#selectivePropertiesView.fillColor.addEventListener("change", () =>
		{
			this.#selectivePropertiesService.setFillColor(this.#selectivePropertiesView.getfillColorValue())
		});

	}

	activate(object)
	{
		this.#selectivePropertiesView.setFillColorValue(this.#selectivePropertiesService.getFillColor(object));
		this.#selectivePropertiesView.show();
	}

	deactivateAll()
	{
		this.deactivate();
	}

	deactivate()
	{
		this.#selectivePropertiesView.hide();
	}





}
