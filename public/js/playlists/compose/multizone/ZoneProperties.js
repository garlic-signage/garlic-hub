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
import { LabeledZone } from './LabeledZone.js';
import './CanvasView.js';

export class ZoneProperties
{
	zone_name        = document.getElementById("zone_name");
	zone_left        = document.getElementById("zone_left");
	zone_top         = document.getElementById("zone_top");
	zone_width       = document.getElementById("zone_width");
	zone_height      = document.getElementById("zone_height");
	zone_bgcolor     = document.getElementById("zone_bgcolor");
	zone_props       = document.getElementById("zone_properties");
	zone_transparent = document.getElementById("zone_transparent");
	zones_container  = document.getElementById("zones_container");
	zone_playlist_id   = null; // will come from Autocomplete
	zone_playlist_name = null; // will come from Autocomplete
	ActiveGroup  = null;
	MyCanvasView = null;
	autocompletePlaylist = null;

	constructor(MyCanvasView, autocompletePlaylist)
	{
		this.MyCanvasView         = MyCanvasView;
		this.autocompletePlaylist = autocompletePlaylist;
		this.zone_playlist_id     = autocompletePlaylist.getHiddenIdElement();
		this.zone_playlist_name   = autocompletePlaylist.getEditFieldElement();

		this.zone_name.addEventListener("input", () =>
		{
			if (this.ActiveGroup != null)
			{
				if (this.ActiveGroup.item(1).text === this.zone_name.value)
					return;


				this.MyCanvasView.setChanged(true);
				this.ActiveGroup.item(1).text = this.zone_name.value;
				this.ActiveGroup.dirty        = true;

				let id = this.MyCanvasView.getActiveObject().getId()
				this.editListItemName(id);

				this.MyCanvasView.renderCanvas();
			}
		});
		this.zone_left.addEventListener("blur", () =>
		{
			if (this.ActiveGroup != null)
			{
				if (this.ActiveGroup.left === Number(this.zone_left.value))
					return;

				this.MyCanvasView.setChanged(true);
				this.ActiveGroup.left = Number(this.zone_left.value);

				this.MyCanvasView.renderCanvas();
			}
		});
		this.zone_top.addEventListener("blur", () =>
		{
			if (this.ActiveGroup != null)
			{
				if (this.ActiveGroup.top === Number(this.zone_top.value))
					return;

				this.MyCanvasView.setChanged(true);
				this.ActiveGroup.top = Number(this.zone_top.value);
				this.MyCanvasView.renderCanvas();
			}
		});
		this.zone_width.addEventListener("blur", () =>
		{
			if (this.ActiveGroup != null)
			{
				this.ActiveGroup.changeWidth(Number(this.zone_width.value));
				this.MyCanvasView.setChanged(true);
				this.MyCanvasView.renderCanvas();
			}
		});
		this.zone_height.addEventListener("blur", () =>
		{
			if (this.ActiveGroup != null)
			{
				this.ActiveGroup.changeHeight(Number(this.zone_height.value));
				this.MyCanvasView.setChanged(true);
				this.MyCanvasView.renderCanvas();
			}
		});
		this.zone_bgcolor.addEventListener("input", () =>
		{
			if (this.ActiveGroup != null)
			{
				this.ActiveGroup.changeBgColor(this.zone_bgcolor.value);
				this.MyCanvasView.setChanged(true);
				this.MyCanvasView.renderCanvas();
			}
		});
		this.zone_transparent.addEventListener("input", () =>
		{
			if (this.ActiveGroup != null)
			{
				if (this.zone_transparent.checked === true)
				{
					this.zone_bgcolor.disabled = true;
					this.ActiveGroup.changeBgColor(LabeledZone._transparent);
				}
				else
				{
					this.zone_bgcolor.disabled = false;
					this.ActiveGroup.changeBgColor("#000000");
				}
				this.MyCanvasView.setChanged(true);
				this.MyCanvasView.renderCanvas();
			}
		});
		this.zone_playlist_id.addEventListener('selectedFromDataList', (e) =>
		{
			if (this.ActiveGroup != null)
			{
				if (this.ActiveGroup.zone_playlist_id === this.zone_playlist_id.value)
					return;

				this.MyCanvasView.setChanged(true);
				this.ActiveGroup.item(2).text = this.zone_playlist_name.value;
				this.ActiveGroup.dirty  = true;

				this.ActiveGroup.zone_playlist_id = this.zone_playlist_id.value;

				this.MyCanvasView.renderCanvas();
			}
		});
		this.zone_playlist_name.addEventListener('blur', () =>
		{
			if (this.ActiveGroup != null && this.zone_playlist_name.value === "")
			{
				this.MyCanvasView.setChanged(true);
				this.ActiveGroup.zone_playlist_id = 0;
			}
		});
	}

