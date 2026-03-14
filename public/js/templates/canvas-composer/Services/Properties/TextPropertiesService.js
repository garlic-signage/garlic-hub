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

export class TextPropertiesService extends BasePropertyService
{
	constructor(fabricWrapper)
	{
		super(fabricWrapper);
	}

	getTextFontFamily()
	{
		const object = this._getActiveObject();
		const styles = object.getSelectionStyles(object.isEditing ? object.selectionStart : 0, object.isEditing ? object.selectionEnd : 9, false)
		return styles && styles[0] ? styles[0].fontFamily : object.fontFamily
	}

	setTextFontFamily(fontFamily)
	{
		const object = this._getActiveObject();
		object.setSelectionStyles({ fontFamily: fontFamily }, object.selectionStart === object.selectionEnd ? 0 : object.selectionStart, object.selectionStart === object.selectionEnd ? object.text.length : object.selectionEnd)
		this._updateCanvas(object);
	}

	setTextAlign()
	{
		const object = this._getActiveObject();
		const positions = ["left", "center", "right", "left"]
		const currentIndex = positions.findIndex((v) => v === object.textAlign)
		const nextAlign = positions[currentIndex + 1]
		object.set("textAlign", nextAlign)
		this._updateCanvas(object);

	}

	getTextAlign()
	{
		const object = this._getActiveObject();
		return object.textAlign
	}

	getTextBold()
	{
		const object = this._getActiveObject();
		const styles = object.getSelectionStyles(object.isEditing ? object.selectionStart : 0, object.isEditing ? object.selectionEnd : 1, true)
		return styles && styles[0] ? styles[0].fontWeight : object.fontWeight
	}

	setTextBold()
	{
		const object = this._getActiveObject();
		const nextBold = this.getTextBold(object) === 'bold' ? 'normal' : 'bold'
		object.setSelectionStyles({ fontWeight: nextBold }, object.selectionStart === object.selectionEnd ? 0 : object.selectionStart, object.selectionStart === object.selectionEnd ? object.text.length : object.selectionEnd)
		this._updateCanvas(object);
	}

	getTextItalic()
	{
		const object = this._getActiveObject();
		const styles = object.getSelectionStyles(object.isEditing ? object.selectionStart : 0, object.isEditing ? object.selectionEnd : 1, true)
		return styles && styles[0] ? styles[0].fontStyle : object.fontStyle
	}

	setTextItalic()
	{
		const object = this._getActiveObject();
		let nextItalic = this.getTextItalic(object) === 'italic' ? 'normal' : 'italic'
		object.setSelectionStyles({ fontStyle: nextItalic }, object.selectionStart === object.selectionEnd ? 0 : object.selectionStart, object.selectionStart === object.selectionEnd ? object.text.length : object.selectionEnd)
		this._updateCanvas(object);
	}

	justUpdateCanvas()
	{
		const object = this._getActiveObject();
		this._updateCanvas(object);
	}


	setTextUnderline()
	{
		const object = this._getActiveObject();
		const nextUnderline = !object.underline

		// object.setSelectionStyles({ underline: nextUnderline }, object.selectionStart === object.selectionEnd ? 0 : object.selectionStart, object.selectionStart === object.selectionEnd ? object.text.length : object.selectionEnd)
		object.set("underline", nextUnderline)
		this._updateCanvas(object);
	}

	getTextUnderline()
	{
		const object = this._getActiveObject();
		const styles = object.getSelectionStyles(object.isEditing ? object.selectionStart : 0, object.isEditing ? object.selectionEnd : 1, true)
		return styles && styles[0] ? styles[0].fontStyle : object.fontStyle
	}
}
