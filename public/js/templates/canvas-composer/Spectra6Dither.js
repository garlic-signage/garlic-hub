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

export class Spectra6Dither
{
	// Official Spectra 6 palette
	static PALETTE = [
		[0,   0,   0  ], // black
		[255, 255, 255], // white
		[255, 0,   0  ], // red
		[0,   255, 0  ], // green
		[0,   0,   255], // blue
		[255, 255, 0  ], // yello
	];

	#findClosestColor(r, g, b)
	{
		let minDist = Infinity;
		let closest = Spectra6Dither.PALETTE[0];

		for (const color of Spectra6Dither.PALETTE)
		{
			const dist = Math.pow(r - color[0], 2) +
				Math.pow(g - color[1], 2) +
				Math.pow(b - color[2], 2);
			if (dist < minDist) {
				minDist = dist;
				closest = color;
			}
		}
		return closest;
	}

	#floydSteinberg(imageData)
	{
		const { width, height, data } = imageData;

		for (let y = 0; y < height; y++) {
			for (let x = 0; x < width; x++) {
				const idx = (y * width + x) * 4;

				const oldR = data[idx];
				const oldG = data[idx + 1];
				const oldB = data[idx + 2];

				const [newR, newG, newB] = this.#findClosestColor(oldR, oldG, oldB);

				data[idx]     = newR;
				data[idx + 1] = newG;
				data[idx + 2] = newB;

				const errR = oldR - newR;
				const errG = oldG - newG;
				const errB = oldB - newB;

				// Fehler verteilen
				this.#distributeError(data, width, height, x + 1, y,     errR, errG, errB, 7 / 16);
				this.#distributeError(data, width, height, x - 1, y + 1, errR, errG, errB, 3 / 16);
				this.#distributeError(data, width, height, x,     y + 1, errR, errG, errB, 5 / 16);
				this.#distributeError(data, width, height, x + 1, y + 1, errR, errG, errB, 1 / 16);
			}
		}

		return imageData;
	}

	#distributeError(data, width, height, x, y, errR, errG, errB, factor)
	{
		if (x < 0 || x >= width || y < 0 || y >= height) return;
		const idx = (y * width + x) * 4;
		data[idx]     = Math.max(0, Math.min(255, data[idx]     + errR * factor));
		data[idx + 1] = Math.max(0, Math.min(255, data[idx + 1] + errG * factor));
		data[idx + 2] = Math.max(0, Math.min(255, data[idx + 2] + errB * factor));
	}

	#imageDataToBMP(imageData) {
		const { width, height, data } = imageData;
		const rowSize = Math.floor((24 * width + 31) / 32) * 4;
		const pixelArraySize = rowSize * height;
		const fileSize = 54 + pixelArraySize;

		const buffer = new ArrayBuffer(fileSize);
		const view = new DataView(buffer);

		// BMP Header
		view.setUint8(0, 0x42); // B
		view.setUint8(1, 0x4D); // M
		view.setUint32(2,  fileSize, true);
		view.setUint32(6,  0, true);
		view.setUint32(10, 54, true);

		// DIB Header
		view.setUint32(14, 40, true);
		view.setInt32(18,  width, true);
		view.setInt32(22,  -height, true); // negativ = top-down
		view.setUint16(26, 1, true);
		view.setUint16(28, 24, true);
		view.setUint32(30, 0, true);
		view.setUint32(34, pixelArraySize, true);
		view.setInt32(38,  2835, true);
		view.setInt32(42,  2835, true);
		view.setUint32(46, 0, true);
		view.setUint32(50, 0, true);

		// Pixel Daten (BGR)
		for (let y = 0; y < height; y++) {
			for (let x = 0; x < width; x++) {
				const srcIdx = (y * width + x) * 4;
				const dstIdx = 54 + y * rowSize + x * 3;
				view.setUint8(dstIdx,     data[srcIdx + 2]); // B
				view.setUint8(dstIdx + 1, data[srcIdx + 1]); // G
				view.setUint8(dstIdx + 2, data[srcIdx]);     // R
			}
		}

		return buffer;
	}

	/**
	 * Base64 DataURL (png/jpeg) -> BMP DataURL with Spectra6 Dithering
	 * @param {string} base64DataUrl
	 * @returns {Promise<string>} BMP as data:image/bmp;base64,...
	 */
	async convert(base64DataUrl)
	{
		return new Promise((resolve, reject) => {
			const img = new Image();
			img.onload = () => {
				const canvas = document.createElement('canvas');
				canvas.width  = img.width;
				canvas.height = img.height;

				const ctx = canvas.getContext('2d');
				ctx.drawImage(img, 0, 0);

				const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
				const dithered  = this.#floydSteinberg(imageData);
				const bmpBuffer = this.#imageDataToBMP(dithered);

				const bytes = new Uint8Array(bmpBuffer);
				let binary = '';
				for (let i = 0; i < bytes.length; i++) {
					binary += String.fromCharCode(bytes[i]);
				}
				const base64 = btoa(binary);
				resolve('data:image/bmp;base64,' + base64);
			};
			img.onerror = reject;
			img.src = base64DataUrl;
		});
	}
}