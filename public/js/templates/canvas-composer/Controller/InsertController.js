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

export class InsertController
{
	#insertView;
	#insertService;

	constructor(insertView, insertService)
	{
		this.#insertView = insertView;
		this.#insertService = insertService;

		this.#insertView.insertImage.addEventListener("click", () =>
		{
			ComposerEventBus.dispatchEvent(new CustomEvent("openMediaDialogForInsert"));
		});

		this.#insertView.insertText.addEventListener("click", () =>
		{
			this.#insertService.insertText();
		});

		this.#insertView.insertCircle.addEventListener("click", () =>
		{
			this.#insertService.insertCircle();
		});

		this.#insertView.insertTriangle.addEventListener("click", () =>
		{
			this.#insertService.insertTriangle();
		});

		this.#insertView.insertRectangle.addEventListener("click", () =>
		{
			this.#insertService.insertRectangle();
		});

		this.#insertView.insertPolygon.addEventListener("click", () =>
		{
			this.#insertService.insertRegularPolygon(5);
		});

		this.#insertView.insertHexagon.addEventListener("click", () =>
		{
			this.#insertService.insertRegularPolygon(6);
		});

		this.#insertView.insertOctagon.addEventListener("click", () =>
		{
			this.#insertService.insertRegularPolygon(8);
		});

	}
}
