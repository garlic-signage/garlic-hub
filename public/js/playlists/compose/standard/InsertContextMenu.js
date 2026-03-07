export class InsertContextMenu
{
	#insertMenuSelect        = document.getElementById("insertMenuSelect");
	#selectorFactory         = null;
	#dragDropHandler         = null;
	#itemSelectContainer     = document.getElementById("itemSelectContainer");

	constructor(selectorFactory, dragDropHandler)
	{
		this.#selectorFactory = selectorFactory;
		this.#dragDropHandler = dragDropHandler;
	}

	init()
	{
		this.#insertMedia();
		this.#insertMenuSelect.addEventListener("change",  (e) =>
		{
			const selectedValue = e.target.value;

			switch (selectedValue)
			{
				case "insertMedia":
					this.#insertMedia()
					break;
				case "insertExternalMedia":
					this.#insertExternalMedia()
					break;
				case "insertPlaylists":
					this.#insertPlaylists();
					break;
				case "insertExternalPlaylists":
					this.#insertExternalPlaylists()
					break;
				case "insertTemplates":
					this.#insertTemplates();
					break
				case "insertChannels":
					this.#insertChannels();
					break;
				default:
					throw new Error("Unknown insert menu option");
			}
		});
	}

	async #insertMedia()
	{
		const selector = this.#selectorFactory.create("mediapool");
		await selector.showSelector(this.#itemSelectContainer);
		this.#dragDropHandler.source = "mediapool";
		this.#dragDropHandler.items = selector.getMediaItems();
		const container = selector.getMediaItemsContainer();
		this.#dragDropHandler.addDropSource(container);
	}

	async #insertExternalMedia()
	{
		alert("Insert external media");
	}

	async #insertPlaylists()
	{
		const selector = this.#selectorFactory.create("playlists");
		await selector.showSelector(this.#itemSelectContainer);
		this.#dragDropHandler.source = "playlists";
		this.#dragDropHandler.items = selector.items;
		const container = selector.getItemsContainer();
		this.#dragDropHandler.addDropSource(container);
	}

	async #insertExternalPlaylists()
	{
		alert("Insert external playlists");
	}

	async #insertTemplates()
	{
		const selector = this.#selectorFactory.create("templates");
		await selector.showSelector(this.#itemSelectContainer);
		this.#dragDropHandler.source = "templates";
		this.#dragDropHandler.items = selector.items;
		const container = selector.getItemsContainer();
		this.#dragDropHandler.addDropSource(container);
	}
	async #insertChannels()
	{
		alert("Insert channels");
	}
}
