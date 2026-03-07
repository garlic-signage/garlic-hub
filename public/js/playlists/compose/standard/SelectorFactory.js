import {MediaService}          from "../../../mediapool/media/MediaService.js";
import {FetchClient}           from "../../../core/FetchClient.js";
import {MediaSelectorView}     from "../../../mediapool/selector/MediaSelectorView.js";
import {MediaSelector}         from "../../../mediapool/selector/MediaSelector.js";
import {WunderbaumWrapper}     from "../../../mediapool/treeview/WunderbaumWrapper.js";
import {TreeViewElements}      from "../../../mediapool/treeview/TreeViewElements.js";
import {MediaFactory}          from "../../../mediapool/media/MediaFactory.js";
import {PlaylistsService}      from "./playlists/PlaylistsService.js";
import {PlaylistsSelector}     from "./selectors/PlaylistsSelector.js";
import {PlaylistsSelectorView} from "./selectors/PlaylistsSelectorView.js";
import {TemplatesSelector}     from "../../../templates/selectors/TemplatesSelector.js";
import {TemplatesSelectorView} from "../../../templates/selectors/TemplatesSelectorView.js";

export class SelectorFactory
{
	#mediaSelector = null;
	#playlistsSelector = null;
	#playlistsService = null;
	#templatesSelector = null;
	#templatesService = null;
	constructor(playlistsService, templatesService)
	{
		this.#playlistsService = playlistsService;
		this.#templatesService = templatesService;
	}

	create(type)
	{
		switch (type)
		{
			case "mediapool":
				if (this.#mediaSelector === null)
				{
					this.#mediaSelector = new MediaSelector(
						new WunderbaumWrapper(new TreeViewElements()),
						new MediaService(new FetchClient()),
						new MediaSelectorView(new MediaFactory(document.getElementById('mediaTemplate')))
					);
				}
				return this.#mediaSelector;
			case "playlists":
				if (this.#playlistsSelector === null)
				{
					this.#playlistsSelector = new PlaylistsSelector(
						this.#playlistsService,
						new PlaylistsSelectorView()
					);
				}
				return this.#playlistsSelector;
			case "templates":
				if (this.#templatesSelector === null)
				{
					this.#templatesSelector = new TemplatesSelector(
						this.#templatesService,
						new TemplatesSelectorView()
					);
				}
				return this.#templatesSelector;
		}
	}
}