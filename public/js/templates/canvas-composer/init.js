/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
 This file is part of the garlic-hub source code

 This program is free software: you can redistribute it and/or modify
 it under the terms of the GNU Affero General Public License, version 3,
 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

import {TemplatesService} from "../TemplatesService.js";
import {FetchClient}      from "../../core/FetchClient.js";
import {WaitOverlay}      from "../../core/WaitOverlay.js";
import {MediaSelector} from "../../mediapool/selector/MediaSelector.js";
import {FontLoader}          from "./Fonts/FontLoader.js";
import {FontCollector}       from "./Fonts/FontCollector.js";
import {ToggleButtonFactory} from "./Utils/ToggleButtonFactory.js";
import {WunderbaumWrapper}   from "../../mediapool/treeview/WunderbaumWrapper.js";
import {TreeViewElements} from "../../mediapool/treeview/TreeViewElements.js";
import {MediaService} from "../../mediapool/media/MediaService.js";
import {MediaSelectorView} from "../../mediapool/selector/MediaSelectorView.js";
import {MediaFactory}     from "../../mediapool/media/MediaFactory.js";
import {BmpDitherFactory}   from "./Formats/BmpDitherFactory.js";
import {FabricWrapper}      from "./Utils/FabricWrapper.js";
import {LoadService}         from "./Services/LoadService.js";
import {ViewportView}               from "./Views/ViewportView.js";
import {ViewportService}            from "./Services/ViewportService.js";
import {ViewportController}         from "./Controller/ViewportController.js";
import {SaveView}                   from "./Views/SaveView.js";
import {SaveController}             from "./Controller/SaveController.js";
import {SaveService}     from "./Services/SaveService.js";
import {ComposerContext}            from "./Utils/ComposerContext.js";
import {HistoryView}                from "./Views/HistoryView.js";
import {HistoryController}          from "./Controller/HistoryController.js";
import {InsertView}                 from "./Views/InsertView.js";
import {InsertService}              from "./Services/InsertService.js";
import {FabricShapeFactory} from "./Utils/FabricShapeFactory.js";
import {InsertController}           from "./Controller/InsertController.js";
import {MediaDialogView}            from "./Views/MediaDialogView.js";
import {MediaDialogController}      from "./Controller/MediaDialogController.js";
import {GlobalPropertiesController} from "./Controller/Properties/GlobalPropertiesController.js";
import {GlobalPropertiesView}       from "./Views/Properties/GlobalPropertiesView.js";
import {PropertiesController}       from "./Controller/PropertiesController.js";
import {GlobalPropertiesService} from "./Services/Properties/GlobalPropertiesService.js";
import {GroupPropertiesController} from "./Controller/Properties/GroupPropertiesController.js";
import {GroupPropertiesService}     from "./Services/Properties/GroupPropertiesService.js";
import {GroupPropertiesView}        from "./Views/Properties/GroupPropertiesView.js";
import {SelectivePropertiesView} from "./Views/Properties/SelectivePropertiesView.js";
import {SelectivePropertiesService} from "./Services/Properties/SelectivePropertiesService.js";
import {SelectivePropertiesController} from "./Controller/Properties/SelectivePropertiesController.js";
import {TextPropertiesView} from "./Views/Properties/TextPropertiesView.js";
import {TextPropertiesService} from "./Services/Properties/TextPropertiesService.js";
import {TextPropertiesController} from "./Controller/Properties/TextPropertiesController.js";
import {ContextMenuView} from "./Views/ContextMenuView.js";
import {ContextMenuService} from "./Services/ContextMenuService.js";
import {ContextMenuController} from "./Controller/ContextMenuController.js";
import {TransformService} from "./Services/TransformService.js";
import {ComposerKeyboardController} from "./Controller/ComposerKeyboardController.js";
import {ComposerKeyboardView} from "./Views/ComposerKeyboardView.js";
import {SnapView} from "./Views/SnapView.js";
import {SnapService}                   from "./Services/SnapService.js";
import {SnapController} from "./Controller/SnapController.js";
import {ShadowPropertiesView} from "./Views/Properties/ShadowPropertiesView.js";
import {ShadowPropertiesService} from "./Services/Properties/ShadowPropertiesService.js";
import {ShadowPropertiesController} from "./Controller/Properties/ShadowPropertiesController.js";

