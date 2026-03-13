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

export class TextPropertiesService
{
	#fabricWrapper;

	constructor(fabricWrapper)
	{
		this.#fabricWrapper = fabricWrapper;
	}

	getTextFontFamily()
	{
		const object = this.#getActiveObject();
		const styles = object.getSelectionStyles(object.isEditing ? object.selectionStart : 0, object.isEditing ? object.selectionEnd : 9, false)
		return styles && styles[0] ? styles[0].fontFamily : object.fontFamily
	}

	setTextFontFamily(fontFamily)
	{
		const object = this.#getActiveObject();
		object.setSelectionStyles({ fontFamily: fontFamily }, object.selectionStart === object.selectionEnd ? 0 : object.selectionStart, object.selectionStart === object.selectionEnd ? object.text.length : object.selectionEnd)
		this.#fabricWrapper.fireObjectModified(object);
		this.#fabricWrapper.renderAll();
	}

	setTextAlign()
	{
		const object = this.#getActiveObject();
		const positions = ["left", "center", "right", "left"]
		const currentIndex = positions.findIndex((v) => v === object.textAlign)
		const nextAlign = positions[currentIndex + 1]

		this.textAlign.update("display", nextAlign)

		object.set("textAlign", nextAlign)
	}

	getTextAlign()
	{
		const object = this.#getActiveObject();
		return object.textAlign
	}

	getTextBold()
	{
		const object = this.#getActiveObject();
		const styles = object.getSelectionStyles(object.isEditing ? object.selectionStart : 0, object.isEditing ? object.selectionEnd : 1, true)
		return styles && styles[0] ? styles[0].fontWeight : object.fontWeight
	}

	setTextBold()
	{
		const object = this.#getActiveObject();
		const nextBold = this.getTextBold(object) === 'bold' ? 'normal' : 'bold'

		this.textBold.update("active", nextBold)

		object.setSelectionStyles({ fontWeight: nextBold }, object.selectionStart === object.selectionEnd ? 0 : object.selectionStart, object.selectionStart === object.selectionEnd ? object.text.length : object.selectionEnd)
		this.#fabricWrapper.fireObjectModified(object);
		this.#fabricWrapper.renderAll();
	}

	getTextItalic()
	{
		const object = this.#getActiveObject();
		const styles = object.getSelectionStyles(object.isEditing ? object.selectionStart : 0, object.isEditing ? object.selectionEnd : 1, true)
		return styles && styles[0] ? styles[0].fontStyle : object.fontStyle
	}

	setTextItalic()
	{
		const object = this.#getActiveObject();
		let nextItalic = this.getTextItalic(object) === 'italic' ? 'normal' : 'italic'

		this.textItalic.update("active", nextItalic)

		object.setSelectionStyles({ fontStyle: nextItalic }, object.selectionStart === object.selectionEnd ? 0 : object.selectionStart, object.selectionStart === object.selectionEnd ? object.text.length : object.selectionEnd)
		this.#fabricWrapper.fireObjectModified(object);
		this.#fabricWrapper.renderAll();
	}

	justUpdateCanvas()
	{
		const object = this.#getActiveObject();
		this.#fabricWrapper.fireObjectModified(object);
		this.#fabricWrapper.renderAll();
	}


	setTextUnderline()
	{
		const object = this.#getActiveObject();
		const nextUnderline = !object.underline

		this.textUnderline.update("active", nextUnderline ? 'underline' : 'normal')

		// object.setSelectionStyles({ underline: nextUnderline }, object.selectionStart === object.selectionEnd ? 0 : object.selectionStart, object.selectionStart === object.selectionEnd ? object.text.length : object.selectionEnd)
		object.set("underline", nextUnderline)
	}

	getTextUnderline()
	{
		const object = this.#getActiveObject();
		const styles = object.getSelectionStyles(object.isEditing ? object.selectionStart : 0, object.isEditing ? object.selectionEnd : 1, true)
		return styles && styles[0] ? styles[0].fontStyle : object.fontStyle
	}

	#getActiveObject()
	{
		const object = this.#fabricWrapper.getActiveObject();
		if (!object) throw new Error("No active object");
		return object;
	}

}
