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
'use strict';

/**
 * The defaults of fabric.js only for the template composer, after core/canvas/fabricDefaults.js.
 *
 * The edges of an image change its frame and the picture keeps filling it, cut in the middle, instead of being
 * distorted (extension of fabric.js). The corners scale the image as before.
 *
 * The controls of the extension take the names of the edges ml, mr, mt and mb: only for these fabric.js gives the
 * fixed opposite side as "right" or "bottom", which the handler of the extension compares with. With its own names
 * (mle, mte ...) the left and the top edge shrank the image to 10 px at the first click.
 */
fabric.FabricImage.createControls = function ()
{
	const {controls} = fabric.FabricObject.createControls();
	const edges      = fabricExtensions.createImageResizeControlsWithScaleToCover();

	return {controls: {...controls, ml: edges.mle, mr: edges.mre, mt: edges.mte, mb: edges.mbe}};
};
