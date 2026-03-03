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
import {EventEmitter} from "../../core/EventEmitter.js";

export class MediaSelector
{
	#filter = "";
	#selectedMediaId = 0;
	#selectedMediaLink = "";
	#emitter = new EventEmitter();

	#treeViewWrapper  = {};
	#mediaService = {};
	#selectorView = {};
	#isMultiselect = false;

	constructor(treeViewWrapper, mediaService, selectorView)
	{
		this.#treeViewWrapper = treeViewWrapper;
		this.#mediaService = mediaService;
		this.#selectorView = selectorView;

		this.#initEvents();
	}

	enableMultiSelect()
	{
		this.#isMultiselect = true;
	}

	disableMultiSelect()
	{
		this.#isMultiselect = false;
	}

	set filter(value)
	{
		this.#filter = value;
	}

	getMediaItemsContainer()
	{
		return this.#selectorView.getMediaListElement();
	}

	getMediaItems()
	{
		return this.#selectorView.mediaItems;
	}

	get selectedMediaId()
	{
		return this.#selectedMediaId;
	}

	get selectedMediaLink()
	{
		return this.#selectedMediaLink;
	}

	on(eventName, listener)
	{
		return this.#emitter.on(eventName, listener);
	}

	off(eventName, listener)
	{
		return this.#emitter.off(eventName, listener);
	}

	getSelectedMedia()
	{
		return [...document.querySelectorAll('.media-item.selected')].map(article => ({
			id: article.dataset.mediaId,
			src: article.querySelector('img').src
		}));
	}

	enableMultiselect()
	{
		this.#isMultiselect = true;
	}

	disableMultiselect()
	{
		this.#isMultiselect = false;
	}


	async showSelector(element)
	{
		element.replaceChildren(this.#selectorView.loadSelectorTemplate());
		this.#treeViewWrapper.initTree();

		document.getElementById('mediaList').addEventListener('click', (e) => {
			const item = e.target.closest('.media-item');
			if (!item)
				return;

			if (!this.#isMultiselect)
				document.querySelectorAll('.media-item.selected').forEach(el => el.classList.remove('selected'));
			item.classList.add('selected');
		});
	}

	async loadMedia(nodeId)
	{
		return await this.#mediaService.loadFilteredMediaByNodeId(nodeId, this.#filter);
	}

	displayMediaList(mediaList)
	{
		this.#selectorView.displayMediaList(mediaList);
	}

	#initEvents()
	{
		this.#treeViewWrapper.on("treeview:loadMediaInDirectory", async (args) =>
		{
			const results = await this.loadMedia(args.node_id);
			this.displayMediaList(results);
			this.#emitter.emit('mediapool:selector:loaded', {nodeId: args.node_id });
		});
	}
}
