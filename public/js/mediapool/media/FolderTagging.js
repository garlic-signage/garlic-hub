/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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
 * Tags the pictures, videos and PDFs of a folder which have no tags yet, one after the other from the browser:
 * the suggestion of the AI is saved at once, the tags as they are and the description added to the one there.
 * The subfolders are not part of it. A dialog shows the progress and cancels after the file in work.
 */
export class FolderTagging
{
	#dialogElement   = document.getElementById("folderTaggingDialog");
	#statusElement   = document.getElementById("folderTaggingStatus");
	#progressElement = document.getElementById("folderTaggingProgress");
	#cancelElement   = document.getElementById("cancelFolderTagging");
	#mediaService;
	#flashMessageHandler;
	#lang;
	#isCancelled = false;

	constructor(mediaService, flashMessageHandler, lang)
	{
		this.#mediaService        = mediaService;
		this.#flashMessageHandler = flashMessageHandler;
		this.#lang                = lang;

		this.#cancelElement.addEventListener("click", () => {
			this.#isCancelled = true;
			this.#cancelElement.disabled = true;
		});
		// Escape would close the dialog while the work goes on
		this.#dialogElement.addEventListener("cancel", (event) => event.preventDefault());
	}

	/**
	 * @param onlyMediaIds the media of the folder to tag, e.g. the ones just uploaded, null for all
	 * @return {Promise<number>} the number of tagged files
	 */
	async run(nodeId, onlyMediaIds = null)
	{
		const media = (await this.#mediaService.loadMediaByNodeId(nodeId))
			.filter(medium => onlyMediaIds === null || onlyMediaIds.includes(medium.media_id))
			.filter(medium => medium.tags.length === 0 && FolderTagging.isTaggable(medium.mimetype));
		if (media.length === 0)
		{
			this.#flashMessageHandler.showInformation(this.#lang.tag_folder_nothing);
			return 0;
		}

		this.#isCancelled = false;
		this.#cancelElement.disabled = false;
		this.#progressElement.max = media.length;
		this.#showProgress(0, media.length);
		this.#dialogElement.showModal();

		const failed = [];
		let done     = 0;
		for (const medium of media)
		{
			if (this.#isCancelled)
				break;

			try
			{
				const suggestion = await this.#mediaService.suggestTags(medium.media_id);
				const description = [(medium.media_description ?? "").trim(), suggestion.description].filter(text => text !== "").join("\n");
				await this.#mediaService.editMedia(medium.media_id, medium.filename, description, suggestion.tags);
			}
			catch (err)
			{
				failed.push(medium.filename);
			}
			done++;
			this.#showProgress(done, media.length);
		}
		this.#dialogElement.close();

		const tagged = done - failed.length;
		if (tagged > 0)
			this.#flashMessageHandler.showSuccess(this.#lang.tag_folder_done.replace("{tagged}", String(tagged)));
		if (failed.length > 0)
			this.#flashMessageHandler.showError(this.#lang.tag_folder_failed.replace("{files}", failed.join(", ")));

		return tagged;
	}

	/**
	 * The AI sees an image: pictures, a frame of videos, the first page of PDFs (like TaggingImage::supports()).
	 */
	static isTaggable(mimetype)
	{
		if (mimetype === "image/svg+xml")
			return false;

		return mimetype.startsWith("image/") || mimetype.startsWith("video/") || mimetype === "application/pdf";
	}

	#showProgress(done, total)
	{
		this.#progressElement.value    = done;
		this.#statusElement.textContent = this.#lang.tag_folder_status.replace("{done}", String(done)).replace("{total}", String(total));
	}
}
