/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2025 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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
"use strict";

import { DirectoryView } from "./treeview/DirectoryView.js";
import { TreeViewElements } from "./treeview/TreeViewElements.js";
import { TreeViewDialog } from "./treeview/TreeViewDialog.js";
import { TreeViewService } from "./treeview/TreeViewService.js";

import { UploaderDialog } from "./uploads/UploaderDialog.js";
import { LocalFilesPreviews } from "./uploads/Local/LocalFilesPreviews.js";
import { DragDropManager } from "./uploads/Local/DragDropManager.js";
import { PreviewFactory } from "./uploads/Preview/PreviewFactory.js";
import { FetchClient } from "../core/FetchClient.js";
import { FlashMessageHandler } from "../core/FlashMessageHandler.js";
import { MediaList } from "./media/MediaList.js";
import { MediaService } from "./media/MediaService.js";
import { ContextMenuMediaFactory } from "./media/ContextMenuMediaFactory.js";
import { MediaFactory } from "./media/MediaFactory.js";
import { MediaEditDialog } from "./media/MediaEditDialog.js";
import { MediaInfoDialog } from "./media/MediaInfoDialog.js";
import { MediaSearch } from "./media/MediaSearch.js";
import { FolderTagging } from "./media/FolderTagging.js";
import { UploadedMedia } from "./uploads/UploadedMedia.js";

import { UploadDialogElements } from "./uploads/UploadDialogElements.js";
import { LocalFilesElements } from "./uploads/Local/LocalFilesElements.js";
import { LocalFilesUploader } from "./uploads/Local/LocalFilesUploader.js";

import { ExternalFileUploader } from "./uploads/External/ExternalFileUploader.js";
import { ExternalFileElements } from "./uploads/External/ExternalFileElements.js";

import { SpicyCam } from "../external/spicy-camcast.min.js";
import { WebcamElements } from "./uploads/Webcam/WebcamElements.js";
import { WebcamUploader } from "./uploads/Webcam/WebcamUploader.js";
import { WebcamPreviews } from "./uploads/Webcam/WebcamPreviews.js";

import { SpicyCast } from "../external/spicy-camcast.min.js";
import { ScreencastElements } from "./uploads/Screencast/ScreencastElements.js";
import { ScreencastUploader } from "./uploads/Screencast/ScreencastUploader.js";
import { ScreencastPreviews } from "./uploads/Screencast/ScreencastPreviews.js";

import { StockPlatformElements } from "./uploads/StockPlatform/StockPlatformElements.js";
import { StockPlatformFactory } from "./uploads/StockPlatform/Platforms/StockPlatformFactory.js";
import { StockPlatformUploader } from "./uploads/StockPlatform/StockPlatformUploader.js";
import {ContextMenuTreeViewFactory} from "./treeview/ContextMenuTreeViewFactory.js";
import { AiImageElements } from "./uploads/AiImage/AiImageElements.js";
import { AiImageUploader } from "./uploads/AiImage/AiImageUploader.js";
import { BasePreviewHandler } from "./uploads/Preview/BasePreviewHandler.js";
import {UnitShareDialog} from "../core/UnitShareDialog.js";

