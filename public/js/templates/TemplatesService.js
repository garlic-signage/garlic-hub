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

import {BaseService}        from "../core/Base/BaseService.js";
import {FetchClient}        from "../core/FetchClient.js";
import {TemplatesApiConfig} from "./TemplatesApiConfig.js";
import {PlayerApiConfig}    from "../player/PlayerApiConfig.js";

export class TemplatesService extends BaseService
{

	async delete(templateId)
	{
		const url =TemplatesApiConfig.BASE_URI;
		const data = {
			template_id: templateId
		};
		return await this._sendRequest(url, "DELETE", data);
	}

	async loadTemplateContent(templateId)
	{
		const url =TemplatesApiConfig.BASE_URI + '/' + templateId;
		return await this._sendRequest(url, "GET");
	}

	async loadPlaylistItemContent(itemId)
	{
		const url =TemplatesApiConfig.LOAD_PLAYLIST_ITEM_URI + '/' + itemId;
		return await this._sendRequest(url, "GET");
	}


	async find()
	{
		const url =TemplatesApiConfig.FIND_URI;
		return await this._sendRequest(url, "GET");
	}

	async installPack()
	{
		return await this._sendRequest(TemplatesApiConfig.PACK_URI, "POST");
	}

	/**
	 * @return {Promise<{success: boolean, template_id?: number, error_message?: string}>}
	 */
	async copy(templateId)
	{
		return await this._sendRequest(TemplatesApiConfig.COPY_URI, "POST", {template_id: templateId});
	}

	async correctText(text)
	{
		return await this._sendRequest(TemplatesApiConfig.CORRECT_TEXT_URI, "POST", {text: text}, FetchClient.LLM_TIMEOUT);
	}

	async shortenText(text, maxLength)
	{
		return await this._sendRequest(TemplatesApiConfig.SHORTEN_TEXT_URI, "POST", {text: text, max_length: maxLength}, FetchClient.LLM_TIMEOUT);
	}

	async translateText(text, locale)
	{
		return await this._sendRequest(TemplatesApiConfig.TRANSLATE_TEXT_URI, "POST", {text: text, locale: locale}, FetchClient.LLM_TIMEOUT);
	}

	/**
	 * @return {Promise<{success: boolean, images?: {media_id: string, filename: string, file_name: string, original: string, thumbnail: string}[], error_message?: string}>}
	 */
	async suggestImages(texts)
	{
		return await this._sendRequest(TemplatesApiConfig.SUGGEST_IMAGES_URI, "POST", {texts: texts}, FetchClient.LLM_TIMEOUT);
	}

	determineRights(templateId)
	{
		const url = TemplatesApiConfig.ACLS_URI + "/" + templateId;
		return this._sendRequest(url, "GET");
	}

	async saveTemplateContent(templateId, content, image)
	{
		const url =TemplatesApiConfig.BASE_URI
		const data = {
			template_id: templateId,
			content: content,
			image: image
		};
		return await this._sendRequest(url, "PATCH", data);
	}

	async savePlaylistItemContent(itemId, content, image)
	{
		const url =TemplatesApiConfig.SAVE_PLAYLIST_ITEM_URI
		const data = {
			item_id: itemId,
			content: content,
			image: image
		};
		return await this._sendRequest(url, "PATCH", data);
	}

}

