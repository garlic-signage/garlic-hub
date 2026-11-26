/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2025 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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
'use strict';

export class EditorsManager {
	#triggerData = null;
	#triggerDataEnd = null;
	#touchableMedialist = null;
	#wallclocksEditors = [];
	#accesskeysEditors = [];
	#touchesEditors = [];
	#notifyEditors = [];

	set triggerData(value) {
		this.#triggerData = value;
	}

	set triggerDataEnd(value) {
		this.#triggerDataEnd = value;
	}

	set touchableMedialist(value) {
		this.#touchableMedialist = value;
	}

	addWallclockEditor(editor) {
		editor.id = this.#wallclocksEditors.length;
		this.#determineInitDataForEditor(editor, "wallclocks");
		this.#wallclocksEditors.push(editor);

		return editor.getEditor();
	}

	collectWallclockValues() {
		let values = [];
		for (let i = 0; i < this.#wallclocksEditors.length; i++) {
			if (this.#wallclocksEditors[i].exists) {
				values.push(this.#wallclocksEditors[i].getValues());
			}
		}
		return values;
	}

	addAccesskeyEditor(editor) {
		editor.id = this.#accesskeysEditors.length;
		this.#determineInitDataForEditor(editor, "accesskeys");
		this.#accesskeysEditors.push(editor);

		return editor.getEditor();
	}

	collectAccesskeyValues() {
		let values = [];
		for (let i = 0; i < this.#accesskeysEditors.length; i++) {
			if (this.#accesskeysEditors[i].exists) {
				values.push(this.#accesskeysEditors[i].getValues());
			}
		}
		return values;
	}

	addTouchEditor(editor) {
		editor.id = this.#touchesEditors.length;
		editor.touchableMedialist = this.#touchableMedialist;
		this.#determineInitDataForEditor(editor, "touches");
		this.#touchesEditors.push(editor);

		return editor.getEditor();
	}

	collectTouchValues() {
		let values = [];
		for (let i = 0; i < this.#touchesEditors.length; i++) {
			if (this.#touchesEditors[i].exists) {
				values.push(this.#touchesEditors[i].getValues());
			}
		}

		return values;
	}

	addNotifyEditor(editor) {
		editor.id = this.#notifyEditors.length;
		this.#determineInitDataForEditor(editor, "notifies");
		this.#notifyEditors.push(editor);

		return editor.getEditor();
	}

	collectNotifyValues() {
		let values = [];
		for (let i = 0; i < this.#notifyEditors.length; i++) {
			if (this.#notifyEditors[i].exists) {
				values.push(this.#notifyEditors[i].getValues());
			}
		}
		return values;
	}

	#determineInitDataForEditor(editor, triggerName) {
		let data = {};
		if (this.#triggerData.hasOwnProperty(triggerName) && this.#triggerData[triggerName].hasOwnProperty(editor.id))
			data = this.#triggerData[triggerName][editor.id];
		if (this.#triggerDataEnd.hasOwnProperty(triggerName) && this.#triggerDataEnd[triggerName].hasOwnProperty(editor.id))
			data = this.#triggerDataEnd[triggerName][editor.id];

		editor.init(data);
	}

}
