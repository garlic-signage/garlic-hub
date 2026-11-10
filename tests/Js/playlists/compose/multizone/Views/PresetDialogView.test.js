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
import {PresetDialogView}             from "@js/playlists/compose/multizone/Views/PresetDialogView.js";

const LABELS  = {title: 'Presets', close: 'Close'};
const PRESETS = [
	{name: 'Main and side', zones: [
		{left: 0, top: 0, width: 75, height: 100, bgcolor: '#111111'},
		{left: 75, top: 0, width: 25, height: 100, bgcolor: '#222222'},
	]},
	{name: 'Full', zones: [{left: 0, top: 0, width: 100, height: 100, bgcolor: '#333333'}]},
];

function dialogs(document)
{
	return [...document.querySelectorAll('dialog.multizone-presets')];
}

function openDialog(document, width = 1920, height = 1080, onSelect = () => {})
{
	const view = new PresetDialogView();
	view.open(PRESETS, width, height, LABELS, onSelect);
	return view;
}

Deno.test('the button to open the presets is read from the page', () =>
{
	using dom = createDom('<button id="open_presets"></button>');

	assertSameElement(new PresetDialogView().openButton, dom.document.getElementById('open_presets'));
});

Deno.test('the dialog shows its title, a close button and a tile per preset', () =>
{
	using dom = createDom();

	openDialog(dom.document);

	const [dialog] = dialogs(dom.document);
	assertEquals(dialog.hasAttribute('open'), true);
	assertEquals(dialog.querySelector('header span').textContent, 'Presets');
	assertEquals(dialog.querySelector('header button').getAttribute('aria-label'), 'Close');
	const tiles = [...dialog.querySelectorAll('.multizone-presets-list li button')];
	assertEquals(tiles.map(tile => [tile.title, tile.querySelector('span').textContent]), [['Main and side', 'Main and side'], ['Full', 'Full']]);
});

Deno.test('the preview draws the zones in percent', () =>
{
	using dom = createDom();

	openDialog(dom.document);

	const svg = dialogs(dom.document)[0].querySelector('svg');
	assertEquals([svg.getAttribute('viewBox'), svg.getAttribute('preserveAspectRatio')], ['0 0 100 100', 'none']);
	const rects = [...svg.querySelectorAll('rect')].map(rect =>
		['x', 'y', 'width', 'height', 'fill'].map(name => rect.getAttribute(name)));
	assertEquals(rects, [['0', '0', '75', '100', '#111111'], ['75', '0', '25', '100', '#222222']]);
});

Deno.test('the preview has the aspect ratio of the screen, the longer side is 120', () =>
{
	const cases = [[1920, 1080, '120', '68'], [1080, 1920, '68', '120'], [1000, 1000, '120', '120'], [0, 0, '120', '68']];

	for (const [width, height, svgWidth, svgHeight] of cases)
	{
		using dom = createDom();
		openDialog(dom.document, width, height);

		const svg = dialogs(dom.document)[0].querySelector('svg');
		assertEquals([svg.getAttribute('width'), svg.getAttribute('height')], [svgWidth, svgHeight], `${width} x ${height}`);
	}
});

Deno.test('a tile tells its preset', () =>
{
	using dom    = createDom();
	const chosen = [];
	openDialog(dom.document, 1920, 1080, (preset) => chosen.push(preset));

	dialogs(dom.document)[0].querySelectorAll('.multizone-presets-list button')[1].dispatchEvent(new Event('click'));

	assertEquals(chosen.length, 1);
	assertEquals(chosen[0] === PRESETS[1], true);
});

Deno.test('closing removes the dialog, opening again replaces it', () =>
{
	using dom  = createDom();
	const view = openDialog(dom.document);

	view.open(PRESETS, 1920, 1080, LABELS, () => {});
	assertEquals(dialogs(dom.document).length, 1);

	view.close();
	assertEquals(dialogs(dom.document), []);
});

Deno.test('the close button closes the dialog, closing without a dialog does nothing', () =>
{
	using dom = createDom();
	new PresetDialogView().close();
	openDialog(dom.document);

	dialogs(dom.document)[0].querySelector('header button').dispatchEvent(new Event('click'));

	assertEquals(dialogs(dom.document), []);
});
