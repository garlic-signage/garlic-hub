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

/**
 * @typedef {Object} ResultData
 * @property {string} new_name
 */

/**
 * @typedef {Object} ResultType
 * @property {ResultData} data
 */

export class TreeViewDialog
{
	#action           = "";
	#treeViewService  = null;
	#treeViewElements = null;
	#directoryView    = null;
	#flashMessageHandler = null;

	constructor(treeViewElements, treeViewService, flashMessageHandler)
	{
		this.#treeViewElements = treeViewElements;
		this.#treeViewService  = treeViewService;
		this.#flashMessageHandler = flashMessageHandler;
		this.#addCancelEvent();
		this.#addSaveEvent();
	}

	prepareShow(action, lang)
	{
		this.#action = action;
		this.#treeViewElements.editFolderDialog.querySelector(".dialog-name").textContent = lang[action];
	}

	show(directoryView)
	{
		this.#directoryView = directoryView;

		switch (this.#action)
		{
			case "add_root_folder":
			case "add_sub_folder":
			case "delete_folder":
				document.getElementById("folderName").value = "";
				break;
			case "edit_folder":
				document.getElementById("folderName").value = this.#directoryView.getActiveNodeTitle();
				break;
			default:
				throw new Error("Unknown action for show");
		}

		this.#treeViewElements.editFolderDialog.showModal();
	}

	#addSaveEvent()
	{
		// also for closing the dialog with the cancel button
		this.#treeViewElements.editFolderDialog.addEventListener('close', async() =>
		{
			if (this.#treeViewElements.editFolderDialog.returnValue !== "submit")
				return;

			try
			{
				await this.#save();
			}
			catch (err)
			{
				this.#flashMessageHandler.showError(err.message);
			}
		});
	}

	async #save()
	{
		const currentNodeId = this.#directoryView.getActiveNodeId();
		if (currentNodeId === 0 && this.#action !== "add_root_folder")
			throw new Error("no node selected");

		/*
		  Remember: Only Module admins are able to see the action icon to create a root folder
		  There are checks in the backend for the case someone wants to celebrate himself as the big haxOr,

		  Regular every action assigned on an active node. Except of creating a root dir.
		  So, we need to check only the rights of this active node.
		  When no node is selected and the action is not add_root_folder, throw an error.
		*/

		const activeNodeRights = this.#directoryView.getActiveNodeRights();
		if (!activeNodeRights.create && !activeNodeRights.edit && !activeNodeRights.delete && this.#action !== "add_root_folder")
			throw new Error('There are no rights for this node.');

		let result = {};
		const folderName = document.getElementById("folderName").value;
		switch (this.#action)
		{
			case "add_root_folder":
				// again no needs to check rights
				result = await this.#treeViewService.addNode(0, folderName);
				this.#directoryView.addRootChild(result.data.id, result.data.new_name);
				break;
			case "add_sub_folder":
				if (!activeNodeRights.create)
					throw new Error('Missing create right for this node.');

				result = await this.#treeViewService.addNode(currentNodeId, folderName);
				this.#directoryView.addSubChild(result.data.id, result.data.new_name);
				break;
			case "edit_folder":
				if (!activeNodeRights.edit)
					throw new Error('Missing edit right for this node.');

				result = await this.#treeViewService.editNode(currentNodeId, folderName);
				this.#directoryView.setActiveTitle(result.data.new_name);
				break;
			// delete is handled in the context menu directly
			default:
				throw new Error("Unknown action");
		}
		this.#treeViewElements.editFolderDialog.close("cancel");
	}

	#addCancelEvent()
	{
		this.#treeViewElements.closeEditDialog.addEventListener("click", () =>
		{
			this.#treeViewElements.editFolderDialog.close("cancel");
		});
	}

}