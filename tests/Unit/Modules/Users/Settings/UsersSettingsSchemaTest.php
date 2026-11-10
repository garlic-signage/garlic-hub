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

namespace Tests\Unit\Modules\Users\Settings;

use App\Modules\Users\Settings\UsersSettingsSchema;
use App\Shared\Settings\SettingField;
use App\Shared\Settings\SettingType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The settings of the user administration which the admin may change.
 */
class UsersSettingsSchemaTest extends TestCase
{
	/**
	 * The switch which requires the second factor of every user (see AuthMiddleware).
	 */
	#[Group('units')]
	public function testTheAdminMayRequireTheSecondFactor(): void
	{
		static::assertEquals([new SettingField('TwoFactor', 'required', SettingType::SWITCH, 'two_factor_required')], new UsersSettingsSchema()->fields());
	}
}
