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
 * The buttons of the toolbar and the canvas which gets the keys.
 */
export class ToolbarView
{
	#addZone    = document.getElementById("add_zone");
	#save       = document.getElementById("save_zones");
	#close      = document.getElementById("close_zones_editor");
	#canvasWrap = document.getElementById("canvas_wrap");

	get addZone()    { return this.#addZone; }
	get save()       { return this.#save; }
	get close()      { return this.#close; }
	get canvasWrap() { return this.#canvasWrap; }
}
