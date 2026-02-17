class ToggleButton
{
	constructor(domElement)
	{
		this.el = domElement
	}

	update(type, value)
	{
		const children = this.el.children;
		if (type === 'display') {
			for (let i = 0; i < children.length; i++)
			{
				children[i].style.display = children[i].getAttribute("name") === value ? "block" : "none";
			}
		}
		if (type === "active")
		{
			this.el.style.fill = this.el.getAttribute("name") === value ? "#269CC0" : "#000";
		}
	}

	getElement()
	{
		return this.el
	}

	show(value)
	{
		this.el.style.display = value ? "flex" : "none";
	}
}