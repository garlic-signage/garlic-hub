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
import {ZoneListView} from "@js/playlists/compose/multizone/Views/ZoneListView.js";

const PAGE = '<fieldset id="zones_container"><legend>Zones</legend></fieldset>';

function entries(document)
{
	return [...document.querySelectorAll('#zones_container li')].map(item => [item.id, item.textContent, item.classList.contains('active')]);
}

Deno.test('the zones are listed in one list with their id', () =>
{
	using dom  = createDom(PAGE);
	const view = new ZoneListView();

	view.add('id-1', 'Lobby', () => {});
	view.add('id-2', 'Hall', () => {});

	assertEquals(dom.document.querySelectorAll('#zones_container ul').length, 1);
	assertEquals(dom.document.querySelector('#zones_container ul').id, 'zones_list');
	assertEquals(entries(dom.document), [['id-1', 'Lobby', false], ['id-2', 'Hall', false]]);
});

Deno.test('a click on an entry tells its id', () =>
{
	using dom    = createDom(PAGE);
	const view   = new ZoneListView();
	const clicks = [];
	view.add('id-1', 'Lobby', (id) => clicks.push(id));
	view.add('id-2', 'Hall', (id) => clicks.push(id));

	dom.document.getElementById('id-2').dispatchEvent(new Event('click'));

	assertEquals(clicks, ['id-2']);
});

Deno.test('only one entry is highlighted', () =>
{
	using dom  = createDom(PAGE);
	const view = new ZoneListView();
	view.add('id-1', 'Lobby', () => {});
	view.add('id-2', 'Hall', () => {});

	view.highlight('id-1');
	view.highlight('id-2');
	assertEquals(entries(dom.document).map(entry => entry[2]), [false, true]);

	view.highlight('id-unknown');
	assertEquals(entries(dom.document).map(entry => entry[2]), [false, false]);
});

Deno.test('an entry is renamed and removed, an unknown id changes nothing', () =>
{
	using dom  = createDom(PAGE);
	const view = new ZoneListView();
	view.add('id-1', 'Lobby', () => {});
	view.add('id-2', 'Hall', () => {});

	view.rename('id-1', 'Entrance');
	view.rename('id-unknown', 'Nothing');
	view.remove('id-2');
	view.remove('id-unknown');

	assertEquals(entries(dom.document), [['id-1', 'Entrance', false]]);
});