	deactivate()
	{
		this.zone_name.value       = "";
		this.zone_left.value       = "";
		this.zone_top.value        = "";
		this.zone_width.value      = "";
		this.zone_height.value     = "";
		this.zone_bgcolor.value    = "";
		this.zone_props.disabled   = true;
		this.autocompletePlaylist.clearAll();

		this.ActiveGroup    = null;
	}

	activate(group)
	{
		if (group == null)
		{
			this.deactivate();
			return;
		}
		this.ActiveGroup         = group;

		this.zone_name.value     = group.label.text;
		this.zone_left.value     = Math.round(group.left);
		this.zone_top.value      = Math.round(group.top);
		this.zone_width.value    = Math.round(group.width);
		this.zone_height.value   = Math.round(group.height);

		group.rect.fill = this.fixRGBA(group.rect.fill);

		this.#toggleColorchecker(group.rect.fill);
		this.#fetchPlaylistName();

		this.zone_props.disabled = false;
	}

	fixRGBA(color)
	{
		if (color.startsWith("rgba"))
		{
			if (!color.endsWith(")"))
				return color + ")";
		}

		return color;
	}

	highlightListItemById(id)
	{
		document.querySelectorAll('li').forEach((item) => {
			item.classList.remove('active');
		});

		let listItem = document.querySelector(`li[id="${id}"]`);
		if (listItem) {
			listItem.classList.add('active');
		}
	}

	removeListItem(id)
	{
		let item = document.querySelector(`li[id="${id}"]`);
		if (item)
			item.remove();
	}

	editListItemName(id)
	{
		let item = document.querySelector(`li[id="${id}"]`);
		if (item)
			item.textContent = this.zone_name.value;
	}


	addList(MyLabeledZone)
	{
		let list = zones_container.querySelector('ul');
		if (!list)
		{
			list = document.createElement('ul');
			list.id = "zones_list"
			this.zones_container.appendChild(list);
		}

		let list_item         = document.createElement('li');
		list_item.id          = MyLabeledZone.id;
		list_item.textContent = MyLabeledZone.label.text;

		list_item.addEventListener("click", () => {

			this.highlightListItemById(list_item.id);

			this.MyCanvasView.getCanvas().getObjects().forEach((obj) => {
				if (obj.id === list_item.id) {
					this.MyCanvasView.getCanvas().setActiveObject(obj);
					this.activate(obj);
					this.MyCanvasView.renderCanvas();
				}
			});
		});

		list.appendChild(list_item);
	}

	#toggleColorchecker(fill)
	{
		if ( fill === LabeledZone._transparent)
		{
			this.zone_transparent.checked = true;
			this.zone_bgcolor.disabled = true;
		}
		else
		{
			// set colorpicker here as HTML5 colorpicker do not accept transparency
			this.zone_transparent.checked = false;
			this.zone_bgcolor.disabled = false;
			this.zone_bgcolor.value  = fill;
		}
	}

	async #fetchPlaylistName()
	{
		if (this.ActiveGroup.zone_playlist_id === 0)
		{
			this.autocompletePlaylist.setInputFields(0, "");
			return;
		}

		try
		{
			const url      = "/async/playlists/find/" + this.ActiveGroup.zone_playlist_id;
			const response = await fetch(url);
			const playlist = await response.json();

			if (playlist.lenght === 0)
				return;

			this.autocompletePlaylist.setInputFields(playlist.playlist_id, playlist.name);

		}
		catch (error)
		{
			console.error('Error fetching suggestions:', error);
		}
	}


}