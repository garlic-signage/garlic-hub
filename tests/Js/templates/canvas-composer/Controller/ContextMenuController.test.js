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

import {assertEquals}                                            from "@std/assert";
import {element, fire, groupObject, imageObject, recorder, tick} from "@support/ComposerControllerFakes.js";
import {busOfTest}                                               from "@support/MultizoneEvents.js";
import {ContextMenuController}                                   from "@js/templates/canvas-composer/Controller/ContextMenuController.js";

const ENTRIES = ['replaceImage', 'cropImage', 'suggestImage', 'correctText', 'shortenText', 'duplicate', 'delete', 'toggleLock',
	'toggleSelectable', 'moveBackground', 'moveBack', 'moveFront', 'moveForeground'];

/**
 * The view of the menu with all entries; missing ones are null, as without an AI or without the rights.
 */
function createController({missing = [], locked = false, selectable = true, isText = false} = {})
{
	const view = recorder(['hide', 'show', 'showLocked', 'showUnLocked', 'showSelectable', 'showUnSelectable', 'hideReplaceImage',
		'showReplaceImage', 'showCorrectText', 'showCropImage']);
	for (const entry of ENTRIES)
		view[entry] = missing.includes(entry) ? null : element();
	view.translationLanguages = missing.includes('translationLanguages') ? [] :
		['de', 'fr'].map(locale => element({getAttribute: (name) => name === 'data-locale' ? locale : null}));

	const service = recorder(['isLocked', 'isSelectable', 'duplicate', 'remove', 'toggleLockedStatus', 'toggleSelectableStatus',
		'sendToBack', 'sendBackwards', 'bringForward', 'bringToFront'], {isLocked: locked, isSelectable: selectable});
	const texts = recorder(['isText', 'correctActiveText', 'shortenActiveText', 'translateActiveText'], {isText});
	new ContextMenuController(view, service, null, texts);

	return {view, service, texts};
}

function rightClick(bus, target, x = 120, y = 80)
{
	bus.send('mouseRightUp', {target, e: {clientX: x, clientY: y}});
}

Deno.test('a left click on the canvas closes the menu', () =>
{
	using bus  = busOfTest();
	const page = createController();

	bus.send('mouseLeftUp', {target: null});

	assertEquals(page.view.calls, [['hide']]);
});

Deno.test('a right click beside the objects closes the menu', () =>
{
	using bus  = busOfTest();
	const page = createController();

	rightClick(bus, null);

	assertEquals(page.view.calls, [['hide']]);
});

Deno.test('a group has no menu', () =>
{
	using bus  = busOfTest();
	const page = createController();

	rightClick(bus, groupObject());

	assertEquals(page.view.calls, []);
});

Deno.test('a shape opens the menu at the mouse without replace and crop', () =>
{
	using bus  = busOfTest();
	const page = createController();

	rightClick(bus, {type: 'rect'}, 300, 200);

	assertEquals(page.view.calls, [
		['showLocked'], ['showUnSelectable'], ['hideReplaceImage'], ['showCorrectText', false], ['showCropImage', false],
		['show', 300, 200]
	]);
});

Deno.test('an image can be replaced and cropped', () =>
{
	using bus  = busOfTest();
	const page = createController();

	rightClick(bus, imageObject());

	assertEquals(page.view.calls.slice(2, 5), [['showReplaceImage'], ['showCorrectText', false], ['showCropImage', true]]);
});

Deno.test('a text can be corrected', () =>
{
	using bus  = busOfTest();
	const page = createController({isText: true});
	const text = {type: 'textbox'};

	rightClick(bus, text);

	assertEquals(page.texts.calls[0], ['isText', text]);
	assertEquals(page.view.calls.find(call => call[0] === 'showCorrectText'), ['showCorrectText', true]);
});

Deno.test('the menu offers to unlock a locked object and to make an unselectable one selectable', () =>
{
	using bus  = busOfTest();
	const page = createController({locked: true, selectable: false});

	rightClick(bus, {type: 'rect'});

	assertEquals(page.view.calls.slice(0, 2), [['showUnLocked'], ['showSelectable']]);
});

Deno.test('without the entries to lock and select the state is not asked', () =>
{
	using bus  = busOfTest();
	const page = createController({missing: ['toggleLock', 'toggleSelectable']});

	rightClick(bus, {type: 'rect'});

	assertEquals(page.service.calls, []);
	assertEquals(page.view.calls[0], ['hideReplaceImage']);
});

Deno.test('replace, crop and suggest ask the composer for it and close the menu', () =>
{
	using bus  = busOfTest('openMediaDialogForReplace', 'cropImage', 'suggestImageForReplace');
	const page = createController();

	fire(page.view.replaceImage, 'click');
	fire(page.view.cropImage, 'click');
	fire(page.view.suggestImage, 'click');

	assertEquals(bus.events.map(([type]) => type), ['openMediaDialogForReplace', 'cropImage', 'suggestImageForReplace']);
	assertEquals(page.view.calls, [['hide'], ['hide'], ['hide']]);
});

Deno.test('the AI corrects, shortens and translates the text', async () =>
{
	using _bus = busOfTest();
	const page = createController();

	fire(page.view.correctText, 'click');
	fire(page.view.shortenText, 'click');
	let stopped = false;
	fire(page.view.translationLanguages[1], 'click', {stopPropagation: () => stopped = true});
	await tick();

	assertEquals(page.texts.calls, [['correctActiveText'], ['shortenActiveText'], ['translateActiveText', 'fr']]);
	assertEquals(page.view.calls, [['hide'], ['hide'], ['hide']]);
	// the entry "translate" around the language is no action of its own
	assertEquals(stopped, true);
});

Deno.test('the entries change the object and close the menu', () =>
{
	using _bus = busOfTest();
	const page = createController();
	const actions = {
		duplicate: 'duplicate', delete: 'remove', toggleLock: 'toggleLockedStatus', toggleSelectable: 'toggleSelectableStatus',
		moveBackground: 'sendToBack', moveBack: 'sendBackwards', moveFront: 'bringForward', moveForeground: 'bringToFront'
	};

	for (const entry of Object.keys(actions))
		fire(page.view[entry], 'click');

	assertEquals(page.service.calls.map(call => call[0]), Object.values(actions));
	assertEquals(page.view.calls.length, 8);
});

Deno.test('a menu without the entries of the AI and the rights works', () =>
{
	using bus  = busOfTest('cropImage');
	const page = createController({missing: ['suggestImage', 'correctText', 'shortenText', 'toggleLock', 'toggleSelectable', 'translationLanguages']});

	fire(page.view.cropImage, 'click');
	fire(page.view.duplicate, 'click');

	assertEquals(bus.events.length, 1);
	assertEquals(page.service.calls, [['duplicate']]);
});
