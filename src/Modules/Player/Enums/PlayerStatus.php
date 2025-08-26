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
 * Enumeration representing the various statuses of a player system.
 *
 * This enumeration defines integer-based constants that correspond to specific states
 * or phases that the player can be in throughout its lifecycle.
 */
enum PlayerStatus: int
{
	case UNREGISTERED    = 0;
	case UNRELEASED      = 1;
	case RELEASED        = 2;
	case DEBUG_FTP       = 3;
	case TEST_SMIL_OK    = 4;
	case TEST_SMIL_ERROR = 5;
	case TEST_USED_LATER1  = 6;
	case TEST_USED_LATER2  = 7;
	case TEST_NO_CONTENT = 8;
	case TEST_NO_PREFETCH = 9;

}