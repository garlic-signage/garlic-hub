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

import {assertSameElement, createDom} from "@support/dom.js";
import {ToolbarView}                  from "@js/playlists/compose/multizone/Views/ToolbarView.js";

Deno.test('the buttons of the toolbar and the canvas are read from the page', () =>
{
	using dom = createDom(`<button id="add_zone"></button><button id="save_zones"></button>
		<button id="close_zones_editor"></button><li id="canvas_wrap" tabIndex="-1"></li>`);

	const view = new ToolbarView();

	assertSameElement(view.addZone, dom.document.getElementById('add_zone'));
	assertSameElement(view.save, dom.document.getElementById('save_zones'));
	assertSameElement(view.close, dom.document.getElementById('close_zones_editor'));
	assertSameElement(view.canvasWrap, dom.document.getElementById('canvas_wrap'));
});