document.addEventListener("DOMContentLoaded", async function ()
{
	const fabricCanvas = new fabric.Canvas('canvas',
		{
			stopContextMenu: true,
			fireRightClick: true,
			preserveObjectStacking: true
		});

	const composerContext = new ComposerContext(lang);
	const templateService = new TemplatesService(new FetchClient());
	const fontLoader      = new FontLoader(FontsList);
	const fontCollector   = new FontCollector(fontLoader);
	const fabricWrapper   = new FabricWrapper(fabricCanvas);
	const waitOverlay     = new WaitOverlay();
	const loadService     = new LoadService(fabricWrapper, templateService, fontCollector, waitOverlay);


	const globalPropertiesView = new GlobalPropertiesView();
	const globalPropertiesService = new GlobalPropertiesService(fabricWrapper);
	const globalPropertiesController  = new GlobalPropertiesController(globalPropertiesView, globalPropertiesService);

	const viewportView       = new ViewportView();
	const viewportService    = new ViewportService(fabricWrapper);
	const viewPortController = new ViewportController(viewportView, viewportService, globalPropertiesService);

	// control save, reset, close and export images
	const saveView         = new SaveView();
	const bmpDitherFactory = new BmpDitherFactory();
	const saveService      = new SaveService(fabricWrapper, templateService, bmpDitherFactory, waitOverlay);
	const saveController   = new SaveController(saveView, composerContext, saveService, loadService, viewportService);

	const historyView       = new HistoryView();
	const historyController = new HistoryController(historyView, fabricWrapper);

	const mediaService = new MediaService(new FetchClient());
	let mediaSelector  = new MediaSelector(
		new WunderbaumWrapper(new TreeViewElements()),
		mediaService,
		new MediaSelectorView(new MediaFactory(document.getElementById('mediaTemplate')))
	);
	mediaSelector.filter = "images";

	const insertView         = new InsertView();
	const fabricShapefactory = new FabricShapeFactory();
	const insertService      = new InsertService(fabricWrapper, fabricShapefactory, viewportService);
	const insertController   = new InsertController(insertView, insertService);

	const mediaDialogView       = new MediaDialogView();
	const mediaDialogController = new MediaDialogController(mediaDialogView, mediaSelector, insertService);

	const toggleButtonFactory = new ToggleButtonFactory();
	const groupProperiesView        = new GroupPropertiesView(toggleButtonFactory);
	const groupPropertiesService    = new GroupPropertiesService(fabricWrapper);
	const groupPropertiesController = new GroupPropertiesController(groupProperiesView, groupPropertiesService);
	const shadowPropertiesView       = new ShadowPropertiesView();
	const shadowPropertiesService    = new ShadowPropertiesService(fabricWrapper);
	const shadowPropertiesController = new ShadowPropertiesController(shadowPropertiesView, shadowPropertiesService);

	const selectivePropertiesView       = new SelectivePropertiesView(toggleButtonFactory);
	const selectivePropertiesService    = new SelectivePropertiesService(fabricWrapper);
	const selectivePropertiesController = new SelectivePropertiesController(selectivePropertiesView, selectivePropertiesService);

	const textPropertiesView     = new TextPropertiesView(toggleButtonFactory);
	const textPropertiesService  = new TextPropertiesService(fabricWrapper)
	const textPropertiesController = new TextPropertiesController(textPropertiesView, textPropertiesService, fontLoader);

	const contextMenuView = new ContextMenuView();
	const contextMenuService  = new ContextMenuService(fabricWrapper);
	const contextMenuController = new ContextMenuController(contextMenuView, contextMenuService, mediaDialogController);

	const propertiesController = new PropertiesController(
		globalPropertiesController,
		groupPropertiesController,
		selectivePropertiesController,
		textPropertiesController,
		shadowPropertiesController
	);

	const composerKeyboardView = new ComposerKeyboardView();
	const transformService = new TransformService(fabricWrapper);
	const composerKeyboardController = new ComposerKeyboardController(composerKeyboardView, transformService, contextMenuService, propertiesController, fabricWrapper);

	const snapView = new SnapView(fabricWrapper.getContext());
	const snapService = new SnapService(fabricWrapper);
	const snapController = new SnapController(snapView, snapService);


	if (composerContext.itemId !== 0)
		await loadService.loadFromPlaylistItemDataBase(composerContext.itemId);
	else
		await loadService.loadFromTemplateDataBase(composerContext.templateId);


});

