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

namespace Tests\Unit\Modules\Playlists\Settings;

use App\Modules\Playlists\Settings\PlaylistsSettingsSchema;
use App\Shared\Settings\SettingField;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The limits of the item defaults which the admin may set: the SMIL export writes them for every item without own values.
 */
class PlaylistsSettingsSchemaTest extends TestCase
{
	#[Group('units')]
	public function testADurationIsOneSecondToADayAndAVolumeZeroToHundred(): void
	{
		$limits = [];
		foreach ((new PlaylistsSettingsSchema())->fields() as $field)
		{
			static::assertInstanceOf(SettingField::class, $field);
			$limits[$field->section . '.' . $field->key] = [$field->min, $field->max];
		}

		static::assertSame([1, 86400], $limits['Defaults.duration']);
		static::assertSame([0, 100], $limits['Defaults.volume']);
	}
}
