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
import {assertEquals} from "@std/assert";
import {createDom}    from "@support/dom.js";
import {WaitOverlay}  from "@js/core/WaitOverlay.js";

Deno.test('the overlay lies over the page while it waits', () =>
{
	using dom = createDom();
	const overlay = new WaitOverlay();

	overlay.start();
	assertEquals(dom.document.querySelectorAll('body > #waitOverlay').length, 1);

	overlay.stop();
	assertEquals(dom.document.getElementById('waitOverlay'), null);
});

Deno.test('a second start keeps one overlay, a stop without start is no error', () =>
{
	using dom = createDom();
	const overlay = new WaitOverlay();

	overlay.stop();
	overlay.start();
	overlay.start();

	assertEquals(dom.document.querySelectorAll('#waitOverlay').length, 1);
});
