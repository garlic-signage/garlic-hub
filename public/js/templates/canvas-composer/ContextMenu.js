class ContextMenu {
	MyCanvasView = {};
	MyCanvasDialog = {};
	options;
	context_menu;

	constructor(MyCanvasView, MyCanvasDialog) {
		this.MyCanvasView = MyCanvasView;
		this.MyCanvasDialog = MyCanvasDialog;
	}

	show(options)
	{
		// open when multiple objects are selected or grouped
		if (options.target.type === "activeSelection" || options.target.type === "group")
			return;

		if (options.target.type === "text" || options.target.type === "i-text" || options.target.type === "textbox" ||
			options.target.type === "circle" || options.target.type === "rect" || options.target.type === "triangle" || options.target.type === "polygon"
		)
		{
			this.build(options)
			document.getElementById("change_image").style.display = 'none';
		}
		else {
			this.build(options)
			this.initLoadImageEvent();
		}
		this.initDublicateEvent();
		this.initRemoveEvent();
		this.initZIndexEvent();
		this.initLockEvent();
	}

	build(options) {
		this.options = options;

		this.context_menu = document.createElement("div");
		this.context_menu.style.position = "absolute";
		this.context_menu.style.zIndex = 1000;
		this.context_menu.style.left = this.options.e.pageX + "px";
		this.context_menu.style.top = this.options.e.pageY + "px";
		this.context_menu.innerHTML = document.getElementById("context-menu").innerHTML;
		document.body.append(this.context_menu);
	}

	initZIndexEvent() {
		let move_background = document.getElementById("move_background");
		move_background.onclick = () => {
			this.MyCanvasView.getCanvas().getActiveObject().sendToBack();
			this.remove();
		}
		let move_back = document.getElementById("move_back");
		move_back.onclick = () => {
			this.MyCanvasView.getCanvas().getActiveObject().sendBackwards();
			this.remove();
		}
		let move_front = document.getElementById("move_front");
		move_front.onclick = () => {
			this.MyCanvasView.getCanvas().getActiveObject().bringForward();
			this.remove();
		}
		let move_foreground = document.getElementById("move_foreground");
		move_foreground.onclick = () => {
			this.MyCanvasView.getCanvas().getActiveObject().bringToFront();
			this.remove();
		}
	}

	initLoadImageEvent() {
		let change_image = document.getElementById("change_image");
		change_image.style.display = 'block';
		change_image.onclick = () => {
			this.remove();
			this.MyCanvasDialog.displayMediaSelector();
			this.MyCanvasDialog.initCancelEvent();
			this.MyCanvasDialog.initTransferEvent(this.options.target, this.MyCanvasView);
		}
	}

	initDublicateEvent() {
		let duplicate_item = document.getElementById("duplicate_item");
		duplicate_item.onclick = () => {
			this.MyCanvasView.dublicateActiveObject();
			this.MyCanvasView.getCanvas()._historySaveAction()
			this.remove();
		}
	}

	initRemoveEvent() {
		let delete_item = document.getElementById("delete_item");
		delete_item.onclick = () => {
			this.MyCanvasView.removeActiveObject();
			this.MyCanvasView.getCanvas()._historySaveAction();
			this.remove();
		}
	}

	initLockEvent() {
		let lock_unlock = document.getElementById("lock_unlock");
		let lock = document.getElementById("lock");
		let unlock = document.getElementById("unlock");
		if (this.MyCanvasView.isCurrentLocked())
		{
			lock.style.display = "none";
			unlock.style.display = "inline";
		}
		else
		{
			lock.style.display = "inline";
			unlock.style.display = "none";
		}
		lock_unlock.onclick = () => {
			if (this.MyCanvasView.isCurrentLocked())
				this.MyCanvasView.setCurrentLockedStatus(false);
			else
				this.MyCanvasView.setCurrentLockedStatus(true);

			this.MyCanvasView.getCanvas()._historySaveAction()
			this.remove();
		}
	}
	remove() {
		if (this.context_menu !== undefined)
			this.context_menu.remove();
	}

}