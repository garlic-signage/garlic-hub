class CanvasView {
	canvas = {};
	percent = document.getElementById("percent");
	slider = document.getElementById("slider");
	insert_text = document.getElementById("insert_text");
	insert_image = document.getElementById("insert_image");
	canvas_wrap = document.getElementById("canvas_wrap");
	insert_circle = document.getElementById("object_add_circle");
	insert_triangle = document.getElementById("object_add_triangle");
	insert_rectangle = document.getElementById("object_add_rectangle");
	insert_polygon = document.getElementById("object_add_polygon");
	insert_hexagon = document.getElementById("object_add_hexagon");
	insert_octagon = document.getElementById("object_add_octagon");
	undo = document.getElementById("undo");
	redo = document.getElementById("redo");
	width = 0;
	height = 0;
	changed = false;
	lang = {};
	_clipboard = {};

	constructor(canvas, lang)
	{
		this.canvas = canvas;
		this.lang = lang;
	}

	addCanvas(object)
	{
		this.canvas.add(object);
	}

	getWidth()
	{
		return this.width;
	}

	getHeight()
	{
		return this.height;
	}

	getUndo()
	{
		return undo
	}

	getRedo()
	{
		return redo
	}

	getInsertText()
	{
		return insert_text;
	}

	getInsertImage()
	{
		return insert_image;
	}

	getInsertCircle()
	{
		return this.insert_circle;
	}

	getInsertTriangle()
	{
		return this.insert_triangle;
	}

	getInsertRectangle()
	{
		return this.insert_rectangle;
	}

	getInsertPolygon()
	{
		return this.insert_polygon;
	}

	getInsertHexagon()
	{
		return this.insert_hexagon;
	}

	getInsertOctagon()
	{
		return this.insert_octagon;
	}

	getTextProperties()
	{
		return this.text_properties
	}

	getSlider()
	{
		return slider;
	}

	getLangByKey(key)
	{
		return lang[key];
	}

	setChanged(val)
	{
		this.changed = val;
	}

	hasChanged()
	{
		return this.changed;
	}

	renderCanvas()
	{
		this.canvas.renderAll();
	}

	dublicateActiveObject()
	{
		this.copyActiveObjectToClipboard();
		this.pasteFromClipboardToPos(this._clipboard.left + 20, this._clipboard.top + 20)
	}

	removeActiveObject()
	{
		let object = this.getActiveObject();
		if (object == null)
			return;

		return this.removeObject(object);
	}

	removeObject(object)
	{
		return this.canvas.remove(object);
	}

	moveActiveObject(direction, step = 50)
	{
		if (this.getActiveObject() === undefined)
			return;

		switch (direction) {
			case "ArrowLeft":
				this.getActiveObject().left -= step;
				break;
			case "ArrowRight":
				this.getActiveObject().left += step;
				break;
			case "ArrowUp":
				this.getActiveObject().top -= step;
				break;
			case "ArrowDown":
				this.getActiveObject().top += step;
				break;
			default:
				break;
		}
		this.getCanvas().fire('object:modified');
	}

	isLockedInSelection()
	{
		let activeObjects = this.canvas.getActiveObjects();
		for (let i in activeObjects) {
			if (this.isLocked(activeObjects[i]))
				return true;
		}
		return false;
	}

	isCurrentLocked()
	{
		return this.isLocked(this.canvas.getActiveObject());
	}

	setCurrentLockedStatus(is_lock)
	{
		this.setLockedStatus(this.canvas.getActiveObject(), is_lock);
	}

	isLocked(object)
	{
		return object.lockMovementX;
	}

	setLockedStatus(object, is_lock)
	{
		object.lockMovementX = is_lock;
		object.lockMovementY = is_lock;
		object.lockSkewingX = is_lock;
		object.lockSkewingY = is_lock;
		object.lockRotation = is_lock;
		object.lockScalingX = is_lock;
		object.lockScalingY = is_lock;
	}

	getActiveObject()
	{
		return this.canvas.getActiveObject();
	}

	setActiveObject(object)
	{
		return this.canvas.setActiveObject(object);
	}

	copyActiveObjectToClipboard()
	{
		let object = this.getActiveObject();
		if (object == null)
			return;

		object.clone(cloned =>
		{
			this._clipboard = cloned;
		});
	}

	pasteFromClipboardToPos(x, y)
	{
		if (this._clipboard == null)
			return;

		this._clipboard.clone(cloned =>
		{
			this.canvas.discardActiveObject();
			cloned.set({
				left: x,
				top: y,
				evented: true,
			});
			if (cloned.type === 'activeSelection')
			{
				cloned.canvas = this.canvas;
				cloned.forEachObject((obj) =>
				{
					this.canvas.add(obj);
				});
				// this should solve the unselectability
				cloned.setCoords();
			}
			else
				this.canvas.add(cloned);

			this.canvas.setActiveObject(cloned);
			this.canvas.requestRenderAll();
		});


	}


	getCanvas()
	{
		return this.canvas;
	}

	getCanvasWrap()
	{
		return this.canvas_wrap;
	}

	setCanvasDimensions(width, height)
	{
		this.width = width;
		this.height = height;

		this.canvas.setWidth(this.width);
		this.canvas.setHeight(this.height);
	}

	scalePercent()
	{
		this.percent.innerHTML = this.slider.value + ' %';
	}

	scaleCanvas()
	{
		let zoom = this.slider.value;
		this.canvas.setZoom(zoom / 100);
		this.canvas.setWidth(Math.floor(this.width / 100 * zoom))
		this.canvas.setHeight(Math.floor(this.height / 100 * zoom));
	}

}