document.addEventListener("DOMContentLoaded", function()
{
	const fetchClient     = new FetchClient();
	const flashMessageHandler = new FlashMessageHandler();
	const mediaService    = new MediaService(fetchClient);

	const mediaList     = new MediaList(
		document.getElementById("mediaList"),
		new MediaFactory(document.getElementById('mediaTemplate')),
        new ContextMenuMediaFactory(
			document.getElementById('media-contextmenu-template'),
			new MediaEditDialog(mediaService, flashMessageHandler),
			new MediaInfoDialog(mediaService, flashMessageHandler),
			mediaService,
			flashMessageHandler
		),
		mediaService
	);

	const treeViewElements = new TreeViewElements();
	const treeViewService  = new TreeViewService(fetchClient)
	const directoryView    = new DirectoryView(
		treeViewElements,
		mediaList,
		treeViewService,
		flashMessageHandler
	);

	const treeDialog = new TreeViewDialog(
		treeViewElements,
		treeViewService,
		flashMessageHandler
	);

	// only there when the user has a model for the tagging
	const folderTagging = document.getElementById("folderTaggingDialog") === null ? null : new FolderTagging(mediaService, flashMessageHandler, lang);

	const contextMenuTreeViewFactory = new ContextMenuTreeViewFactory(
		treeViewElements,
		treeDialog,
		treeViewService,
		flashMessageHandler,
		lang,
		UnitShareDialog.create("mediapool"),
		folderTagging
	);
	directoryView.addContextMenu(contextMenuTreeViewFactory);

	new MediaSearch(
		document.getElementById("mediaSearch"),
		document.getElementById("mediaTypeFilter"),
		mediaService,
		mediaList,
		directoryView,
		treeViewElements.currentPath,
		flashMessageHandler,
		lang
	).init();

	const addRootFolder = document.getElementById('addRootFolder');
	if (addRootFolder !== null)
	{
		addRootFolder.addEventListener('click', () => {
			treeDialog.prepareShow("add_root_folder", lang);
			treeDialog.show(directoryView);
		});
	}

	/**************** uploader ******************/

	const uploaderDialog = new UploaderDialog(new UploadDialogElements(), directoryView);
	initTaggingAfterUpload(folderTagging, directoryView, flashMessageHandler);

	// Section for local file uploads
	const localFilesElements = new LocalFilesElements();
    const previewFactory     = new PreviewFactory();
    const filePreviews       = new LocalFilesPreviews(localFilesElements.dropzonePreview, previewFactory);
	const dragDropManager    = new DragDropManager(localFilesElements, filePreviews);
	dragDropManager.init();
	filePreviews.fileUploader = new LocalFilesUploader(
		filePreviews,
		localFilesElements,
		directoryView,
		uploaderDialog,
		fetchClient
	);

	// Section for external file uploads
    new ExternalFileUploader(
        new ExternalFileElements(),
        directoryView,
        uploaderDialog,
        fetchClient
    );

	// Section for uploads from camera / webcam
    const webcamElements = new WebcamElements();
    const webcamPreviews = new WebcamPreviews(webcamElements.previewRecordsArea, previewFactory);
	webcamPreviews.fileUploader  = new WebcamUploader(
        new SpicyCam(webcamElements.webcamVideo),
        webcamPreviews,
        webcamElements,
        directoryView,
        uploaderDialog,
        fetchClient,
        lang
    );

	// Section for uploads from screencasts
	const screencastElements = new ScreencastElements();
	const screencastPreviews = new ScreencastPreviews(screencastElements.previewScreencastsArea, previewFactory);
	screencastPreviews.fileUploader  = new ScreencastUploader(
		new SpicyCast(screencastElements.screencastVideo),
		screencastPreviews,
		screencastElements,
		directoryView,
		uploaderDialog,
		fetchClient,
		lang
	);

	// Section for images of the AI, only there when the user has an AI for images
	if (AiImageElements.isAvailable())
	{
		const aiImageElements = new AiImageElements();
		const aiImagePreviews = new BasePreviewHandler(aiImageElements.previewArea, previewFactory);
		aiImagePreviews.fileUploader = new AiImageUploader(aiImagePreviews, aiImageElements, directoryView, uploaderDialog, fetchClient);
	}

	// Section for 3rd Party StockPlatforms
	const stockPlatformElements = new StockPlatformElements();
	const stockPlatformFactory = new StockPlatformFactory(fetchClient);
	new StockPlatformUploader(
		stockPlatformFactory,
		stockPlatformElements,
		directoryView,
		uploaderDialog,
		fetchClient
	);
});

/**
 * With the switch of the uploader the media uploaded since it was opened are tagged when it closes, like a folder:
 * only the ones without tags, saved at once. The switch is remembered in the browser.
 */
function initTaggingAfterUpload(folderTagging, directoryView, flashMessageHandler)
{
	const toggle = document.getElementById("tagAfterUpload");
	if (folderTagging === null || toggle === null)
		return;

	try
	{
		toggle.checked = localStorage.getItem("tag-after-upload") === "1";
	}
	catch
	{
		// no storage in this browser: the switch starts off
	}
	toggle.addEventListener("change", () => {
		try
		{
			localStorage.setItem("tag-after-upload", toggle.checked ? "1" : "0");
		}
		catch
		{
			// the switch counts for this page only
		}
	});

	document.getElementById("uploaderDialog").addEventListener("close", async () => {
		const mediaIds = UploadedMedia.take();
		if (!toggle.checked || mediaIds.length === 0 || directoryView.getActiveNodeId() === 0)
			return;

		try
		{
			const tagged = await folderTagging.run(directoryView.getActiveNodeId(), mediaIds);
			if (tagged > 0)
				directoryView.reloadCurrentNode();
		}
		catch (err)
		{
			flashMessageHandler.showError(err.message);
		}
	});
}
