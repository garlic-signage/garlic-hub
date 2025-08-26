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

namespace App\Modules\Player\Enums;

/**
 * Represents a set of commands that can be issued to a player.
 *
 * This enum contains various string-based commands that may be used to
 * control or modify the player's behavior, perform maintenance tasks,
 * or update its configuration.
 */
enum PlayerCommands : string
{
	case UPDATE_CONFIGURATION  = 'update_configuration';
	case REBOOT                = 'reboot';
	case UPDATE_FIRMWARE       = 'update_firmware';
	case CLEAR_CACHE           = 'clear_cache';
	case CLEAR_WEBCACHE        = 'clear_webcache';
	case UPDATE_URLS_LIST      = 'update_urls_list';
}