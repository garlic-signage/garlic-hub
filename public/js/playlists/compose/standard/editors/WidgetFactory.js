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
import {WidgetsService} from "./WidgetsService.js";
import {FetchClient}    from "../../../../core/FetchClient.js";
import {WidgetForm}     from "./WidgetForm.js";
import {EditDialog}     from "./EditDialog.js";
import {Widget}         from "./Widget.js";

export class WidgetFactory
{
	create()
	{
		return new Widget(new EditDialog(), new WidgetForm(), new WidgetsService(new FetchClient()))
	}

}
