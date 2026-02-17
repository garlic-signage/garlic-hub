/**
 * Interface/Wrapper class for every Object/Item Properties
 */
class ItemProperties
{
	MyGlobalProperties = {};
	MyGroupProperties = {};
	MySelectiveProperties = {};
	MyTextProperties = {};
	current = ""

	constructor(Global, Group, Selective, Text)
	{
		this.MyGlobalProperties = Global;
		this.MyGroupProperties  = Group;
		this.MySelectiveProperties = Selective;
		this.MyTextProperties = Text;
	}

	activateCurrent(object)
	{
		this.current = object.type;
		switch (object.type)
		{
			case "group":
			case "activeSelection":
				this.MyGroupProperties.activate(object);
				break;
			case "text":
			case "i-text":
			case "textbox":
				this.MySelectiveProperties.activateFillColor(object);
				this.MyGlobalProperties.activate(object);
				this.MyTextProperties.activate(object);
				break;
			case "circle":
			case "rect":
			case "triangle":
			case "polygon":
				this.MySelectiveProperties.activateFillColor(object);
				this.MyGlobalProperties.activate(object);
				break;
			case "image":
				this.MyGlobalProperties.activate(object);
				break;
			default:
				break;
		}
	}

	deactivatePrevious(new_current)
	{
		if (this.current === "")
			return;
		this.deactivateAllProperties();
	}

	deactivateAllProperties()
	{
		this.MyGlobalProperties.deactivate();
		this.MyGroupProperties.deactivate();
		this.MySelectiveProperties.deactivateAll();
		this.MyTextProperties.deactivate();
	}

	initEventListener(MyCanvasView)
	{
		setInterval(() =>
		{
			MyCanvasView.renderCanvas();
		}, 250)

		this.MyGlobalProperties.initEventListener();
		this.MyGroupProperties.initEventListener();
		this.MySelectiveProperties.initEventListener();
		this.MyTextProperties.initEventListener();
	}
}