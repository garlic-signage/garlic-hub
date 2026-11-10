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

export class DragDropManager
{
    #domElements  = null;
    #filePreview = null;

    constructor(domElements, filePreview)
    {
        this.#domElements = domElements;
        this.#filePreview = filePreview;
    }

    init()
    {
		this.#domElements.dropzone.addEventListener('dragover', (e) => this.onDragOver(e));
		this.#domElements.dropzone.addEventListener('dragleave', () => this.onDragLeave());
		this.#domElements.dropzone.addEventListener('drop', (e) => this.onDrop(e));
		this.#domElements.dropzone.addEventListener('click', () => this.#domElements.fileInput.click());
		this.#domElements.fileInput.addEventListener('change', (event) => {
            const files = Array.from(event.target.files);
            // the same file again, e.g. after its preview was removed, is a change only for an empty field
            event.target.value = "";
            this.#filePreview.handleFiles(files);
        });
    }

    onDragOver(event)
    {
        event.preventDefault();
		this.#domElements.dropzone.style.borderColor = "#007bff";
    }

    onDragLeave()
    {
		this.#domElements.dropzone.style.borderColor = "#ccc";
    }

    onDrop(event)
    {
        event.preventDefault();
		this.#domElements.dropzone.style.borderColor = "#ccc";
        const files =  Array.from(event.dataTransfer.files);
        this.#filePreview.handleFiles(files);
    }
}