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

import {assertEquals}                 from "@std/assert";
import {assertSameElement, createDom} from "@support/dom.js";
import {SaveView}                     from "@js/templates/canvas-composer/Views/SaveView.js";

/**
 * The buttons of templates/templates/canvas.mustache: the formats only for a template with save formats,
 * the reset only where there is something to reset to.
 */
function page({formats = true, reset = true} = {})
{
	return `
		${formats ? `<select id="exportFormat"><option value="jpg">JPEG</option><option value="png">PNG</option></select>
		<input type="number" id="exportQuality" min="10" max="100" step="1" value="90">` : ''}
		<button id="save"><i class="bi bi-floppy"></i></button>
		${reset ? '<button id="reset"><i class="bi bi-arrow-repeat"></i></button>' : ''}
		<button id="close"><i class="bi bi-box-arrow-in-right"></i></button>`;
}

Deno.test('the buttons are read from the page', () =>
{
	using dom  = createDom(page());
	const view = new SaveView();

	for (const [getter, id] of Object.entries({saveButton: 'save', exportFormat: 'exportFormat', resetButton: 'reset', closeButton: 'close'}))
		assertSameElement(view[getter], dom.document.getElementById(id), getter);
});

Deno.test('an unsaved change is marked at the save button', () =>
{
	using dom    = createDom(page());
	const view   = new SaveView();
	const button = dom.document.getElementById('save');

	view.setSaveNotify();
	assertEquals(button.classList.contains('notify-save'), true);

	view.unsetSaveNotify();
	assertEquals(button.classList.contains('notify-save'), false);
});

Deno.test('the chosen format and quality are read', () =>
{
	using dom = createDom(page());
	dom.document.getElementById('exportFormat').value = 'png';

	const view = new SaveView();

	assertEquals([view.getExportFormatValue(), view.getExportQualityValue()], ['png', '90']);
});

Deno.test('without a choice of formats a JPEG of quality 80 is saved', () =>
{
	using _dom = createDom(page({formats: false}));

	const view = new SaveView();

	assertEquals([view.getExportFormatValue(), view.getExportQualityValue()], ['jpg', 80]);
	assertSameElement(view.exportFormat, null);
});

Deno.test('the quality is hidden and shown', () =>
{
	using dom     = createDom(page());
	const view    = new SaveView();
	const quality = dom.document.getElementById('exportQuality');

	view.hideExportQuality();
	assertEquals(quality.style.display, 'none');

	view.showExportQuality();
	assertEquals(quality.style.display, 'block');
});

Deno.test('a template without a reset has no reset button', () =>
{
	using _dom = createDom(page({reset: false}));

	assertSameElement(new SaveView().resetButton, null);
});
