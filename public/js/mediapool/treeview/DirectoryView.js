/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

import { TreeViewApiConfig } from "./TreeViewApiConfig.js";
import { Wunderbaum } from "../../external/wunderbaum.esm.min.js";
import { UploadDialogElements } from "../uploads/UploadDialogElements.js";

/**
 * @typedef {Object} NodeEvent
 * @property {WunderbaumNode} node
 * @property {Object} sourceNode
 * @property {Object} suggestedDropMode
 * @property {Function} node.getPath
 * @property {Function} node.getParentList
 * @property {string} node.key
 */

export class DirectoryView
{
	// Chrome and Safari make DataTransfer while Drag and drop useless
	// see https://stackoverflow.com/questions/12958136/html5-drag-and-drop-datatransfer-and-chrome
	static workaroundShitForMediaIdBecauseOfChrome = "";

	#tree               = {};
	#treeViewElements   = null;
    #activeNode         = null;
    #mediaList          = null;
	#treeViewService	= null;
	#flashMessageHandler = null;

    constructor(treeViewElements, mediaList, treeViewService, flashMessageHandler)
    {
        this.#treeViewElements = treeViewElements;
        this.#mediaList        = mediaList;
		this.#treeViewService  = treeViewService;
		this.#flashMessageHandler = flashMessageHandler;

		this.#tree             = new Wunderbaum({
            debugLevel: TreeViewApiConfig.DEBUG_LEVEL,
            element: this.#treeViewElements.mediapoolTree,
            source: { url: TreeViewApiConfig.ROOT_NODES_URI },
            init: async (e) => {
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
            lazyLoad: function (e){
                return { url:TreeViewApiConfig.SUB_NODES_URI + e.node.key, params: { parentKey: e.node.key } };
             },
			activate: (e) => {
                this.#treeViewElements.currentPath.innerText = " / " + e.node.getPath(true, "title", " / ");

                this.#activeNode = e.node;

                const parentList = e.node.getParentList(false, true);
                let keyList = parentList.map(parent => parent.key);
                localStorage.setItem('parent_list', keyList);
                this.#loadMediaInDirectory(e.node);
			},
            filter: {autoApply: true, mode: "hide"},
            dnd: {
                effectAllowed: "move",
                dropEffectDefault: "move",
                guessDropEffect: false,
                preventNonNodes: false,
                preventForeignNodes: false,
                dragStart: (e) => {
					e.event.dataTransfer.effectAllowed = "all";
                    return true;
                },
                dragOver: (e) => {
                    return true;
                },
                dragLeave: (e) => {
                    return true;
                },
                dragEnter: (e) => {
					if (e.sourceNode === null) // media drag'nDrop
                        return ["appendChild"];
                    else
                        return ["before", "after", "appendChild"];
                },
                drop: (e) => {
                    if (e.sourceNode === null) // media drag'nDrop
                    {
						const mediaId = DirectoryView.workaroundShitForMediaIdBecauseOfChrome;
                        if (mediaId === null || mediaId === undefined)
                            throw Error("mediaId is not defined")

                        this.#mediaList.moveMediaTo(mediaId, e.node.key)
							.catch(err => this.#flashMessageHandler.showError(err.message));
						DirectoryView.workaroundShitForMediaIdBecauseOfChrome = ""; // reset for security
					}
                    else
                    {
                        this.#moveNodeTo(e);
                    }
                },
            },
        });

		// prevent a drag into this field
		this.#treeViewElements.treeViewFilter.addEventListener('dragover', (event) => event.preventDefault());
		this.#treeViewElements.treeViewFilter.addEventListener('drop', (event) => event.preventDefault());
		this.#treeViewElements.treeViewFilter.addEventListener("input", (event) => {
			this.#tree.filterNodes(event.target.value, { mode: "hide" });
		})
    }

    addContextMenu(contextMenuTreeViewFactory)
    {
        this.#treeViewElements.mediapoolTree.addEventListener("contextmenu", (event) => {
            event.preventDefault();

            // check rights
            const currentTreeNode = this.setActiveNodeFromEventTarget(event.target);
            const rights = currentTreeNode.data.rights;
            if (!rights.create  && !rights.edit  && !rights.delete)
                return;

            const contextMenu  = contextMenuTreeViewFactory.create();
            contextMenu.show(event);

            const editNodeElement = document.getElementById("edit_node");
            if (rights.edit)
                contextMenu.addEditEvent(editNodeElement, this);
            else
                editNodeElement.remove();

            const addNodeElement = document.getElementById("add_node");
            if (rights.create)
                contextMenu.addAddEvent(addNodeElement, this);
            else
                addNodeElement.remove();

            const removeNodeElement = document.getElementById("remove_node");
            if (rights.delete)
                contextMenu.addRemoveEvent(removeNodeElement, currentTreeNode);
            else
                removeNodeElement.remove();
        });
    }

    reloadCurrentNode()
    {
        this.#loadMediaInDirectory(this.#activeNode);
    }

    setActiveTitle(title)
    {
        if (this.#activeNode === null)
            throw new Error("No active node");

        this.#activeNode.title = title;
        this.#activeNode.update();
    }

    setActiveNodeVisibility(visibility)
    {
        if (this.#activeNode === null)
            throw new Error("No active node");

        this.#activeNode.data.visibility = visibility;
        this.#activeNode.update();
    }

    getActiveVisibility()
    {
        if (this.#activeNode === null)
            return "";

        return this.#activeNode.title;
    }

    getActiveNodeTitle()
    {
        if (this.#activeNode === null)
            return "";

        return this.#activeNode.title;
    }

	getActiveNodeId()
    {
        if (this.#activeNode === null)
            return 0;

        return this.#activeNode.key;
    }

    getActiveNodeRights()
    {
        if (this.#activeNode === null)
            return 0;

        return this.#activeNode.data.rights;
    }


    getActiveNodeVisibility()
    {
        if (this.#activeNode === null)
            return 0;

        return this.#activeNode.data.visibility;
    }

    setActiveNodeFromEventTarget(event_target)
    {
        // getNode is static for some reasons
        const node = Wunderbaum.getNode(event_target);
        node.setActive(true);

        return node;
    }

    addRootChild(key, folder_name)
    {
		const rights = {"create" : true, "edit" : true, "delete" : true}
        this.#tree.addChildren({ key:  key, title: folder_name, isFolder: true, rights: rights });
    }

    addSubChild(key, folder_name)
    {
		const rights = {"create" : true, "edit" : true, "delete" : true}
        if (this.#activeNode === null)
        {
            console.error("No active node");
            return;
        }
        this.#tree.getActiveNode().addChildren({ key:  key, title: folder_name, isFolder: true, rights: rights });
    }

    async #loadMediaInDirectory(node)
    {
        try
        {
			UploadDialogElements.openUploadDialog.disabled = !node.data.rights.create;
			await this.#mediaList.loadMediaListByNode(node.key, node.data.rights);
        }
        catch (err)
        {
            this.#flashMessageHandler.showError(err.message);
        }
    }

    async #moveNodeTo(e)
    {
		try
		{
			await this.#treeViewService.moveNodeTo(e.sourceNode.key, e.node.key, e.suggestedDropMode);
			e.sourceNode.moveTo(e.node, e.suggestedDropMode);
		}
		catch (err)
		{
			this.#flashMessageHandler.showError(err.message);
		}
    }
}