document.addEventListener("DOMContentLoaded", function (event) {

	let MyCanvasView     = new CanvasView(new fabric.Canvas('canvas',
		{
			stopContextMenu: true,
			fireRightClick: true,
			preserveObjectStacking: true
		}
	), {});
	let MySvgItemsParser = new SvgItemsParser(MyCanvasView);
	// needed for load media
	let MyMediaSelector  = new MediaSelector();

	let MyCanvasDialog   = new CanvasDialog(MyMediaSelector, MySvgItemsParser);
	let MyContextMenu    = new ContextMenu(MyCanvasView, MyCanvasDialog);

	let MyGlobalProperties    = new GlobalProperties(MyCanvasView);
	let MyGroupProperties     = new GroupProperties(MyCanvasView);
	let MySelectiveProperties = new SelectiveProperties(MyCanvasView);
	let MyTextProperties      = new TextProperties(MyCanvasView, new FontHandler(FontsList));

	let MyItemProperties = new ItemProperties(MyGlobalProperties, MyGroupProperties, MySelectiveProperties, MyTextProperties);

	let MyCanvasEvents   = new CanvasEvents(MyContextMenu, MyCanvasView, MyCanvasDialog, MyMediaSelector, MyItemProperties);
	let MyTemplateModel  = new TemplateModel(MySvgItemsParser, MyCanvasEvents);

	let content_id = document.getElementById("content_id").value;

	let is_template_editor_dev =  document.getElementById("is_template_editor_dev").value;
	if (is_template_editor_dev === undefined || is_template_editor_dev === "false")
	{
		// Load mechanism set in CMS
		MyTemplateModel.loadFromDataBase(content_id);
	}
	else
	{
		// this should be used only for developing
		MyTemplateModel.loadFromLocalFile("./data/template_1.svg");
	}

	MyCanvasEvents.initInsertObjects();
	MyItemProperties.initEventListener(MyCanvasView);
	MyCanvasEvents.initSaveEvent(MyTemplateModel);
	MyCanvasEvents.initRangeSliderEvents();
	MyCanvasEvents.initCloseEvent();

	window.onresize = () => {
		if (MyCanvasEvents.isAutoResize())
			MySvgItemsParser.zoomToViewPort();
	}

});

