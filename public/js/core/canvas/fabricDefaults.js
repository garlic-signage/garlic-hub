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
 * The defaults of fabric.js for the composers, imported first by their init.js. Same in
 * misc/template-generator/generate.html.
 *
 * - The position of an object is its top left corner, like up to fabric.js 6 (7 takes the center). The templates
 *   and the zones count on it, and so does content without originX/originY.
 * - Numbers are stored with 6 decimals: with 2 a picture of 4000 px scaled by 0.123 would change its size by up to
 *   4 % with every save.
 */
fabric.FabricObject.ownDefaults.originX = "left";
fabric.FabricObject.ownDefaults.originY = "top";
fabric.config.configure({NUM_FRACTION_DIGITS: 6});
