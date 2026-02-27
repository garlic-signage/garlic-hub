class GlobalProperties
{
	MyCanvasView   = {};
	positionLeft   = document.getElementById("object_align_left")
	positionCenter = document.getElementById("object_align_center")
	positionRight  = document.getElementById("object_align_right")
	positionTop    = document.getElementById("object_align_top")
	positionMiddle = document.getElementById("object_align_middle")
	positionBottom = document.getElementById("object_align_bottom")
	objectOpacity  = document.getElementById("object_opacity")
	strokeColor    = document.getElementById("stroke_color")
	strokeWidth    = document.getElementById("stroke_width")

	constructor(MyCanvasView)
	{
		this.MyCanvasView = MyCanvasView;
	}

	activate(object)
	{
		this.strokeColor.value = this.getStrokeColor(object) || "#000000"
		this.strokeWidth.value = this.getStrokeWidth(object)
		this.objectOpacity.value = this.getOpacity(object)
		document.getElementById("global_properties").style.display = "flex"
	}

	deactivate()
	{
		document.getElementById("global_properties").style.display = "none"
	}

	initEventListener()
	{

		this.positionLeft.addEventListener("click", () =>
		{
			let object = this.MyCanvasView.getCanvas().getActiveObject();
			this.setPosition(object, "left")
			this.MyCanvasView.renderCanvas();
		});
		this.positionCenter.addEventListener("click", () =>
		{
			let object = this.MyCanvasView.getCanvas().getActiveObject();
			this.setPosition(object, "center")
			this.MyCanvasView.renderCanvas();
		});
		this.positionRight.addEventListener("click", () =>
		{
			let object = this.MyCanvasView.getCanvas().getActiveObject();
			this.setPosition(object, "right")
			this.MyCanvasView.renderCanvas();
		});
		this.positionTop.addEventListener("click", () =>
		{
			let object = this.MyCanvasView.getCanvas().getActiveObject();
			this.setPosition(object, "top")
			this.MyCanvasView.renderCanvas();
		});
		this.positionMiddle.addEventListener("click", () =>
		{
			let object = this.MyCanvasView.getCanvas().getActiveObject();
			this.setPosition(object, "middle")
			this.MyCanvasView.renderCanvas();
		});
		this.positionBottom.addEventListener("click", () =>
		{
			let object = this.MyCanvasView.getCanvas().getActiveObject();
			this.setPosition(object, "bottom")
			this.MyCanvasView.renderCanvas();
		});
		this.objectOpacity.addEventListener("change", (event) =>
		{
			let object = this.MyCanvasView.getCanvas().getActiveObject();
			this.setOpacity(object, event.target.value)
			this.MyCanvasView.getCanvas().fire('object:modified', {target: object})
			this.MyCanvasView.renderCanvas();
		})
		this.strokeColor.addEventListener("input", (event) =>
		{
			let object = this.MyCanvasView.getCanvas().getActiveObject();
			this.setStrokeColor(object, event.target.value)
			this.MyCanvasView.renderCanvas();
		});
		this.strokeColor.addEventListener("change", (event) =>
		{
			let object = this.MyCanvasView.getCanvas().getActiveObject();
			this.setStrokeColor(object, event.target.value)
			this.MyCanvasView.getCanvas().fire('object:modified', {target: object})
			this.MyCanvasView.renderCanvas();
		});
		this.strokeWidth.addEventListener("change", (event) =>
		{
			let object = this.MyCanvasView.getCanvas().getActiveObject();
			this.setStrokeWidth(object, event.target.value)
			this.MyCanvasView.getCanvas().fire('object:modified', {target: object})
			this.MyCanvasView.renderCanvas();
		})

	}

//============== private methods ===========================================================

	getOpacity(object)
	{
		if (!object) return
		return object.opacity * 100
	}

	setOpacity(object, value)
	{
		if (!object) return
		object.set("opacity", Number(value) / 100)
	}

	getStrokeColor(object)
	{
		if (!object) return
		return object.stroke
	}

	setStrokeColor(object, value)
	{
		if (!object) return
		object.set("stroke", value)
	}

	getStrokeWidth(object)
	{
		if (!object) return
		return object.strokeWidth
	}

	setStrokeWidth(object, value)
	{
		if (!object) return
		object.set("strokeWidth", Number(value))
	}

	setPosition(object, position)
	{
		if (!object) return
		let canvas = this.MyCanvasView.getCanvas()

		// import from SVG has no viewport values. function fails so we take the values form CanvasView-class
		// Todo: delete this after test
		// let canvasBound = { width: canvas.viewport.width, height: canvas.viewport.height, center: { x: canvas.viewport.width / 2, y: canvas.viewport.height / 2 } }
		let canvasBound = {width: this.MyCanvasView.getWidth(), height: this.MyCanvasView.getHeight(), center: {x: this.MyCanvasView.getWidth() / 2, y: this.MyCanvasView.getHeight() / 2}}

		let s = object.getBoundingRect().height / canvas.viewportTransform[3],
			a = object.getBoundingRect().width / canvas.viewportTransform[0]
		switch (position)
		{
			case "top":
				object.setPositionByOrigin(new fabric.Point(object.getCenterPoint().x, s / 2 + canvasBound.center.y - canvasBound.height / 2), "center", "center")
				break
			case "middle":
				object.setPositionByOrigin({
					x: object.getCenterPoint().x,
					y: canvasBound.center.y
				}, "center", "center")
				break
			case "bottom":
				object.setPositionByOrigin(new fabric.Point(object.getCenterPoint().x, canvasBound.center.y + canvasBound.height / 2 - s / 2), "center", "center")
				break
			case "left":
				object.setPositionByOrigin(new fabric.Point(canvasBound.center.x - canvasBound.width / 2 + a / 2, object.getCenterPoint().y), "center", "center")
				break
			case "center":
				object.setPositionByOrigin({
					x: canvasBound.center.x,
					y: object.getCenterPoint().y
				}, "center", "center")
				break
			case "right":
				object.setPositionByOrigin(new fabric.Point(canvasBound.center.x + canvasBound.width / 2 - a / 2, object.getCenterPoint().y), "center", "center")
		}
		canvas.fire('object:modified', {target: object})
	}


}
