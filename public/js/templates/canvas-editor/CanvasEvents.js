class CanvasEvents
{
	MyContextMenu = {};
	MyCanvasView = {};
	MyCanvasDialog = {};
	MyMediaSelector = {};
	MyItemProperties = {};
	is_autoresize = true;

	constructor(MyContextMenu, MyCanvasView, MyCanvasDialog, MyMediaSelector, MyItemProperties) {
		this.MyContextMenu = MyContextMenu;
		this.MyCanvasView = MyCanvasView;
		this.MyCanvasDialog = MyCanvasDialog;
		this.MyMediaSelector = MyMediaSelector;
		this.MyItemProperties = MyItemProperties;
	}

	isAutoResize() {
		return this.is_autoresize;
	}

	initChangeDetectors() {
		// check if canvas was changed
		this.MyCanvasView.getCanvas().on('object:modified', (event) => {
			this.MyCanvasView.setChanged(true);
		})
		this.MyCanvasView.getCanvas().on('text:changed', (event) => {
			this.MyCanvasView.setChanged(true);
		})
		// activate this only when canvas is builded.
		this.MyCanvasView.getCanvas().on('object:added', (event) => {
			this.MyCanvasView.setChanged(true);
		})
		this.MyCanvasView.getCanvas().on('object:removed', (event) => {
			this.MyCanvasView.setChanged(true);
		})
	}

	initEditEvents() {
		this.initMouseEvents();
		this.initKeyboardEvents();
	}

	initMouseEvents() {
		this.MyCanvasView.getCanvas().on('mouse:up', (options) => {
			this.MyContextMenu.remove();
			this.MyCanvasDialog.remove();
			if (options.button === 1) {
				// todo implement changing of canvas properties
				if (options.target == null) {
					this.MyItemProperties.deactivateAllProperties();
					return;
				}

				this.MyItemProperties.deactivatePrevious(this.MyCanvasView.getCanvas().getActiveObject().type);
				this.MyItemProperties.activateCurrent(this.MyCanvasView.getCanvas().getActiveObject());

			}
			else if (options.button === 3) {
				if (options.target == null)
					return;

				this.MyCanvasView.getCanvas().setActiveObject(options.target);
				this.MyContextMenu.show(options);
			}
		});
	}

	initKeyboardEvents()
	{
		this.MyCanvasView.getCanvasWrap().addEventListener("keydown", (event) => {
			if (event.shiftKey &&
				(event.key === "ArrowLeft" || event.key === "ArrowRight" || event.key === "ArrowUp" || event.key === "ArrowDown")) {
				this.MyCanvasView.moveActiveObject(event.key, 50);
			}
			else if (event.ctrlKey && event.key.toUpperCase() === "Z")
			{
				this.MyCanvasView.getCanvas().undo()
				this.MyItemProperties.deactivateAllProperties();
			}
			else if (event.ctrlKey && event.key.toUpperCase() === "Y")
			{
				this.MyCanvasView.getCanvas().redo()
				this.MyItemProperties.deactivateAllProperties();
			}
			else if (event.ctrlKey && event.key.toUpperCase() === "D")
			{
				this.MyCanvasView.dublicateActiveObject()
				this.MyItemProperties.deactivateAllProperties();
			}
			else
			{
				switch (event.key) {
					case "Delete":
						this.MyCanvasView.removeActiveObject();
						this.MyCanvasView.getCanvas()._historySaveAction()
						break;
					case "ArrowLeft":
					case "ArrowRight":
					case "ArrowUp":
					case "ArrowDown":
						this.MyCanvasView.moveActiveObject(event.key, 1);
						this.MyCanvasView.getCanvas()._historySaveAction()
						break;
					default:
						break;
				}
			}
		}, false);
	}

	initInsertObjects() {
		this.MyCanvasView.getInsertImage().addEventListener("click", () => {
			if (this.MyCanvasDialog.isOpen())
				return;

			this.MyCanvasDialog.displayMediaSelector();
			this.MyCanvasDialog.initCancelEvent();
			this.MyCanvasDialog.initInsertEvent(this.MyCanvasView);
		});

		this.MyCanvasView.getInsertText().addEventListener("click", () => {
			let text = new fabric.Textbox('Lorem ipsum', {
				left: 50, top: 10, width: 400, fontFamily: 'Arial',
				fill: '#000000', fontSize: 64
			});
			this.MyCanvasView.getCanvas().add(text);
			this.MyCanvasView.getCanvas()._historySaveAction()
			this.MyCanvasView.getCanvas().renderAll();
		});
		this.MyCanvasView.getInsertCircle().addEventListener("click", () => {
			var circle = new fabric.Circle({
				left: 50, top: 10, radius: 200,
				fill: '#000000'
			});
			this.MyCanvasView.getCanvas().add(circle);
			this.MyCanvasView.getCanvas()._historySaveAction()
			this.MyCanvasView.getCanvas().renderAll();
		});
		this.MyCanvasView.getInsertTriangle().addEventListener("click", () => {
			var triangle = new fabric.Triangle({
				left: 50, top: 10, width: 400, height: 400,
				fill: '#000000'
			});
			this.MyCanvasView.getCanvas().add(triangle);
			this.MyCanvasView.getCanvas()._historySaveAction()
			this.MyCanvasView.getCanvas().renderAll();
		});
		this.MyCanvasView.getInsertRectangle().addEventListener("click", () => {
			var rect = new fabric.Rect({
				left: 50, top: 10, width: 400, height: 300,
				fill: '#000000'
			});
			this.MyCanvasView.getCanvas().add(rect);
			this.MyCanvasView.getCanvas()._historySaveAction()
			this.MyCanvasView.getCanvas().renderAll();
		});
		this.MyCanvasView.getInsertPolygon().addEventListener("click", () => {
			var sweep = Math.PI * 2 / 5;
			var cx = 200;
			var cy = 200;
			var points = [];
			for (var i = 0; i < 5; i++) {
				var x = cx + 200 * Math.cos(i * sweep);
				var y = cy + 200 * Math.sin(i * sweep);
				points.push({ x: x, y: y });
			}
			var polygon = new fabric.Polygon(points, {
				fill: '#000',
				left: 50, top: 10
			}, false);
			this.MyCanvasView.getCanvas().add(polygon);
			this.MyCanvasView.getCanvas()._historySaveAction()
			this.MyCanvasView.getCanvas().renderAll();
		});
		this.MyCanvasView.getInsertHexagon().addEventListener("click", () => {
			var sweep = Math.PI * 2 / 6;
			var cx = 200;
			var cy = 200;
			var points = [];
			for (var i = 0; i < 6; i++) {
				var x = cx + 200 * Math.cos(i * sweep);
				var y = cy + 200 * Math.sin(i * sweep);
				points.push({ x: x, y: y });
			}
			var hexagon = new fabric.Polygon(points, {
				fill: '#000',
				left: 50, top: 10
			}, false);
			this.MyCanvasView.getCanvas().add(hexagon);
			this.MyCanvasView.getCanvas()._historySaveAction()
			this.MyCanvasView.getCanvas().renderAll();
		});
		this.MyCanvasView.getInsertOctagon().addEventListener("click", () => {
			var sweep = Math.PI * 2 / 8;
			var cx = 200;
			var cy = 200;
			var points = [];
			for (var i = 0; i < 8; i++) {
				var x = cx + 200 * Math.cos(i * sweep);
				var y = cy + 200 * Math.sin(i * sweep);
				points.push({ x: x, y: y });
			}
			var octagon = new fabric.Polygon(points, {
				fill: '#000',
				left: 50, top: 10
			}, false);
			this.MyCanvasView.getCanvas().add(octagon);
			this.MyCanvasView.getCanvas()._historySaveAction()
			this.MyCanvasView.getCanvas().renderAll();
		});

		this.MyCanvasView.getUndo().addEventListener("click", () => {
			this.MyCanvasView.getCanvas().undo()
			this.MyItemProperties.deactivateAllProperties();
		});
		this.MyCanvasView.getRedo().addEventListener("click", () => {
			this.MyCanvasView.getCanvas().redo()
			this.MyItemProperties.deactivateAllProperties();
		});

	}

	initRangeSliderEvents() {
		this.MyCanvasView.getSlider().oninput = () => {
			this.is_autoresize = false;
			this.MyCanvasView.scalePercent();
			this.MyCanvasView.scaleCanvas()
		}
	}

	initSaveEvent(MyTemplateModel) {
		document.getElementById("save_template").addEventListener("click", () => {
			MyTemplateModel.saveAsJpg(this.MyCanvasView.getCanvas());
			this.MyCanvasView.setChanged(false);
		});
	}

	initCloseEvent() {
		document.getElementById("close_template_editor").addEventListener("click", () => {
			if (this.MyCanvasView.hasChanged() === false || confirm_delete(this.MyCanvasView.getLangByKey('confirm_close')) === true)
				window.location.href = ThymianConfig.main_site + "?site=smil_playlists_show" + url_separator + "smil_playlist_id=" + document.getElementById("playlist_id").value;
		});
	}
}