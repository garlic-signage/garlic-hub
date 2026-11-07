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

import {AdjustMenus} from "../../core/AdjustMenus.js";

export class ContextMenuTreeView
{
    #menu = null;
    #treeDialog = null;
	#treeViewService = null;
	#flashMessageHandler = null;
	#lang  = null;
	#shareDialog = null;
	#folderTagging = null;

    constructor(treeViewElements, treeDialog, treeViewService, flashMessageHandler, lang, shareDialog, folderTagging = null)
    {
		this.#folderTagging   = folderTagging;
		this.#shareDialog     = shareDialog;
        this.#menu            = treeViewElements.menuTemplate.content.cloneNode(true).firstElementChild;
        this.#treeDialog      = treeDialog;
		this.#treeViewService = treeViewService;
		this.#flashMessageHandler = flashMessageHandler;
		this.#lang            = lang;
    }

    show(event)
    {
        document.querySelectorAll('.context-menu').forEach(el => el.remove());  // remove all previous menu
        this.#menu.style.left = `${event.pageX}px`;
        this.#menu.style.top = `${event.pageY}px`;

		document.body.appendChild(this.#menu);
		AdjustMenus.adjustDropdownPosition(this.#menu);
        document.addEventListener('click', () => this.#menu.remove(), {once: true});
    }

    addEditEvent(editNodeElement, directoryView)
    {
        editNodeElement.addEventListener("click", () => {
            this.#treeDialog.prepareShow("edit_folder", this.#lang);
            this.#treeDialog.show(directoryView);
        });
    }

    addAddEvent(addNodeElement, directoryView)
    {
        addNodeElement.addEventListener("click", () => {
            this.#treeDialog.prepareShow("add_sub_folder", this.#lang);
            this.#treeDialog.show(directoryView);
        });
    }

    addShareEvent(shareNodeElement, currentTreeNode)
    {
		this.#shareDialog.attachTo(shareNodeElement, currentTreeNode.key, currentTreeNode.data.rights.share_unit === true);
    }

    get canTagFolders()
    {
        return this.#folderTagging !== null;
    }

    addTagEvent(tagNodeElement, currentTreeNode, directoryView)
    {
        tagNodeElement.addEventListener("click", async () => {
			try
			{
				const tagged = await this.#folderTagging.run(currentTreeNode.key);
				// the media of the folder show their tags in the information
				if (tagged > 0 && directoryView.isActiveNode(currentTreeNode))
					directoryView.reloadCurrentNode();
			}
			catch (err)
			{
				this.#flashMessageHandler.showError(err.message);
			}
        });
    }

    addRemoveEvent(removeNodeElement, currentTreeNode)
    {
        removeNodeElement.addEventListener("click", () => {
            (async () => {
				try
				{
					if (!currentTreeNode.data.rights.delete)
						throw new Error('Missing delete right for this node.');

					await this.#treeViewService.deleteNode(currentTreeNode.key);

					currentTreeNode.remove();
				}
				catch (err)
				{
					this.#flashMessageHandler.showError(err.message);
				}
            })();
        });
    }
}