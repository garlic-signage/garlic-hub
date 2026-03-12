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
'use strict';

export class ComposerContext
{
	#templateId = 0;
	#playlistId = 0;
	#itemId     = 0;
	#lang;

	constructor(lang)
	{
		this.#templateId = document.getElementById('template_id').value;
		if (document.getElementById('playlist_id') !== null)
			this.#playlistId = document.getElementById('playlist_id').value;
		if(document.getElementById('item_id') !== null)
			this.#itemId     = document.getElementById('item_id').value;
		this.#lang = lang;
	}

	get templateId()
	{
		return this.#templateId;
	}

	get playlistId()
	{
		return this.#playlistId;
	}

	get itemId()
	{
		return this.#itemId;
	}

	getLangByKey(key)
	{
		return lang[key];
	}
}