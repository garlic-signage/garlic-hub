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

export class GlobalPropertiesController
{
	#globalPropertiesView;
	#globalPropertiesService;

	constructor(globalPropertiesView, globalPropertiesService)
	{
		this.#globalPropertiesView = globalPropertiesView;
		this.#globalPropertiesService = globalPropertiesService;
		this.#initEventListener();
	}

	activate()
	{
		this.#globalPropertiesView.setStrokeColorValue(this.#globalPropertiesService.getStrokeColor())
		this.#globalPropertiesView.setStrokeWidthValue(this.#globalPropertiesService.getStrokeWidth());
		this.#globalPropertiesView.setOpacityValue(this.#globalPropertiesService.getOpacity());
		this.#globalPropertiesView.setScaleXValue(this.#globalPropertiesService.getScaleX());
		this.#globalPropertiesView.setScaleYValue(this.#globalPropertiesService.getScaleY());

		this.#globalPropertiesView.showGlobalProperties();
	}

	deactivate()
	{
		this.#globalPropertiesView.hideGlobalProperties();
	}

	#initEventListener()
	{
		this.#globalPropertiesView.opacity.addEventListener("change", (event) =>
		{
			const value = parseInt(this.#globalPropertiesView.getOpacityValue());
			this.#globalPropertiesService.setOpacity(value);
			this.#globalPropertiesView.setOpacityValue(value);
		})
		this.#globalPropertiesView.strokeColor.addEventListener("input", (event) =>
		{
			this.#globalPropertiesService.setStrokeColor(event.target.value)
		});
		this.#globalPropertiesView.strokeColor.addEventListener("change", (event) =>
		{
			this.#globalPropertiesService.setStrokeColor(event.target.value)
		});
		this.#globalPropertiesView.strokeWidth.addEventListener("change", (event) =>
		{
			const value = parseInt(this.#globalPropertiesView.getStrokeWidthValue());
			this.#globalPropertiesService.setStrokeWidth(value);
			this.#globalPropertiesView.setStrokeWidthValue(value);
		})
		this.#globalPropertiesView.alignLeft.addEventListener("click", () =>
		{
			this.#globalPropertiesService.setPosition("left")
		});
		this.#globalPropertiesView.alignCenter.addEventListener("click", () =>
		{
			this.#globalPropertiesService.setPosition("center")
		});
		this.#globalPropertiesView.alignRight.addEventListener("click", () =>
		{
			this.#globalPropertiesService.setPosition("right")
		});
		this.#globalPropertiesView.alignTop.addEventListener("click", () =>
		{
			this.#globalPropertiesService.setPosition("top")
		});
		this.#globalPropertiesView.alignMiddle.addEventListener("click", () =>
		{
			this.#globalPropertiesService.setPosition("middle")
		});
		this.#globalPropertiesView.alignBottom.addEventListener("click", () =>
		{
			this.#globalPropertiesService.setPosition("bottom")
		});
		this.#globalPropertiesView.scaleX.addEventListener("input", () =>
		{
			const value = parseFloat(this.#globalPropertiesView.getScaleXValue());
			this.#globalPropertiesService.setScaleX(value);
		});
		this.#globalPropertiesView.scaleY.addEventListener("input", () =>
		{
			const value = parseFloat(this.#globalPropertiesView.getScaleYValue());
			this.#globalPropertiesService.setScaleY(value);
		});

	}

}
