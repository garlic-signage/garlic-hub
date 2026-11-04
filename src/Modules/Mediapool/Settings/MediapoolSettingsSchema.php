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

namespace App\Modules\Mediapool\Settings;

use App\Shared\Settings\SettingField;
use App\Shared\Settings\SettingsSchema;
use App\Shared\Settings\SettingType;

/**
 * The largest file per media type an upload or a download may have, in bytes.
 */
final readonly class MediapoolSettingsSchema implements SettingsSchema
{
	public function fields(): array
	{
		$fields = [];
		foreach (['images', 'documents', 'audios', 'videos', 'downloads', 'datasource'] as $type)
		{
			$fields[] = new SettingField('max_file_sizes', $type, SettingType::NUMBER, 'max_file_size_' . $type, 1);
		}

		return $fields;
	}
}
