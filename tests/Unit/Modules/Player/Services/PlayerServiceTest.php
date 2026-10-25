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

namespace Tests\Unit\Modules\Player\Services;

use App\Modules\Auth\UserSession;
use App\Modules\Player\Repositories\PlayerRepository;
use App\Modules\Player\Services\AclValidator;
use App\Modules\Player\Services\PlayerService;
use App\Modules\Playlists\Services\PlaylistsService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The rights and the changes of the players are tested with the real database in
 * tests/Integration/Modules/Player/Services (PlayerAccessTest, PlayerManagementTest).
 * Here is the one answer which the database of the tests does not give: the dashboard query has no result.
 */
class PlayerServiceTest extends TestCase
{
	#[Group('units')]
	public function testTheDashboardWithoutAResultOfTheDatabaseIsZero(): void
	{
		$repository = $this->createStub(PlayerRepository::class);
		$repository->method('findAllForDashboard')->willReturn([]);
		$service = new PlayerService($repository, $this->createStub(PlaylistsService::class), $this->createStub(AclValidator::class), $this->createStub(UserSession::class));

		static::assertSame(['active' => 0, 'inactive' => 0, 'pending' => 0], $service->findAllForDashboard());
	}
}
