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
import {assertEquals}   from "@std/assert";
import {EditorsManager} from "@js/playlists/compose/standard/editors/types/EditorsManager.js";

/**
 * An editor of a trigger: it keeps the data of init() and gives them back as its values.
 */
class FakeEditor
{
	id      = null;
	exists  = false;
	data    = null;
	touchableMedialist = undefined;

	init(data)
	{
		this.data   = data;
		this.exists = true;
	}

	getEditor()
	{
		return `editor ${this.id}`;
	}

	getValues()
	{
		return {...this.data, id: this.id};
	}
}

// the saved triggers of an item, as the TriggerService loads them
const TRIGGER_DATA = {
	wallclocks: [{iso_date: '2026-11-10T08:30:00'}, {iso_date: '2026-11-11T09:00:00'}],
	accesskeys: [{accesskey: 'k'}],
	touches:    [{touch_item_id: 11}],
	notifies:   [{notify: 'doorbell'}],
};

// the add and collect methods of every kind of trigger
const KINDS = {
	wallclocks: ['addWallclockEditor', 'collectWallclockValues'],
	accesskeys: ['addAccesskeyEditor', 'collectAccesskeyValues'],
	touches:    ['addTouchEditor', 'collectTouchValues'],
	notifies:   ['addNotifyEditor', 'collectNotifyValues'],
};

function createManager(triggerData = TRIGGER_DATA)
{
	const manager = new EditorsManager();
	manager.triggerData = triggerData;
	return manager;
}

Deno.test('the editors of a kind are numbered and get the saved trigger of their number', async (t) =>
{
	for (const [kind, [add]] of Object.entries(KINDS))
	{
		await t.step(kind, () =>
		{
			const manager = createManager();
			const first   = new FakeEditor();
			const second  = new FakeEditor();

			assertEquals(manager[add](first), 'editor 0');
			assertEquals(manager[add](second), 'editor 1');

			assertEquals([first.id, second.id], [0, 1]);
			assertEquals(first.data, TRIGGER_DATA[kind][0]);
			assertEquals(second.data, TRIGGER_DATA[kind][1] ?? {});
		});
	}
});

Deno.test('without saved triggers the editors start empty', () =>
{
	const manager = createManager({});
	const editor  = new FakeEditor();

	manager.addWallclockEditor(editor);

	assertEquals(editor.data, {});
});

Deno.test('the values of the editors which still exist are collected in their order', async (t) =>
{
	for (const [kind, [add, collect]] of Object.entries(KINDS))
	{
		await t.step(kind, () =>
		{
			const manager = createManager({});
			const editors = [new FakeEditor(), new FakeEditor(), new FakeEditor()];
			for (const editor of editors)
				manager[add](editor);

			editors[1].exists = false;

			assertEquals(manager[collect]().map(values => values.id), [0, 2]);
		});
	}
});

Deno.test('without editors nothing is collected', () =>
{
	const manager = createManager();

	for (const [, collect] of Object.values(KINDS))
		assertEquals(manager[collect](), [], collect);
});

Deno.test('the kinds are counted apart', () =>
{
	const manager   = createManager({});
	const wallclock = new FakeEditor();
	const notify    = new FakeEditor();

	manager.addWallclockEditor(wallclock);
	manager.addNotifyEditor(notify);

	assertEquals([wallclock.id, notify.id], [0, 0]);
	assertEquals(manager.collectWallclockValues().length, 1);
	assertEquals(manager.collectAccesskeyValues(), []);
});

Deno.test('a removed editor keeps its number, the next one gets a new number', () =>
{
	const manager = createManager();
	const first   = new FakeEditor();
	const second  = new FakeEditor();
	manager.addWallclockEditor(first);
	first.exists = false;

	manager.addWallclockEditor(second);

	assertEquals(second.id, 1);
	assertEquals(second.data, TRIGGER_DATA.wallclocks[1]);
});

Deno.test('only the touch editors get the touchable items of the playlist', () =>
{
	const manager   = createManager({});
	const touchable = [{item_id: 11, item_name: 'Lobby'}];
	manager.touchableMedialist = touchable;
	const touch     = new FakeEditor();
	const wallclock = new FakeEditor();

	manager.addTouchEditor(touch);
	manager.addWallclockEditor(wallclock);

	assertEquals(touch.touchableMedialist === touchable, true);
	assertEquals(wallclock.touchableMedialist, undefined);
});
