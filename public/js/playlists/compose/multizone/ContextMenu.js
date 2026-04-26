/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2025 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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
import './CanvasView.js';
export class ContextMenu
{
	MyCanvasView = {};
	options;
	context_menu;

	constructor(MyCanvasView)
	{
		this.MyCanvasView = MyCanvasView;
	}

	show(options)
	{
		if (options.target.getType() !== "LabeledZone" )
			return;

		this.build(options)
	}

	build(options)
	{
		this.options = options;

		this.context_menu = document.createElement("div");
		this.context_menu.style.position = "absolute";
		this.context_menu.style.zIndex = 1000;
		this.context_menu.style.left = this.options.e.pageX + "px";
		this.context_menu.style.top = this.options.e.pageY + "px";
		this.context_menu.innerHTML = document.getElementById("context-menu").innerHTML;
		this.context_menu.addEventListener('contextmenu', e => e.preventDefault());
		document.body.append(this.context_menu);
	}

	remove()
	{
		if (this.context_menu !== undefined)
			this.context_menu.remove();
	}
}
