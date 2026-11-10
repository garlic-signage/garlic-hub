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
import {replaceGlobals}               from "@support/globals.js";
import {ContextMenuView}              from "@js/templates/canvas-composer/Views/ContextMenuView.js";

/**
 * The context menu of templates/templates/canvas.mustache: the entries of the AI only with text correction,
 * the lock only for templates with selectable objects. The entry to toggle "selectable" is commented out there.
 */
function page({textCorrection = true, selectable = true} = {})
{
	return `
	<ul id="composerContextMenu" class="context-menu">
		<li id="replaceImage" class="bi-image"> Replace image</li>
		<li id="cropImage" class="bi-crop"> Crop image</li>
		${textCorrection ? '<li id="suggestImage" class="bi-stars"> Suggest image</li>' : ''}
		<li id="duplicate" class="bi-copy">Duplicate</li>
		<li id="delete" class="bi-trash">Delete</li>
		${textCorrection ? `
		<li id="correctText" class="bi-spellcheck"> Correct text</li>
		<li id="shortenText" class="bi-arrows-collapse"> Shorten text</li>
		<li id="translateText" class="bi-translate context-menu-parent"> Translate
			<ul class="context-submenu">
				<li data-locale="de">German</li>
				<li data-locale="fr">French</li>
			</ul>
		</li>` : ''}
		${selectable ? '<li><hr /></li><li id="toggleLock"><span id="lock" class="bi-lock"> Lock</span><span id="unlock" class="bi-unlock"> Unlock</span></li>' : ''}
		<li><hr /></li>
		<li id="moveBackground" class="bi-back">Background</li>
		<li id="moveBack" class="bi-arrow-down">Back</li>
		<li id="moveFront" class="bi-arrow-up">Front</li>
		<li id="moveForeground" class="bi-front">Foreground</li>
	</ul>`;
}

/**
 * linkedom has no layout: the window is 1000 x 800, the menu 200 x 300.
 */
function createMenu(options = {})
{
	const dom     = createDom(page(options));
	const globals = replaceGlobals({window: {innerWidth: 1000, innerHeight: 800}});
	const menu    = dom.document.getElementById('composerContextMenu');
	menu.getBoundingClientRect = () => ({left: 0, top: 0, width: 200, height: 300});

	return {
		document: dom.document, menu, view: new ContextMenuView(),
		byId: (id) => dom.document.getElementById(id),
		[Symbol.dispose]: () => { globals[Symbol.dispose](); dom[Symbol.dispose](); }
	};
}

Deno.test('the menu floats above the canvas, hidden at first, without the menu of the browser', () =>
{
	using page = createMenu();

	assertEquals([page.menu.style.position, String(page.menu.style.zIndex), page.menu.style.display], ['fixed', '1000', 'none']);
	const event = new Event('contextmenu', {cancelable: true});
	page.menu.dispatchEvent(event);
	assertEquals(event.defaultPrevented, true);
});

Deno.test('the menu is shown where the mouse is', () =>
{
	using page = createMenu();

	page.view.show(120, 80);

	assertEquals([page.menu.style.display, page.menu.style.visibility], ['block', 'visible']);
	assertEquals([page.menu.style.left, page.menu.style.top], ['120px', '80px']);
});

Deno.test('at the right and the bottom edge the menu stays inside the window', () =>
{
	using page = createMenu();

	page.view.show(900, 700);

	// 1000 - 200 and 800 - 300
	assertEquals([page.menu.style.left, page.menu.style.top], ['800px', '500px']);
});

Deno.test('the menu is hidden again', () =>
{
	using page = createMenu();
	page.view.show(10, 10);

	page.view.hide();

	assertEquals(page.menu.style.display, 'none');
});

Deno.test('lock or unlock is offered', () =>
{
	using page = createMenu();

	page.view.showLocked();
	assertEquals([page.byId('lock').style.display, page.byId('unlock').style.display], ['inline', 'none']);

	page.view.showUnLocked();
	assertEquals([page.byId('lock').style.display, page.byId('unlock').style.display], ['none', 'inline']);
});

Deno.test('without selectable objects there is no lock, the entry of selectable is not in the template', () =>
{
	using page = createMenu({selectable: false});

	assertSameElement(page.view.toggleLock, null);
	assertSameElement(page.view.toggleSelectable, null);
});

Deno.test('the crop is offered for images only', () =>
{
	using page = createMenu();

	page.view.showCropImage(false);
	assertEquals(page.byId('cropImage').style.display, 'none');

	page.view.showCropImage(true);
	assertEquals(page.byId('cropImage').style.display, 'block');
});

Deno.test('replace and suggest an image are shown and hidden together', () =>
{
	using page = createMenu();

	page.view.hideReplaceImage();
	assertEquals([page.byId('replaceImage').style.display, page.byId('suggestImage').style.display], ['none', 'none']);

	page.view.showReplaceImage();
	assertEquals([page.byId('replaceImage').style.display, page.byId('suggestImage').style.display], ['block', 'block']);
});

Deno.test('without the AI only the replace of an image is shown and hidden', () =>
{
	using page = createMenu({textCorrection: false});

	page.view.hideReplaceImage();
	assertEquals(page.byId('replaceImage').style.display, 'none');
	page.view.showReplaceImage();
	assertEquals(page.byId('replaceImage').style.display, 'block');

	assertSameElement(page.view.suggestImage, null);
});

Deno.test('the entries of the AI for texts are shown and hidden together', () =>
{
	using page = createMenu();
	const ids  = ['correctText', 'shortenText', 'translateText'];

	page.view.showCorrectText(false);
	assertEquals(ids.map(id => page.byId(id).style.display), ['none', 'none', 'none']);

	page.view.showCorrectText(true);
	assertEquals(ids.map(id => page.byId(id).style.display), ['', '', '']);
});

Deno.test('the languages of the translation come with their locale', () =>
{
	using page = createMenu();

	assertEquals(page.view.translationLanguages.map(entry => [entry.dataset.locale, entry.textContent]), [['de', 'German'], ['fr', 'French']]);
});

Deno.test('without the AI there are no entries for texts and no languages', () =>
{
	using page = createMenu({textCorrection: false});

	page.view.showCorrectText(true);

	assertSameElement(page.view.correctText, null);
	assertSameElement(page.view.shortenText, null);
	assertEquals(page.view.translationLanguages, []);
});

Deno.test('the entries are read from the page', () =>
{
	using page = createMenu();
	const entries = {
		replaceImage: 'replaceImage', cropImage: 'cropImage', suggestImage: 'suggestImage', duplicate: 'duplicate',
		delete: 'delete', correctText: 'correctText', shortenText: 'shortenText', toggleLock: 'toggleLock',
		moveBackground: 'moveBackground', moveBack: 'moveBack', moveFront: 'moveFront', moveForeground: 'moveForeground'
	};

	for (const [getter, id] of Object.entries(entries))
		assertSameElement(page.view[getter], page.byId(id), getter);
});
