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
 * The crop mode of an image: the whole picture shows half transparent around its frame. Dragging moves the picture
 * in the frame, the corners zoom it, the edges cut the frame. The controls come from the cropping extension of
 * fabric.js (public/js/external/fabric-extensions.min.js); moving and the ghost picture are taken from its
 * enterCropMode(), which is a demo that ends only by a double click.
 *
 * Apply keeps the cut as one step of the history, cancel brings the image back as it was.
 */
export class CropService
{
	/** what the crop mode changes and cancel restores */
	static STATE = ["left", "top", "width", "height", "cropX", "cropY", "scaleX", "scaleY"];

	#fabricWrapper;
	#image    = null;
	#saved    = null;
	#onMoving = null;
	#onRender = null;

	constructor(fabricWrapper)
	{
		this.#fabricWrapper = fabricWrapper;
	}

	get image()
	{
		return this.#image;
	}

	isCropping()
	{
		return this.#image !== null;
	}

	static isCroppable(object)
	{
		return object instanceof fabric.FabricImage;
	}

	enter(image)
	{
		if (!CropService.isCroppable(image) || this.#image === image)
			return;
		if (this.#image !== null)
			this.apply();

		this.#image = image;
		this.#saved = {
			state: Object.fromEntries(CropService.STATE.map(key => [key, image[key]])),
			clipScale: image.clipPath ? {scaleX: image.clipPath.scaleX, scaleY: image.clipPath.scaleY} : null,
			controls: image.controls,
			padding: image.padding,
			lockMovementX: image.lockMovementX,
			lockMovementY: image.lockMovementY
		};

		this.#fabricWrapper.pauseHistory();
		this.#onMoving = (event) => CropService.#panInFrame(event);
		this.#onRender = (event) => this.#renderGhost(image, event.ctx);
		image.set({padding: 0, lockMovementX: false, lockMovementY: false});
		image.controls = fabricExtensions.createImageCroppingControls();
		image.on("moving", this.#onMoving);
		image.on("before:render", this.#onRender);
		image.setCoords();
		this.#fabricWrapper.setActiveObject(image);
		this.#fabricWrapper.renderAll();
	}

	/**
	 * Keeps the cut. A changed one is one step of the history.
	 */
	apply()
	{
		const image = this.#image;
		if (image === null)
			return;

		const changed = CropService.STATE.some(key => image[key] !== this.#saved.state[key]);
		this.#keepClipSize(image);
		this.#exit();
		this.#fabricWrapper.resumeHistory();
		if (changed)
			this.#fabricWrapper.fireObjectModified(image);
		this.#fabricWrapper.renderAll();
	}

	cancel()
	{
		const image = this.#image;
		if (image === null)
			return;

		image.set(this.#saved.state);
		if (this.#saved.clipScale !== null)
			image.clipPath.set(this.#saved.clipScale);
		this.#exit();
		this.#fabricWrapper.resumeHistory();
		this.#fabricWrapper.renderAll();
	}

	#exit()
	{
		const image = this.#image;
		image.off("moving", this.#onMoving);
		image.off("before:render", this.#onRender);
		image.controls = this.#saved.controls;
		image.set({padding: this.#saved.padding, lockMovementX: this.#saved.lockMovementX, lockMovementY: this.#saved.lockMovementY});
		image.setCoords();
		this.#image = null;
		this.#saved = null;
	}

	#keepClipSize(image)
	{
		if (this.#saved.clipScale === null)
			return;

		const factor = this.#saved.state.scaleX / image.scaleX;
		image.clipPath.set({scaleX: this.#saved.clipScale.scaleX * factor, scaleY: this.#saved.clipScale.scaleY * factor});
	}

	/**
	 * Dragging moves the picture inside its frame, the frame stays (cropPanMoveHandler of the extension).
	 */
	static #panInFrame({transform})
	{
		const {target, original} = transform;
		const p = new fabric.Point(target.left - original.left, target.top - original.top)
			.transform(fabric.util.invertTransform(fabric.util.createRotateMatrix({angle: target.getTotalAngle()})));
		const element = target.getElement();
		let cropX = original.cropX - p.x / target.scaleX * (target.flipX ? -1 : 1);
		let cropY = original.cropY - p.y / target.scaleY * (target.flipY ? -1 : 1);
		cropX = Math.max(0, Math.min(cropX, element.width - target.width));
		cropY = Math.max(0, Math.min(cropY, element.height - target.height));
		target.cropX = cropX;
		target.cropY = cropY;
		target.left  = original.left;
		target.top   = original.top;
	}

	/**
	 * The whole picture half transparent behind the frame (renderGhostImage of the extension). A round clip path
	 * keeps its size on the template while the corners zoom the picture.
	 */
	#renderGhost(image, ctx)
	{
		this.#keepClipSize(image);

		const element = image.getElement();
		const ghostX  = -image.width / 2 - image.cropX;
		const ghostY  = -image.height / 2 - image.cropY;
		const alpha   = ctx.globalAlpha;
		ctx.globalAlpha *= 0.5;
		ctx.drawImage(element, ghostX, ghostY);
		ctx.strokeStyle = image.borderColor;
		ctx.lineWidth   = image.borderScaleFactor / image.scaleX;
		ctx.strokeRect(ghostX, ghostY, element.width, element.height);
		ctx.globalAlpha = alpha;
	}
}
