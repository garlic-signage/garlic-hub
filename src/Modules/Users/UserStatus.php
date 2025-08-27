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

namespace App\Modules\Users;

/**
 * Represents the various statuses that can be assigned to a user.
 * Each status is associated with a specific integer value.
 */
enum UserStatus: int {
	case DELETED         = 0;
	case BLOCKED         = 1;
	case NOT_VERIFICATED = 2;
	case REGISTERED      = 3;
	case PREMIUM_A       = 4;
	case PREMIUM_B       = 5;
	case PREMIUM_C       = 6;
	case TECHNICIAN_A    = 7;
	case TECHNICIAN_B    = 8;
	case ADMIN           = 9;
}
