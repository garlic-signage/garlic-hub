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

import {ComposerEventBus} from "../Utils/ComposerEventBus.js";

export class PropertiesController
{
	#globalPropertiesController;
	#groupPropertiesController;
	#selectivePropertiesController;
	#textPropertiesController;
	#currentType = ""

	constructor(globalPropertiesController, /*groupPropertiesController, selectivePropertiesController, textPropertiesController*/)
	{
		this.#globalPropertiesController = globalPropertiesController;
	/*	this.#groupPropertiesController  = groupPropertiesController;
		this.#selectivePropertiesController = selectivePropertiesController;
		this.#textPropertiesController = textPropertiesController;

	 */
		ComposerEventBus.addEventListener('mouseLeftUp', (e) =>
		{
			const object = e.detail.target;
			if (object)
				this.activateCurrent(object);
			else
				this.deactivateAllProperties();
		});

	}

	activateCurrent(object)
	{
		this.#currentType = object.type;
		switch (this.#currentType)
		{
			case "group":
			case "activeSelection":
				// this.#groupPropertiesController.activate();
				break;
			case "text":
			case "i-text":
			case "textbox":
			//	this.#selectivePropertiesController.activateFillColor();
				this.#globalPropertiesController.activate();
			//	this.#textPropertiesController.activate();
				break;
			case "circle":
			case "rect":
			case "triangle":
			case "polygon":
			//	this.#selectivePropertiesController.activateFillColor();
				this.#globalPropertiesController.activate();
				break;
			case "image":
				this.#globalPropertiesController.activate();
				break;
			default:
				break;
		}
	}

	deactivatePrevious(new_current)
	{
		if (this.#currentType === "")
			return;
		this.deactivateAllProperties();
	}

	deactivateAllProperties()
	{
		this.#globalPropertiesController.deactivate();
	//	this.#groupPropertiesController.deactivate();
	//	this.#selectivePropertiesController.deactivateAll();
	//	this.#textPropertiesController.deactivate();
	}
}