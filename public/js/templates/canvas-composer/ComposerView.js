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

/**
 * Collect all UI Elements, create listener which emit custom signals
 * for use in Controller
 */
export class ComposerView  extends EventTarget
{
	#canvasWrap = document.getElementById("canvas_wrap");

	#textProperties = document.getElementById("text_properties");
	#fontFamily = document.getElementById("font_family")
	#positionLeft   = document.getElementById("object_align_left")
	#positionCenter = document.getElementById("object_align_center")
	#positionRight  = document.getElementById("object_align_right")
	#positionTop    = document.getElementById("object_align_top")
	#positionMiddle = document.getElementById("object_align_middle")
	#positionBottom = document.getElementById("object_align_bottom")
	#objectOpacity  = document.getElementById("object_opacity")
	#strokeColor    = document.getElementById("stroke_color")
	#strokeWidth    = document.getElementById("stroke_width")
	#fillColor    = document.getElementById("fill_color");
	#textAlign = document.getElementById("text_align");
	#textBold = document.getElementById("text_bold");
	#textItalic = document.getElementById("text_italic");
	#textUnderline = document.getElementById("text_underline");
	#group = document.getElementById("object_group");
	#lang;

	constructor(lang)
	{
		super();
		this.#lang = lang;
		this.#initEventListeners();
	}

	getLangByKey(key)
	{
		return this.#lang[key];
	}

	#initEventListeners()
	{
		// Buttons

		// Insert shapes
		this.#insertCircle?.addEventListener('click', () =>
		{
			this.dispatchEvent(new CustomEvent('insertCircleChanged', {detail: {}}));
		});

		this.#insertTriangle?.addEventListener('click', () =>
		{
			this.dispatchEvent(new CustomEvent('insertTriangleChanged', {detail: {}}));
		});

		this.#insertRectangle?.addEventListener('click', () =>
		{
			this.dispatchEvent(new CustomEvent('insertRectangleChanged', {detail: {}}));
		});

		this.#insertPolygon?.addEventListener('click', () =>
		{
			this.dispatchEvent(new CustomEvent('insertPolygonChanged', {detail: {}}));
		});

		this.#insertHexagon?.addEventListener('click', () =>
		{
			this.dispatchEvent(new CustomEvent('insertHexagonChanged', {detail: {}}));
		});

		this.#insertOctagon?.addEventListener('click', () =>
		{
			this.dispatchEvent(new CustomEvent('insertOctagonChanged', {detail: {}}));
		});


		// Font
		this.#fontFamily?.addEventListener('change', () =>
		{
			this.dispatchEvent(new CustomEvent('fontFamilyChanged', {detail: {value: this.#fontFamily.value}}));
		});

		// Position alignment
		this.#positionLeft?.addEventListener('click', () =>
		{
			this.dispatchEvent(new CustomEvent('positionLeftChanged', {detail: {}}));
		});

		this.#positionCenter?.addEventListener('click', () =>
		{
			this.dispatchEvent(new CustomEvent('positionCenterChanged', {detail: {}}));
		});

		this.#positionRight?.addEventListener('click', () =>
		{
			this.dispatchEvent(new CustomEvent('positionRightChanged', {detail: {}}));
		});

		this.#positionTop?.addEventListener('click', () =>
		{
			this.dispatchEvent(new CustomEvent('positionTopChanged', {detail: {}}));
		});

		this.#positionMiddle?.addEventListener('click', () =>
		{
			this.dispatchEvent(new CustomEvent('positionMiddleChanged', {detail: {}}));
		});

		this.#positionBottom?.addEventListener('click', () =>
		{
			this.dispatchEvent(new CustomEvent('positionBottomChanged', {detail: {}}));
		});

		// Object properties
		this.#objectOpacity?.addEventListener('input', () =>
		{
			this.dispatchEvent(new CustomEvent('objectOpacityChanged', {detail: {value: this.#objectOpacity.value}}));
		});

		this.#strokeColor?.addEventListener('input', () =>
		{
			this.dispatchEvent(new CustomEvent('strokeColorChanged', {detail: {value: this.#strokeColor.value}}));
		});

		this.#strokeWidth?.addEventListener('input', () =>
		{
			this.dispatchEvent(new CustomEvent('strokeWidthChanged', {detail: {value: this.#strokeWidth.value}}));
		});

		this.#fillColor?.addEventListener('input', () =>
		{
			this.dispatchEvent(new CustomEvent('fillColorChanged', {detail: {value: this.#fillColor.value}}));
		});

		// Text properties
		this.#textAlign?.addEventListener('change', () =>
		{
			this.dispatchEvent(new CustomEvent('textAlignChanged', {detail: {value: this.#textAlign.value}}));
		});

		this.#textBold?.addEventListener('click', () =>
		{
			this.dispatchEvent(new CustomEvent('textBoldChanged', {detail: {}}));
		});

		this.#textItalic?.addEventListener('click', () =>
		{
			this.dispatchEvent(new CustomEvent('textItalicChanged', {detail: {}}));
		});

		this.#textUnderline?.addEventListener('click', () =>
		{
			this.dispatchEvent(new CustomEvent('textUnderlineChanged', {detail: {}}));
		});

		// Group
		this.#group?.addEventListener('click', () =>
		{
			this.dispatchEvent(new CustomEvent('groupChanged', {detail: {}}));
		});
	}

}
