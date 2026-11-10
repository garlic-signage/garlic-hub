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
import {ExternalFileElements}         from "@js/mediapool/uploads/External/ExternalFileElements.js";

// the tab of templates/mediapool/overview.mustache
const PAGE = `
	<section class="tab-content" id="linkTab">
		<input type="url" id="externalLinkField">
		<button id="startExternalFileUpload" class="upload-button primary">Start</button>
	</section>`;

Deno.test('the elements of the tab are read from the page', () =>
{
	using dom = createDom(PAGE);

	const elements = new ExternalFileElements();

	assertSameElement(elements.externalLinkField, dom.document.getElementById('externalLinkField'));
	assertSameElement(elements.startFileUpload, dom.document.getElementById('startExternalFileUpload'));
});

Deno.test('without a page the elements are null', () =>
{
	using _dom = createDom('');

	const elements = new ExternalFileElements();

	assertEquals([elements.externalLinkField, elements.startFileUpload], [null, null]);
});
