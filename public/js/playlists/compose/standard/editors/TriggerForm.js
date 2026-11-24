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

export class TriggerForm {
	#triggerTypeFactory = null;
	#addWallclock = null;
	#wallclocksContainer = null;
	#addAccesskey = null;
	#accesskeysContainer = null;
	#addTouch = null;
	#touchesContainer = null;
	#addNotify = null;
	#notifiesContainer = null;
	#editorsManager = null;

	constructor(triggertypeFactory, editorsManager) {
		this.#triggerTypeFactory = triggertypeFactory;
		this.#editorsManager = editorsManager;
	}

	init(triggerData, touchableMedialist) {
		this.#editorsManager.triggerData = triggerData.begin_trigger;
		this.#editorsManager.triggerDataEnd = triggerData.end_trigger;
		this.#editorsManager.touchableMedialist = touchableMedialist;

		this.#addWallclock = document.getElementById("addWallclock");
		this.#wallclocksContainer = document.getElementById("wallclocksContainer");
		this.#addWallclock.addEventListener("click", () => {
			const editor = this.#triggerTypeFactory.create("wallclock");
			const html = this.#editorsManager.addWallclockEditor(editor);
			this.#wallclocksContainer.appendChild(html);
		});

		this.#addAccesskey = document.getElementById("addAccesskey");
		this.#accesskeysContainer = document.getElementById("accesskeysContainer");
		this.#addAccesskey.addEventListener("click", () => {
			const editor = this.#triggerTypeFactory.create("accesskey");
			const html = this.#editorsManager.addAccesskeyEditor(editor);
			this.#accesskeysContainer.appendChild(html);
		});

		this.#addTouch = document.getElementById("addTouch");
		this.#touchesContainer = document.getElementById("touchesContainer");
		this.#addTouch.addEventListener("click", () => {
			const editor = this.#triggerTypeFactory.create("touch");
			const html = this.#editorsManager.addTouchEditor(editor);
			this.#touchesContainer.appendChild(html);
		});

		this.#addNotify = document.getElementById("addNotify");
		this.#notifiesContainer = document.getElementById("notifiesContainer");
		this.#addNotify.addEventListener("click", () => {
			const editor = this.#triggerTypeFactory.create("notify");
			const html = this.#editorsManager.addNotifyEditor(editor);
			this.#notifiesContainer.appendChild(html);
		});

		// init existing from data
		if (triggerData.hasOwnProperty("begin_trigger")) {
			if (triggerData.hasOwnProperty("wallclocks")) {
				for (let i = 0; i < triggerData.wallclocks.length; i++) {
					const editor = this.#triggerTypeFactory.create("wallclock");
					const html = this.#editorsManager.addWallclockEditor(editor);
					this.#wallclocksContainer.appendChild(html);
				}
			}
			if (triggerData.hasOwnProperty("accesskeys")) {
				for (let i = 0; i < triggerData.accesskeys.length; i++) {
					const editor = this.#triggerTypeFactory.create("accesskey");
					const html = this.#editorsManager.addAccesskeyEditor(editor);
					this.#accesskeysContainer.appendChild(html);
				}
			}
			if (triggerData.hasOwnProperty("touches")) {
				for (let i = 0; i < triggerData.touches.length; i++) {
					const editor = this.#triggerTypeFactory.create("touch");
					const html = this.#editorsManager.addTouchEditor(editor);
					this.#touchesContainer.appendChild(html);
				}
			}
			if (triggerData.hasOwnProperty("notifies")) {
				for (let i = 0; i < triggerData.notifies.length; i++) {
					const editor = this.#triggerTypeFactory.create("notify");
					const html = this.#editorsManager.addNotifyEditor(editor);
					this.#notifiesContainer.appendChild(html);
				}
			}
		}
		if (triggerData.hasOwnProperty("end_trigger")) {
			if (triggerData.end_trigger.hasOwnProperty("touches")) {
				for (let i = 0; i < triggerData.end_trigger.touches.length; i++) {
					const editor = this.#triggerTypeFactory.create("touch");
					const html = this.#editorsManager.addTouchEditor(editor);
					this.#touchesContainer.appendChild(html);
				}
			}
		}
	}

	collectValues() {
		let triggers = {}

		let wallclocks = this.#editorsManager.collectWallclockValues();
		if (Object.keys(wallclocks).length > 0)
			triggers.wallclocks = wallclocks;

		let accesskeys = this.#editorsManager.collectAccesskeyValues();
		if (Object.keys(accesskeys).length > 0)
			triggers.accesskeys = accesskeys;

		let touches = this.#editorsManager.collectTouchValues();
		if (Object.keys(touches).length > 0)
			triggers.touches = touches;

		let notifies = this.#editorsManager.collectNotifyValues();
		if (Object.keys(notifies).length > 0)
			triggers.notifies = notifies;

		return triggers;
	}
}