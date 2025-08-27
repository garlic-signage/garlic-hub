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
 * Enumeration of item types utilized within the application.
 *
 * This enum defines various constants representing different types of items.
 * Each case corresponds to a distinct type, uniquely identified by a string value.
 */
enum ItemType: string
{
	case MEDIAPOOL = 'mediapool';
	case MEDIA_EXTERN = 'media_url';
	case PLAYLIST = 'playlist';
	case PLAYLIST_EXTERN = 'playlist_url';
	case TEMPLATE = 'template';
	case CHANNEL = 'channel';
}