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

export class ItemProperties
{
	MyGlobalProperties = {};
	MyGroupProperties = {};
	MySelectiveProperties = {};
	MyTextProperties = {};
	current = ""

	constructor(Global, Group, Selective, Text)
	{
		this.MyGlobalProperties = Global;
		this.MyGroupProperties  = Group;
		this.MySelectiveProperties = Selective;
		this.MyTextProperties = Text;
	}

	activateCurrent(object)
	{
		this.current = object.type;
		switch (object.type)
		{
			case "group":
			case "activeSelection":
				this.MyGroupProperties.activate(object);
				break;
			case "text":
			case "i-text":
			case "textbox":
				this.MySelectiveProperties.activateFillColor(object);
				this.MyGlobalProperties.activate(object);
				this.MyTextProperties.activate(object);
				break;
			case "circle":
			case "rect":
			case "triangle":
			case "polygon":
				this.MySelectiveProperties.activateFillColor(object);
				this.MyGlobalProperties.activate(object);
				break;
			case "image":
				this.MyGlobalProperties.activate(object);
				break;
			default:
				break;
		}
	}

	deactivatePrevious(new_current)
	{
		if (this.current === "")
			return;
		this.deactivateAllProperties();
	}

	deactivateAllProperties()
	{
		this.MyGlobalProperties.deactivate();
		this.MyGroupProperties.deactivate();
		this.MySelectiveProperties.deactivateAll();
		this.MyTextProperties.deactivate();
	}

	initEventListener(MyCanvasView)
	{
		setInterval(() =>
		{
			MyCanvasView.renderCanvas();
		}, 250)

		this.MyGlobalProperties.initEventListener();
		this.MyGroupProperties.initEventListener();
		this.MySelectiveProperties.initEventListener();
		this.MyTextProperties.initEventListener();
	}
}