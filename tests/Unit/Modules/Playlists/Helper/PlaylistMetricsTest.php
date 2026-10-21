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

namespace Tests\Unit\Modules\Playlists\Helper;

use App\Modules\Playlists\Helper\PlaylistMetrics;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class PlaylistMetricsTest extends TestCase
{
	#[Group('units')]
	public function testDefaultsAreEmpty(): void
	{
		$metrics = new PlaylistMetrics();

		static::assertSame(
			['count_items' => 0, 'count_owner_items' => 0, 'filesize' => 0, 'duration' => 0, 'owner_duration' => 0],
			$metrics->toFrontendArray()
		);
	}

	#[Group('units')]
	public function testArrayRepresentations(): void
	{
		$metrics = new PlaylistMetrics(3, 2, 450, 120, 80);

		static::assertSame(
			['count_items' => 3, 'count_owner_items' => 2, 'filesize' => 450, 'duration' => 120, 'owner_duration' => 80],
			$metrics->toFrontendArray()
		);
		static::assertSame(['filesize' => 450, 'duration' => 120, 'owner_duration' => 80], $metrics->toPlaylistTableArray());
		static::assertSame(['item_duration' => 120, 'item_filesize' => 450], $metrics->toItemArray());
	}
}
