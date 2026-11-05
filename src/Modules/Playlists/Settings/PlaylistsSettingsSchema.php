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

namespace App\Modules\Playlists\Settings;

use App\Modules\Playlists\Helper\ExportSmil\Utils\Properties;
use App\Modules\Playlists\Helper\ItemFit;
use App\Shared\Settings\SettingField;
use App\Shared\Settings\SettingsSchema;
use App\Shared\Settings\SettingType;

/**
 * The defaults of a new item: what the SMIL export writes when the item has no own value.
 */
final readonly class PlaylistsSettingsSchema implements SettingsSchema
{
	public function fields(): array
	{
		return [
			new SettingField('Defaults', 'duration', SettingType::NUMBER, 'default_duration', 1, 86400),
			new SettingField('Defaults', 'volume', SettingType::NUMBER, 'default_volume', 0, 100),
			new SettingField('Defaults', 'fit', SettingType::SELECT, 'default_fit', options: array_map(static fn(ItemFit $fit): string => $fit->value, ItemFit::cases())),
			new SettingField('Defaults', 'media_align', SettingType::SELECT, 'default_media_align', options: Properties::MEDIA_ALIGNS),
			new SettingField('Release', 'enabled', SettingType::SWITCH, 'release_enabled'),
		];
	}
}
