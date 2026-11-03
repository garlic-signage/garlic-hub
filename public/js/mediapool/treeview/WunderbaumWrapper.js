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
import {TreeViewApiConfig} from "./TreeViewApiConfig.js";
import {Wunderbaum} from "../../external/wunderbaum.esm.min.js";
import {EventEmitter} from "../../core/EventEmitter.js";

export class WunderbaumWrapper
{
	// Chrome and Safari make DataTransfer while Drag and drop useless
	// see https://stackoverflow.com/questions/12958136/html5-drag-and-drop-datatransfer-and-chrome
	static workaroundShitForMediaIdBecauseOfChrome = "";

	#emitter            = new EventEmitter();
	/**
	 * @property {Wunderbaum} #tree
	 * @property {WunderbaumNode} #activeNode
	 */
	#tree               = null;
	#activeNode         = null;
	#dndConfig          = null;
	#defaultConfig       = null;
	#treeViewElements   = null;

	constructor(treeViewElements)
	{
		this.#treeViewElements = treeViewElements;
	}

	activateNodeByTarget(target)
	{
		// getNode is static for some reasons
		const node = Wunderbaum.getNode(target);
		node.setActive(true);

		return node;
	}

	get tree()
	{
		return this.#tree;
	}

	initTree()
	{
		this.treeViewElements.initialize();
		if (this.#treeViewElements.treeViewFilter !== null)
		{
			this.#initDefaultConfig();
			this.#tree = new Wunderbaum(this.#defaultConfig);
		}
	}

	addDragNDrop() // find out why this did not work
	{
		this.#initDragNDropConfig();
		this.#tree.setOption("dnd", this.#dndConfig);
	}

	addRootFolder(key, folderName)
	{
		this.#tree.addChildren({ key:  key, title: folderName, isFolder: true });
	}

	addSubFolder(key, folderName)
	{
		this.#activeNode.addChildren({ key:  key, title: folder_name, isFolder: true });
	}

	get treeViewElements()
	{
		return this.#treeViewElements;
	}

	on(eventName, listener)
	{
		return this.#emitter.on(eventName, listener);
	}

	off(eventName, listener)
	{
		return this.#emitter.off(eventName, listener);
	}

	#initDefaultConfig()
	{
		this.#defaultConfig = {
			debugLevel: TreeViewApiConfig.DEBUG_LEVEL,
			element: this.#treeViewElements.mediapoolTree,
			source: {url: TreeViewApiConfig.ROOT_NODES_URI},
			init: async (e) =>
			{
				if (localStorage.getItem('parent_list') === null)
					return;

				const parentList = localStorage.getItem('parent_list').split(",");
				let node = null;
				for (const key of parentList)
				{
					node = e.tree.findKey(key);
					if (node === null)
						return;

					await node.setExpanded(true);
				}
				await node.setActive();
			},
			selectMode: "single",
			lazyLoad: function (e)
			{
				return {
					url: TreeViewApiConfig.SUB_NODES_URI + e.node.key,
					params: {parentKey: e.node.key}
				};
			},
			activate: (e) =>
			{
				if (this.#treeViewElements.currentPath !== null)
					this.#treeViewElements.currentPath.innerText = " / " + e.node.getPath(true, "title", " / ");

				this.#activeNode = e.node;

				const parentList = e.node.getParentList(false, true);
				let keyList = parentList.map(parent => parent.key);
				localStorage.setItem('parent_list', keyList);

				// the root "shared with me" is no directory and has no media
				if (e.node.data.virtual !== true)
					this.#loadMediaInDirectory(e.node.key);
			},
			filter: {autoApply: true, mode: "hide"}
		}
		this.#treeViewElements.treeViewFilter.addEventListener("input", (event) => {
			this.#tree.filterNodes(event.target.value, { mode: "hide" });
		})

	}

	#initDragNDropConfig()
	{
		this.#dndConfig = {
			effectAllowed: "move",
			dropEffectDefault: "move",
			guessDropEffect: false,
			preventNonNodes: false,
			preventForeignNodes: false,
			dragStart: (e) =>
			{
				if (e.node.data.virtual === true)
					return false;

				e.event.dataTransfer.effectAllowed = "all";
				return true;
			},
			dragOver: (e) =>
			{
				return true;
			},
			dragLeave: (e) =>
			{
				return true;
			},
			dragEnter: (e) =>
			{
				if (e.node.data.virtual === true)
					return false;

				// before or after a shared directory would be a place under "shared with me", only into it
				if (e.sourceNode === null || e.node.parent?.data.virtual === true) // media drag'nDrop
					return ["appendChild"];
				else
					return ["before", "after", "appendChild"];
			},
			drop: (e) =>
			{
				if (e.sourceNode === null) // media Drag'nDrop
				{
					const mediaId = WunderbaumWrapper.workaroundShitForMediaIdBecauseOfChrome;
					if (mediaId === null || mediaId === undefined)
						throw Error("mediaId is not defined");

					this.#moveMediaTo(mediaId, e.node.key);
					WunderbaumWrapper.workaroundShitForMediaIdBecauseOfChrome = ""; // reset for security
				}
				else // node Drag'nDrop
				{
					this.#moveNodeTo(e);
				}
			}
		};

		// prevent a drag into this field
		this.#treeViewElements.treeViewFilter.addEventListener('dragover', (event) => event.preventDefault());
		this.#treeViewElements.treeViewFilter.addEventListener('drop', (event) => event.preventDefault());
	}

	#loadMediaInDirectory(key)
	{
		this.#emitter.emit('treeview:loadMediaInDirectory', { node_id: key });
	}

	#moveMediaTo(mediaId, nodeId)
	{
		this.#emitter.emit('treeview:moveMediaTo', { media_id: mediaId, node_id: nodeId });
	}

	#moveNodeTo(e)
	{
		this.#emitter.emit('treeview:moveNodeTo', { event: e });
	}
}