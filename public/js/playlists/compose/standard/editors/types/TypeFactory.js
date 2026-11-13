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
'use strict';

import {NotifyEdit} from "./NotifyEdit.js";
import {TouchEdit}  from "./TouchEdit.js";
import {AccesskeyEdit} from "./AccesskeyEdit.js";
import {WallclockEdit} from "./WallclockEdit.js";

export class TypeFactory
{
	#triggerService = null;

	constructor(triggerService)
	// Stryker disable next-line BlockStatement: nothing uses the service yet
	{
		this.#triggerService = triggerService;
	}

	create(type)
	{
		switch (type)
		{
			case "wallclock":
				return new WallclockEdit();
			case "accesskey":
				return new AccesskeyEdit();
			case "touch":
				return new TouchEdit();
			case "notify":
				return new NotifyEdit();
		}
	}

}
