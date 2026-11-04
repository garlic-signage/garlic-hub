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

namespace App\Modules\System\Settings;

use App\Shared\Settings\SettingField;
use App\Shared\Settings\SettingsSchema;
use App\Shared\Settings\SettingType;

/**
 * The settings of the whole installation. All of them are off by default: the system is lean.
 */
final readonly class SystemSettingsSchema implements SettingsSchema
{
	public function fields(): array
	{
		return [
			new SettingField('Scheduler', 'enabled', SettingType::SWITCH, 'scheduler_enabled'),
			// the scheduler deletes the old entries of the audit log
			new SettingField('Audit', 'enabled', SettingType::SWITCH, 'audit_enabled', dependsOn: 'Scheduler.enabled'),
			new SettingField('Audit', 'retention_days', SettingType::NUMBER, 'audit_retention_days', 1, 3650, dependsOn: 'Audit.enabled'),
			new SettingField('Versioning', 'enabled', SettingType::SWITCH, 'versioning_enabled'),
			new SettingField('Versioning', 'max_versions', SettingType::NUMBER, 'versioning_max_versions', 1, 100, dependsOn: 'Versioning.enabled'),
		];
	}
}
