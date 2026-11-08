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
 * Three rules for screens which the composer can measure, as hints, nothing is blocked:
 * - the contrast of a text to what is behind it (WCAG: 4.5:1, 3:1 for large text),
 * - the font size against the height of the template (seen from a distance),
 * - the time to read all texts against the duration of the item.
 * All sizes are in the units of the template, without the zoom of the composer.
 */
export class DesignCheckService
{
	static MIN_CONTRAST       = 4.5;
	static MIN_CONTRAST_LARGE = 3;
	/** a text of at least this part of the height counts as large */
	static LARGE_FONT_SHARE   = 1 / 20;
	/** smaller is hard to read from a few meters */
	static MIN_FONT_SHARE     = 1 / 30;
	/** reading on a screen in passing: a moment to look, then about three words a second */
	static ORIENTATION_SECONDS = 2;
	static WORDS_PER_SECOND    = 3;

	#fabricWrapper;
	#viewportService;
	#duration;

	constructor(fabricWrapper, viewportService, duration)
	{
		this.#fabricWrapper   = fabricWrapper;
		this.#viewportService = viewportService;
		this.#duration        = duration;
	}

	/**
	 * @return {{object: (Object|null), key: string, values: Object}[]} the hints, object null for the whole template
	 */
	check()
	{
		const hints = [];
		const texts = this.#fabricWrapper.getObjects().filter(object => DesignCheckService.isText(object) && object.visible !== false && object.text.trim() !== "");
		for (const text of texts)
		{
			const fontSize = Math.round(text.fontSize * (text.scaleY ?? 1));
			const minSize  = Math.ceil(this.#viewportService.height * DesignCheckService.MIN_FONT_SHARE);
			if (fontSize < minSize)
				hints.push({object: text, key: "design_small_font", values: {size: fontSize, min: minSize}});

			const ratio       = this.#contrastRatio(text);
			const minContrast = fontSize >= this.#viewportService.height * DesignCheckService.LARGE_FONT_SHARE ? DesignCheckService.MIN_CONTRAST_LARGE : DesignCheckService.MIN_CONTRAST;
			if (ratio !== null && ratio < minContrast)
				hints.push({object: text, key: "design_low_contrast", values: {ratio: ratio.toFixed(1), min: minContrast}});
		}

		const words  = texts.reduce((sum, text) => sum + text.text.split(/\s+/).filter(word => word !== "").length, 0);
		const needed = Math.ceil(DesignCheckService.ORIENTATION_SECONDS + words / DesignCheckService.WORDS_PER_SECOND);
		if (words > 0 && needed > this.#duration)
			hints.push({object: null, key: "design_reading_time", values: {needed: needed, duration: this.#duration}});

		return hints;
	}

	static isText(object)
	{
		return ["text", "i-text", "textbox"].includes(object.type);
	}

	/**
	 * The contrast of the color of the text to the average of what is drawn behind it: the area of the text is
	 * rendered without the text. null when it can not be measured, e.g. a gradient or an image of another origin.
	 */
	#contrastRatio(text)
	{
		if (typeof text.fill !== "string")
			return null;

		const canvas = this.#fabricWrapper.fabricCanvas;
		const rect   = text.getBoundingRect();
		let pixels;
		text.visible = false;
		try
		{
			const area = canvas.toCanvasElement(1, {left: rect.left, top: rect.top, width: Math.max(1, rect.width), height: Math.max(1, rect.height)});
			pixels = area.getContext("2d").getImageData(0, 0, area.width, area.height).data;
		}
		catch
		{
			return null;
		}
		finally
		{
			text.visible = true;
		}

		let sum   = 0;
		let count = 0;
		// every fourth pixel is enough for an average
		for (let i = 0; i < pixels.length; i += 16)
		{
			// a transparent background is white, like the canvas of the composer
			const alpha = pixels[i + 3] / 255;
			sum += DesignCheckService.#luminance(
				pixels[i] * alpha + 255 * (1 - alpha),
				pixels[i + 1] * alpha + 255 * (1 - alpha),
				pixels[i + 2] * alpha + 255 * (1 - alpha)
			);
			count++;
		}
		if (count === 0)
			return null;

		const [r, g, b] = new fabric.Color(text.fill).getSource();
		const textLuminance       = DesignCheckService.#luminance(r, g, b);
		const backgroundLuminance = sum / count;

		return (Math.max(textLuminance, backgroundLuminance) + 0.05) / (Math.min(textLuminance, backgroundLuminance) + 0.05);
	}

	/**
	 * The relative luminance of WCAG.
	 */
	static #luminance(r, g, b)
	{
		const channel = (value) =>
		{
			const c = value / 255;
			return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
		};

		return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
	}
}
