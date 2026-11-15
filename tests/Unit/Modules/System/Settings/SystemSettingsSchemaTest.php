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

namespace Tests\Unit\Modules\System\Settings;

use App\Framework\System\SelfUpdate;
use App\Modules\System\Settings\SystemSettingsSchema;
use App\Shared\Settings\SettingField;
use App\Shared\Settings\SettingType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The settings of the whole installation. The switch of the automatic update only where garlic-hub-update is
 * installed: its timer on the Pi reads Update.auto from var/settings/config_system.ini.
 */
class SystemSettingsSchemaTest extends TestCase
{
	#[Group('units')]
	public function testWithoutTheUpdaterThereIsNoSwitchForTheUpdate(): void
	{
		$keys = array_map(static fn(SettingField $f): string => $f->section . '.' . $f->key, new SystemSettingsSchema(new SelfUpdate(false, sys_get_temp_dir()))->fields());

		static::assertSame(['Scheduler.enabled', 'Audit.enabled', 'Audit.retention_days', 'Versioning.enabled', 'Versioning.max_versions'], $keys);
	}

	#[Group('units')]
	public function testWithTheUpdaterTheAutomaticUpdateIsTheLastSwitch(): void
	{
		$fields = new SystemSettingsSchema(new SelfUpdate(true, sys_get_temp_dir()))->fields();

		static::assertCount(6, $fields);
		static::assertEquals(new SettingField('Update', 'auto', SettingType::SWITCH, 'update_auto'), $fields[5]);
	}
}
