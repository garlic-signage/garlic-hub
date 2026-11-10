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
import {MediaDialogView}              from "@js/templates/canvas-composer/Views/MediaDialogView.js";

// the toolbar, the dialog of the media and the canvas of templates/templates/canvas.mustache
const PAGE = `
	<dialog id="mediaSelectorDialog">
		<header>
			<span class="dialog-name"></span>
			<button id="closeEditMediaDialog" class="close-button bi bi-x" aria-label="Close"></button>
		</header>
		<section id="mediaSelectorInstance"></section>
		<footer>
			<button id="addMedia" type="button">Add</button>
			<button id="applyMedia" type="button" class="primary">Apply</button>
			<button id="closeDialogButton" type="button">Cancel</button>
		</footer>
	</dialog>
	<dialog id="imageSuggestionDialog"><header><span class="dialog-name">Suggestions</span></header></dialog>
	<ul id="canvas_toolbar">
		<li id="inserts">
			<button id="insertImage"></button><button id="insertText">Aa</button>
			<button id="insertCircle"></button><button id="insertTriangle"></button><button id="insertRectangle"></button>
			<button id="insertPolygon"></button><button id="insertHexagon"></button><button id="insertOctagon"></button>
		</li>
		<li id="undo_redo"><button id="undo"></button><button id="redo"></button></li>
	</ul>
	<div id="canvasWrap" tabIndex="1000"><canvas id="canvas"></canvas></div>`;

function assertGetters(view, document, getters)
{
	for (const [getter, id] of Object.entries(getters))
		assertSameElement(view[getter], document.getElementById(id), getter);
}

Deno.test('the dialog of the media has its selector and buttons', () =>
{
	using dom = createDom(PAGE);

	assertGetters(new MediaDialogView(), dom.document, {
		mediaSelectorElement: 'mediaSelectorInstance', closeEditMediaDialog: 'closeEditMediaDialog',
		closeDialogButton: 'closeDialogButton', mediaSelectorDialog: 'mediaSelectorDialog', addMedia: 'addMedia', applyMedia: 'applyMedia'
	});
});

Deno.test('the name of the dialog is the first one of the page', () =>
{
	using dom = createDom(PAGE);

	const view = new MediaDialogView();

	assertSameElement(view.dialogName, dom.document.querySelector('#mediaSelectorDialog .dialog-name'));
});

Deno.test('a page without the dialog has no name of it', () =>
{
	using _dom = createDom('');

	assertSameElement(new MediaDialogView().dialogName, undefined);
	assertSameElement(new MediaDialogView().mediaSelectorDialog, null);
});
