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

import {assertEquals}                 from "@std/assert";
import {assertSameElement, createDom} from "@support/dom.js";
import {replaceGlobals}               from "@support/globals.js";
import {BasePreviewHandler}           from "@js/mediapool/uploads/Preview/BasePreviewHandler.js";
import {ScreencastPreviews}           from "@js/mediapool/uploads/Screencast/ScreencastPreviews.js";

/**
 * Builds a preview like the PreviewFactory: an image with the data of the file.
 */
class FakePreviewFactory
{
	create(file)
	{
		return {
			createPreview:   () => document.createElement('img'),
			extractMetadata: () => ({name: file.name, size: '4 B', type: file.type})
		};
	}
}

Deno.test('the previews of the screencast are the previews of the uploads', () =>
{
	assertEquals(new ScreencastPreviews(null, null) instanceof BasePreviewHandler, true);
});

Deno.test('a screenshot is shown in its area with its metadata', () =>
{
	using dom      = createDom('<div id="previewScreencastsArea"></div>');
	using _globals = replaceGlobals({location: {protocol: 'https:'}});
	const area     = dom.document.getElementById('previewScreencastsArea');
	const enabled  = [];
	const previews = new ScreencastPreviews(area, new FakePreviewFactory());
	previews.fileUploader = {enableUploadButton: () => enabled.push(true)};
	const file     = new File(['jpeg'], 'screencast_shoot.jpg', {type: 'image/jpeg'});

	previews.addFile(file, {origin: 'Screencast screenshot', page_url: ''});

	const [id] = Object.keys(previews.fileList);
	assertEquals(previews.fileList[id], file);
	assertEquals(previews.metaDataList[id], {origin: 'Screencast screenshot', page_url: ''});
	assertSameElement(area.querySelector('.previewContainer'), area.querySelector(`[data-preview-id="${id}"]`));
	assertEquals(area.querySelectorAll('img.previewElement').length, 1);
	assertEquals(enabled, [true]);
});
