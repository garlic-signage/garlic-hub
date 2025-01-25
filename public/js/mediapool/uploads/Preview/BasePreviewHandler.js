/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
 This file is part of the garlic-hub source code

 This program is free software: you can redistribute it and/or  modify
 it under the terms of the GNU Affero General Public License, version 3,
 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/


export class BasePreviewHandler
{
    #previewArea    = null;
    #previewFactory = null;
    #fileList       = {};
	#metaDataList   = {}; // need metadata as mediarecorder do not include duration
    #fileUploader   = null;
    #xhrUpload      = null;
    #upload_id      = null;

    constructor(previewArea, previewFactory)
    {
        this.#previewArea     = previewArea;
        this.#previewFactory  = previewFactory;
    }

	get metaDataList() { return this.#metaDataList; }

    get fileList() { return this.#fileList; }

    set fileUploader(fileUploader)
    {
        this.#fileUploader = fileUploader;
    }

    setUploadHandler(xhr, id)
    {
        this.#xhrUpload = xhr;
        this.#upload_id = id;
    }

    addFile(file, metadata)
    {
        try
        {
            const previewHandler = this.#previewFactory.create(file);
            const metadata       = previewHandler.extractMetadata(file);
            const previewElement = previewHandler.createPreview();
            previewElement.className = "previewElement";

            const id = crypto.randomUUID();
            this.#fileList[id] = file;
			if (metadata !== null)
				this.#metaDataList[id] = metadata;

            const previewContainer = this.createPreviewContainer(metadata, previewElement, id);
            this.#previewArea.appendChild(previewContainer);
            this.#fileUploader.enableUploadButton();
        }
        catch (error)
        {
            alert(`${file.name} not supported.` + error);
        }
    }

    createPreviewContainer(metadata, previewElement, id)
    {
        const container     = document.createElement("div");
        container.className = "previewContainer";

        // Tooltip
        const tooltip     = document.createElement("div");
        tooltip.className = "tooltip";
        tooltip.innerHTML = `${metadata.name}<br> ${metadata.size}<br /> ${metadata.type}`
        container.appendChild(tooltip);
        container.addEventListener("mouseenter", () => {tooltip.style.visibility = "visible";});
        container.addEventListener("mouseleave", () => {tooltip.style.visibility = "hidden";});

        // Close button
        const closeButton = document.createElement("button");
        closeButton.className = "closeButton";
        closeButton.textContent = "×";

        container.setAttribute('data-preview-id', id);
        closeButton.addEventListener("click", () => {
            this.removeFromPreview(id);
        });

        container.appendChild(closeButton);
        container.appendChild(previewElement);
        return container;
    }

    removeFromPreview(id)
    {
        if (this.#xhrUpload !== null && this.#upload_id === id)
            this.#xhrUpload.abort();

        delete this.#fileList[id];
        document.querySelector(`[data-preview-id="${id}"]`).remove();

        if (Object.keys(this.#fileList).length === 0)
            this.#fileUploader.disableUploadButton();

    }
}