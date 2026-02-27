class GroupProperties
{
	MyCanvasView = {};
	group         = new ToggleButton(document.getElementById("object_group"))

	constructor(MyCanvasView)
	{
		this.MyCanvasView = MyCanvasView;
	}

	activate(object)
	{
		// do not group if there is one item locked
		if (this.MyCanvasView.isLockedInSelection() === true)
			return;

		this.group.show(true);
		this.group.update('display', object.type === 'group' ? 'ungroup' : 'group')
		this.current = object.type;
	}

	deactivate()
	{
		this.group.show(false)
	}

	initEventListener()
	{
		this.group.getElement().addEventListener("click", () =>
		{
			let object = this.MyCanvasView.getCanvas().getActiveObject();
			this.setGroup(object)
			this.MyCanvasView.renderCanvas();
		});

		this.MyCanvasView.getCanvas().on('selection:updated', ({selected, target}) =>
		{
			if (this.MyCanvasView.isLockedInSelection() === true)
			{
				this.MyCanvasView.setLockedStatus(target, true);
			}
		});
	}
//============== private methods ===========================================================

	setGroup(object)
	{
		if (!object) return
		if (object.type === 'activeSelection')
		{
			object.toGroup()
			this.group.update('display', 'ungroup') // TEMP, TODO CANVAS EVENTS UPDATE
			this.MyCanvasView.getCanvas().fire('selection:updated', {
				target: object
			})
			return
		}
		if (object.type === 'group') {
			object.toActiveSelection()
			this.group.update('display', 'group') // TEMP, TODO CANVAS EVENTS UPDATE
			this.MyCanvasView.getCanvas().fire('selection:updated', {
				target: object
			})
		}
	}
}
