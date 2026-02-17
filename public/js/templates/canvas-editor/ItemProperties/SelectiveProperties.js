/**
 * For Properties which can be assigned to more than one
 * Maybe later someone have a better idea how to deal with this stuff.
 */
class SelectiveProperties
{
	MyCanvasView = {};
	fillColor    = document.getElementById("fill_color");

	constructor(MyCanvasView) {
		this.MyCanvasView = MyCanvasView;
	}

	activateFillColor(object)
	{
		this.fillColor.value = this.getFillColor(object);
		this.fillColor.style.display = "block";
	}

	deactivateAll()
	{
		this.deactivateFillColor();
	}

	deactivateFillColor()
	{
		this.fillColor.style.display = "none";
	}

	initEventListener()
	{
		this.fillColor.addEventListener("input", () => {
			let object = this.MyCanvasView.getCanvas().getActiveObject();
			this.setFillColor(object, this.fillColor.value)
			this.MyCanvasView.renderCanvas();
		});
		this.fillColor.addEventListener("change", () => {
			let object = this.MyCanvasView.getCanvas().getActiveObject();
			this.setFillColor(object, this.fillColor.value)
			this.MyCanvasView.getCanvas().fire('object:modified', { target: object })
			this.MyCanvasView.renderCanvas();
		});
	}

//============== private methods ===========================================================

	// Todo: This is wrong here, because we have two responsibilies (text and Shapes)
	// But I do not habe a bedder idea, currently
	// We need something like an abstract class to instance/inject in properties
	getFillColor(object) {
		if (!object) return
		if (object.type !== "i-text" && object.type !== "text" && object.type !== "textbox") return object.fill

		let styles = object.getSelectionStyles(object.isEditing ? object.selectionStart : 0, object.isEditing ? object.selectionEnd : 1, true)
		return styles && styles[0] ? styles[0].fill : object.fill
	}

	// Todo: look at getFillColor
	setFillColor(object, color) {
		if (!object) return
		this.fillColor.value = color;
		if (object.type === "i-text" || object.type === "text" || object.type === "textbox") {
			object.setSelectionStyles({ fill: color }, object.selectionStart === object.selectionEnd ? 0 : object.selectionStart, object.selectionStart === object.selectionEnd ? object.text.length : object.selectionEnd)
		} else {
			object.set("fill", color)
		}
	}

}
