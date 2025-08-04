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

export class AutocompleteView
{
    #inputElement    = null;
    #hiddenElement   = null;
    #datalistElement = null;
    #li              = null;
    #oldParent       = null;
	#exists          = false;

    get inputElement()
    {
        return this.#inputElement;
    }

    get hiddenElement()
    {
        return this.#hiddenElement;
    }

    get datalistElement()
    {
        return this.#datalistElement;
    }

	get exists()
	{
		return this.#exists;
	}

	initExisting(fieldName)
    {
        this.#inputElement    = document.getElementById(fieldName  + "_search");
        this.#datalistElement = document.getElementById(fieldName + "_suggestions");
        this.#hiddenElement   = document.getElementById(fieldName);

		if (this.#inputElement !== null && this.#datalistElement !== null && this.#hiddenElement !== null)
			this.exists = true;
    }

    initCreate(parent, fieldName)
    {
        this.#oldParent = parent;
        this.#li = document.createElement("li");
        this.#li.className            = "playlist_id";
        this.#inputElement      = document.createElement("input");
        this.#inputElement.id   = fieldName  + "_search";
        this.#inputElement.type = "text";
        this.#inputElement.setAttribute('list', fieldName + "_suggestions");
        this.#li.appendChild(this.#inputElement);

        this.#datalistElement    = document.createElement("datalist");
        this.#datalistElement.id = fieldName + "_suggestions";
        this.#li.appendChild(this.#datalistElement);

        this.#hiddenElement      = document.createElement("input");
        this.#hiddenElement.id   = fieldName;
        this.#hiddenElement.type = "hidden";
        this.#li.appendChild(this.#inputElement);

        parent.replaceWith(this.#li);
        this.#inputElement.focus();
    }

    restore(newName)
    {
        this.#li.replaceWith(this.#oldParent);
        this.#oldParent.innerHTML = newName;
    }

}
