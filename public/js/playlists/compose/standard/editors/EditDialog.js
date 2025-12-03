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
export class EditDialog
{
	#dialogElement        = document.getElementById("editItemDialog");
	#itemIdElement        = document.getElementById("editItemId");
	#itemNameElement      = document.getElementById("editItemName");
	#itemContentElement   = document.getElementById("editItemContent");
	#editItemForm         = document.getElementById("editItemForm");
	#closeEditDialog      = document.getElementById("closeEditDialog");
	#editItemErrorMessage = document.getElementById("editItemErrorMessage");
	#cancelButton         = this.#dialogElement.querySelector('button[value="cancel"]');
	#saveCallback         = null;

	constructor() {}

	openDialog()
	{
		this.#dialogElement.showModal();
	}

	setWidth(percent)
	{
		this.#dialogElement.style.width = percent;
	}

	closeDialog()
	{
		this.#dialogElement.close();
		this.#editItemForm.removeEventListener("submit", this.#saveCallback);
	}

	setId(id)
	{
		this.#itemIdElement.value = id;
	}

	setErrorMessage(message)
	{
		this.#editItemErrorMessage.innerText = message;
	}

	setTitle(title)
	{
		this.#itemNameElement.innerText = title;
	}

	setContent(content)
	{
		this.#itemContentElement.innerHTML = content;
	}

	empty()
	{
		this.#itemIdElement.value = "";
		this.#itemNameElement.value = "";
		this.#itemContentElement.innerHTML = "";
	}

	onSave(callback)
	{
		this.#saveCallback = callback
		this.#editItemForm.addEventListener("submit", this.#saveCallback, { once: true });
	}

	removeListener

	onCancel()
	{

		this.cancelHandler = (e) => {
			e.preventDefault();
			this.#cancelButton.removeEventListener("click", this.cancelHandler);
			this.#editItemForm.removeEventListener("click", this.#saveCallback);
			this.empty();
			this.closeDialog();
		};
		this.#cancelButton.addEventListener("click", this.cancelHandler);
		this.#closeEditDialog.addEventListener("click", this.cancelHandler);
	}


}
