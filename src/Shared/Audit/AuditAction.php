<?php
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
declare(strict_types=1);

namespace App\Shared\Audit;

/**
 * What the audit log records: the changes of rights, shares, settings and users, and failed logins.
 */
enum AuditAction: string
{
	case ROLE_GRANTED           = 'role_granted';
	case ROLE_REVOKED           = 'role_revoked';
	case UNIT_SHARED            = 'unit_shared';
	case UNIT_UNSHARED          = 'unit_unshared';
	case SETTINGS_CHANGED       = 'settings_changed';
	case USER_CREATED           = 'user_created';
	case USER_UPDATED           = 'user_updated';
	case USER_DELETED           = 'user_deleted';
	case PASSWORD_RESET_CREATED = 'password_reset_created';
	case PASSWORD_CHANGED       = 'password_changed';
	case LOGIN_FAILED           = 'login_failed';
	case VERSION_RESTORED       = 'version_restored';
}
