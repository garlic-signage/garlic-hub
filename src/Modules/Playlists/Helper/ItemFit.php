<?php
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
declare(strict_types=1);

namespace App\Modules\Playlists\Helper;

/**
 * ItemFit enum represents different strategies for fitting an item into a given space.
 *
 * Enums:
 * - FILL: The item is resized to fill the entire space.
 * - MEET: The item is resized to fit within the constraints while maintaining its aspect ratio.
 * - MEETBEST: The item is resized to meet the most suitable dimensions, optimizing for quality.
 * - SLICE: The item is resized and cropped to completely cover the space.
 * - SCROLL: The item maintains its original size, allowing scrolling to view contents outside the bounds.
 */
enum ItemFit: string
{
	case FILL     = 'fill';
	case MEET     = 'meet';
	case MEETBEST = 'meetBest';
	case SLICE    = 'slice';
	case SCROLL   = 'scroll';
}