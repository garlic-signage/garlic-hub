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

export class MediaEditDialog
{
    #dialogElement    = document.getElementById('editMediaDialog');
    #closeElement     = document.getElementById("closeEditMediaDialog");
    #mediaService     = null;
	#editFilename     = document.getElementById("editFilename");
	#editDescription  = document.getElementById("editDescription");
	#editTags         = document.getElementById("editTags");
	#currentMedia     = null;
	#submitEditMedia = document.getElementById("submitEditMedia");
	#flashMessageHandler = null;

    constructor(mediaService, flashMessageHandler)
    {
        this.#mediaService   = mediaService;
		this.#flashMessageHandler = flashMessageHandler;
        this.#addCancelEvent();
        this.#addSaveEvent();
    }

    /**
     * With a suggestion of the AI its description is added to the one of the medium and its tags to the ones
     * of the medium. The user changes them and saves, or cancels and nothing changed.
     */
    show(currentMedia, suggestion = null)
    {
		this.#currentMedia = currentMedia;
		const img   = currentMedia.querySelector("img");
		let description = img.getAttribute("data-description") ?? "";
		let tags        = this.#splitTags(img.getAttribute("data-tags") ?? "");
		if (suggestion !== null)
		{
			if (suggestion.description !== "")
				description = description.trim() === "" ? suggestion.description : description.trim() + "\n" + suggestion.description;

			const known = tags.map(tag => tag.toLowerCase());
			tags = tags.concat(suggestion.tags.filter(tag => !known.includes(tag.toLowerCase())));
		}
		this.#editFilename.value    = img.getAttribute("data-title");
		this.#editDescription.value = description;
		this.#editTags.value        = tags.join(", ");
        this.#dialogElement.showModal();

    }

	#splitTags(text)
	{
		return text.split(",").map(tag => tag.trim()).filter(tag => tag !== "");
	}

    #addSaveEvent()
    {
        this.#submitEditMedia.addEventListener('click', async () => {
			try
			{
				await this.#mediaService.editMedia(
					this.#currentMedia.getAttribute("data-media-id"),
					this.#editFilename.value,
					this.#editDescription.value,
					this.#splitTags(this.#editTags.value)
				)
			}
			catch (err)
			{
				this.#flashMessageHandler.showError(err.message);
				return;
			}
			this.#currentMedia.querySelector(".media-filename").textContent = this.#editFilename.value;
			this.#currentMedia.querySelector("img").setAttribute("data-title", this.#editFilename.value);
			this.#currentMedia.querySelector("img").setAttribute("data-description", this.#editDescription.value);
			this.#currentMedia.querySelector("img").setAttribute("data-tags", this.#splitTags(this.#editTags.value).join(", "));

        });
    }

    #addCancelEvent()
    {
        this.#closeElement.addEventListener("click", () => {
            this.#dialogElement.close("cancel");
        });
    }
}