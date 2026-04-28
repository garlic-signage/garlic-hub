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

export class ShadowPropertiesController
{
	#shadowPropertiesView;
	#shadowPropertiesService;


	constructor(shadowPropertiesView, shadowPropertiesService)
	{
		this.#shadowPropertiesView = shadowPropertiesView;
		this.#shadowPropertiesService = shadowPropertiesService;

		this.#shadowPropertiesView.createShadow.addEventListener("click", () =>
		{
			this.#shadowPropertiesService.create();
			this.activate();
		});
		this.#shadowPropertiesView.deleteShadow.addEventListener("click", () =>
		{
			this.#shadowPropertiesService.delete();
			this.activate();
		});
		this.#shadowPropertiesView.shadowColor.addEventListener("click", () =>
		{
			this.#shadowPropertiesService.setColor(this.#shadowPropertiesView.getShadowColorValue());
		});
		this.#shadowPropertiesView.shadowOffsetX.addEventListener("input", () =>
		{
			this.#shadowPropertiesService.setOffsetX(this.#shadowPropertiesView.getShadowOffsetXValue());
		});
		this.#shadowPropertiesView.shadowOffsetY.addEventListener("input", () =>
		{
			this.#shadowPropertiesService.setOffsetY(this.#shadowPropertiesView.getShadowOffsetYValue());
		});
		this.#shadowPropertiesView.shadowBlur.addEventListener("input", () =>
		{
			this.#shadowPropertiesService.setBlur(this.#shadowPropertiesView.getShadowBlurValue());
		});

	}

	activate()
	{
		this.#shadowPropertiesView.setShadowColorValue(this.#shadowPropertiesService.getColor());
		this.#shadowPropertiesView.setShadowOffsetXValue(this.#shadowPropertiesService.getOffsetX());
		this.#shadowPropertiesView.setShadowOffsetYValue(this.#shadowPropertiesService.getOffsetX());
		this.#shadowPropertiesView.setShadowBlurValue(this.#shadowPropertiesService.getBlur());

		this.#shadowPropertiesView.toggleShadowVisibility(this.#shadowPropertiesService.hasShadow());
	}
}
