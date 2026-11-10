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

import { AbstractPreview } from "./AbstractPreview.js";

export class MiscellaneousPreview extends AbstractPreview
{
	createPreview()
	{
		return this.createPreviewElement();
	}

	createPreviewElement()
	{
		const img  = document.createElement("img");
		const name = this.getFile().name.toLowerCase();
		if (name.endsWith(".csv") || name.endsWith(".json") || name.endsWith(".xml"))
			img.src = "./images/icons/database.svg";
		else
			img.src = "./images/icons/file.svg";

		return img;
	}
}
