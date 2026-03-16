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

export class ViewportController
{
	#viewportView;
	#viewportService
	#isAutoresize = true;

	constructor(viewportView, viewportService)
	{
		this.#viewportView = viewportView;
		this.#viewportService = viewportService;

		this.#viewportView.resolutionWidth.addEventListener('change', () =>
		{
			this.#viewportService.setCanvasDimensions(
				this.#viewportView.getResolutionWidthValue(),
				this.#viewportView.getResolutionHeightValue()
			);
			this.#viewportService.scaleCanvas(this.#viewportView.getZoomSliderValue());

		});

		this.#viewportView.resolutionHeight.addEventListener('change', () =>
		{
			this.#viewportService.setCanvasDimensions(
				this.#viewportView.getResolutionWidthValue(),
				this.#viewportView.getResolutionHeightValue()
			);
			this.#viewportService.scaleCanvas(this.#viewportView.getZoomSliderValue());
		});

		this.#viewportView.zoomSlider.addEventListener('input', () =>
		{
			this.#isAutoresize = false;
			this.scalePercent();
			this.#viewportService.scaleCanvas(this.#viewportView.getZoomSliderValue());
		});
		ComposerEventBus.addEventListener("canvasUpdated", (e) => {
			this.initializeCanvas();
		});

		// we should stop autoresize zoom when user uses zoom manually.
		window.addEventListener('resize', () => {
			if (this.#isAutoresize)
				this.zoomToViewPort();
		});
	}

	initializeCanvas()
	{
		this.#viewportService.initializeFromCanvas()
		this.zoomToViewPort();
	}

	zoomToViewPort()
	{
		const browserWidth = document.documentElement.clientWidth - 180;
		const browserHeight = document.documentElement.clientHeight - 180;
		const zoom          = this.#viewportService.calculateZoomByBrowserViewPort(browserWidth, browserHeight);

		this.#viewportView.setZoomSliderValue(zoom);

		this.scalePercent();
		this.#viewportService.scaleCanvas(this.#viewportView.getZoomSliderValue());
	}

	scalePercent()
	{
		this.#viewportView.setZoomPercentHtml(this.#viewportView.getZoomSliderValue() + ' %');
	}



}
