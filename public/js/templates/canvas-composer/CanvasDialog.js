class CanvasDialog
{
	MyMediaSelector = {};
	MySvgItemsParser = {};
	edit_dialog;
	is_open = false;

	constructor(MyMediaSelector, MySvgItemsParser)
	{
		this.MyMediaSelector = MyMediaSelector;
		this.MySvgItemsParser = MySvgItemsParser;
	}

	remove()
	{
		if (this.edit_dialog !== undefined)
			this.edit_dialog.remove();

		this.is_open = false;
	}

	isOpen()
	{
		return this.is_open;
	}

	displayMediaSelector()
	{
		let template_editor = document.getElementById("edit_dialog").innerHTML;
		this.MyMediaSelector.setDomContainer("mediaselector_tree", "mediaselector_content");
		this.MyMediaSelector.setMediaFilter("image");
		this.MyMediaSelector.initTreeView();

		this.edit_dialog = document.createElement("div");
		this.edit_dialog.className = 'dialog_overlay_wrapper';

		this.edit_dialog.innerHTML = template_editor;

		document.body.append(this.edit_dialog);
		this.MyMediaSelector.initTreeView();
		this.is_open = true;
	}

	initCancelEvent()
	{
		let edit_cancel = document.getElementById("element_edit_cancel");
		edit_cancel.onclick = () =>
		{
			this.remove();
		}
	}

	initInsertEvent(MyCanvasView)
	{
		let edit_insert = document.getElementById("element_edit_insert");
		edit_insert.style.display = "inline";
		edit_insert.onclick = () =>
		{
			let link = this.MyMediaSelector.getSelectedMediaLink().replace("preview", "original");
			fabric.Image.fromURL(link, (img) =>
			{
				let scale = this.MySvgItemsParser.calculateImageScaleByCanvasInPerCent(img.width, img.height);
				img.scale(scale/100); // 1 is 100%
				MyCanvasView.getCanvas().add(img);
				MyCanvasView.getCanvas().renderAll();
				this.MyMediaSelector.destroyTreeView();
				edit_insert.style.display = "none";
				this.remove();
			},{crossOrigin: 'anonymous'});
		}
	}

	initTransferEvent(target, MyCanvasView)
	{
		let edit_transfer = document.getElementById("element_edit_transfer");
		edit_transfer.style.display = "inline";
		edit_transfer.onclick = () =>
		{
			// must before set src cause then we have a new target
			let w = target.width  * target.scaleX;
			let h = target.height * target.scaleY;

			let link = this.MyMediaSelector.getSelectedMediaLink().replace("preview", "original");
			target.setSrc(link, () =>
			{
				target.scaleX = 1;
				target.scaleY = 1;
				// do not know why both must be set
				target.scaleToWidth(w, true);
				target.scaleToHeight(h, true);
				MyCanvasView.getCanvas().renderAll();
				this.MyMediaSelector.destroyTreeView();
				edit_transfer.style.display = "none";
				this.edit_dialog.remove();
			},{crossOrigin: 'anonymous'});
		}
	}
}