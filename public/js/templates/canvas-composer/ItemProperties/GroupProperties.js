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


export class GroupProperties
{
	MyCanvasView = {};
	group;

	constructor(MyCanvasView, toggleButtonFactory)
	{
		this.MyCanvasView = MyCanvasView;
		this.group         = toggleButtonFactory.create(document.getElementById("object_group"))
	}

	activate(object)
	{
		// do not group if there is one item locked
		if (this.MyCanvasView.isLockedInSelection() === true)
			return;

		this.group.show(true);
		this.group.update('display', object.type === 'group' ? 'ungroup' : 'group')
		this.current = object.type;
	}

	deactivate()
	{
		this.group.show(false)
	}

	initEventListener()
	{
		this.group.getElement().addEventListener("click", () =>
		{
			let object = this.MyCanvasView.getCanvas().getActiveObject();
			this.setGroup(object)
			this.MyCanvasView.renderCanvas();
		});

		this.MyCanvasView.getCanvas().on('selection:updated', ({selected, target}) =>
		{
			if (this.MyCanvasView.isLockedInSelection() === true)
			{
				this.MyCanvasView.setLockedStatus(target, true);
			}
		});
	}
//============== private methods ===========================================================

	setGroup(object)
	{
		if (!object) return
		if (object.type === 'activeSelection')
		{
			object.toGroup()
			this.group.update('display', 'ungroup') // TEMP, TODO CANVAS EVENTS UPDATE
			this.MyCanvasView.getCanvas().fire('selection:updated', {
				target: object
			})
			return
		}
		if (object.type === 'group') {
			object.toActiveSelection()
			this.group.update('display', 'group') // TEMP, TODO CANVAS EVENTS UPDATE
			this.MyCanvasView.getCanvas().fire('selection:updated', {
				target: object
			})
		}
	}
}
