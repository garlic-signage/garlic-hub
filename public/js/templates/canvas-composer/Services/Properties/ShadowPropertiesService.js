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

import {BasePropertyService} from "./BasePropertyService.js";

export class ShadowPropertiesService extends BasePropertyService
{
	constructor(fabricWrapper)
	{
		super(fabricWrapper);
	}

	hasShadow()
	{
		const object = this._getActiveObject();
		return object.shadow !== null && object.shadow !== undefined;
	}

	getColor()
	{
		const object = this._getActiveObject();
		return object.shadow?.color || 'rgba(0, 0, 0, 0)';
	}

	setColor(value)
	{
		const object = this._getActiveObject();
		object.set('shadow', new fabric.Shadow({ ...object.shadow, color: value }));
	}

	getOffsetX()
	{
		const object = this._getActiveObject();
		return object.shadow?.offsetX || 0;
	}

	setOffsetX(value)
	{
		const object = this._getActiveObject();
		object.set('shadow', new fabric.Shadow({ ...object.offsetX, offsetX: value }));
	}

	getOffsetY()
	{
		const object = this._getActiveObject();
		return object.shadow?.offsetY || 0;
	}

	setOffsetY(value)
	{
		const object = this._getActiveObject();
		object.set('shadow', new fabric.Shadow({ ...object.offsetY, offsetY: value }));
	}

	getBlur()
	{
		const object = this._getActiveObject();
		return object.shadow?.blur || 0;
	}

	setBlur(value)
	{
		const object = this._getActiveObject();
		object.set('shadow', new fabric.Shadow({ ...object.blur, blur: value }));
	}

	delete(value)
	{
		const object = this._getActiveObject();
		object.set('shadow', null);	}

}
