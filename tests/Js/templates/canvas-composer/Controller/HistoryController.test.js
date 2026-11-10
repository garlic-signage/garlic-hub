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

import {assertEquals}                     from "@std/assert";
import {element, fire, FakeFabricWrapper} from "@support/ComposerControllerFakes.js";
import {HistoryController}                from "@js/templates/canvas-composer/Controller/HistoryController.js";

Deno.test('the buttons undo and redo the last step', () =>
{
	const view    = {undo: element(), redo: element()};
	const wrapper = new FakeFabricWrapper();
	new HistoryController(view, wrapper);

	fire(view.undo, 'click');
	fire(view.redo, 'click');
	fire(view.undo, 'click');

	assertEquals(wrapper.calls, [['undo'], ['redo'], ['undo']]);
});
