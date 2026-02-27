class SvgItemsParser
{
	svg_items = {};
	width     = 0;
	height    = 0;
	MyCanvasView  = {};

	constructor(MyCanvasView)
	{
		this.MyCanvasView = MyCanvasView;
	}

	getParsedSvgItems()
	{
		return this.svg_items;
	}

	getSvgItemsbyId(id)
	{
		return this.svg_items[id].item;
	}

	getSvgItems()
	{
		return this.svg_items;
	}

	outputTemplate(objects, options)
	{
		this.width  = options.width;
		this.height = options.height;
		this.MyCanvasView.setCanvasDimensions(options.width, options.height)
		for (let i = 0; i < objects.length; i++)
		{
			this.MyCanvasView.addCanvas(this.svg_items[objects[i].id].object);
		}
		this.zoomToViewPort();
	}

	outputJsonTemplate(width, height)
	{
		this.width  = width;
		this.height = height;
		this.MyCanvasView.setCanvasDimensions(width, height);
		this.zoomToViewPort();
	}

	zoomToViewPort()
	{
		this.MyCanvasView.slider.value = this.calculateZoomByBrowserViewPort();
		this.MyCanvasView.scalePercent();
		this.MyCanvasView.scaleCanvas()
	}

	calculateZoomByBrowserViewPort()
	{
		let w = document.documentElement.clientWidth - 180;
		let h = document.documentElement.clientHeight - 300;
		let p1 = Math.floor((100/this.width)  * w);
		let p2 = Math.floor((100/this.height) * h);
		if (p1 > p2)
			return  p2;
		else
			return  p1;
	}

	calculateImageScaleByCanvasInPerCent(w, h)
	{
		let p1 = Math.floor((100/w)  * this.width);
		let p2 = Math.floor((100/h) * this.height);
		if (p1 > p2)
			return  p2;
		else
			return  p1;
	}

	createImageFromSVG(item, object)
	{
		this.createItem(item, object);
	}

	createTextFromSVG(item, object)
	{
		var textobj = object.toObject();
		textobj.id = object.id;

		let new_text = new fabric.Textbox(object.text, textobj);
		this.createItem(item, new_text);
	}

	createItem(item, object)
	{
		this.svg_items[object.id] = {"item": item, "object": object};
	}

}

