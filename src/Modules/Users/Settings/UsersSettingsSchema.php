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

namespace App\Modules\Users\Settings;

use App\Shared\Settings\SettingField;
use App\Shared\Settings\SettingsSchema;
use App\Shared\Settings\SettingType;

final readonly class UsersSettingsSchema implements SettingsSchema
{
	public function fields(): array
	{
		return [
			// a user without the second factor reaches only his account until he sets it up (see AuthMiddleware)
			new SettingField('TwoFactor', 'required', SettingType::SWITCH, 'two_factor_required'),
			// the login with OpenID Connect: the first login creates the user without rights (see Auth\SsoLogin)
			new SettingField('Sso', 'enabled', SettingType::SWITCH, 'sso_enabled'),
			new SettingField('Sso', 'name', SettingType::TEXT, 'sso_name', dependsOn: 'Sso.enabled'),
			new SettingField('Sso', 'issuer', SettingType::TEXT, 'sso_issuer', dependsOn: 'Sso.enabled'),
			new SettingField('Sso', 'client_id', SettingType::TEXT, 'sso_client_id', dependsOn: 'Sso.enabled'),
			new SettingField('Sso', 'client_secret', SettingType::SECRET, 'sso_client_secret', dependsOn: 'Sso.enabled'),
			new SettingField('Sso', 'allowed_domains', SettingType::TEXT, 'sso_allowed_domains', dependsOn: 'Sso.enabled'),
		];
	}
}
