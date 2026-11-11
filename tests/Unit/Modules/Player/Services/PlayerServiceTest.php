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

use App\Framework\Database\BaseRepositories\Transactions;
use App\Modules\Auth\UserSession;
use App\Modules\Player\Repositories\PlayerRepository;
use App\Modules\Player\Services\AclValidator;
use App\Modules\Player\Services\PlayerService;
use App\Modules\Playlists\Services\PlaylistsService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use App\Shared\Acl\AclGrantService;
use App\Shared\Acl\AclSections;

/**
 * The rights and the changes of the players are tested with the real database in
 * tests/Integration/Modules/Player/Services (PlayerAccessTest, PlayerManagementTest).
 * Here is the one answer which the database of the tests does not give: the dashboard query has no result,
 * and which scope the dashboard asks the repository for.
 */
class PlayerServiceTest extends TestCase
{
	#[Group('units')]
	public function testTheDashboardWithoutAResultOfTheDatabaseIsZero(): void
	{
		$repository = $this->createStub(PlayerRepository::class);
		$repository->method('findAllForDashboard')->willReturn([]);
		$service = new PlayerService($repository, $this->createStub(PlaylistsService::class), $this->createStub(AclValidator::class), $this->createStub(UserSession::class), $this->createStub(AclGrantService::class), $this->createStub(Transactions::class));

		static::assertSame(['active' => 0, 'inactive' => 0, 'pending' => 0], $service->findAllForDashboard());
	}

	/**
	 * The database gives the sums as text, NULL when there is no player of a kind.
	 */
	#[Group('units')]
	public function testTheDashboardHasNumbers(): void
	{
		$repository = $this->createStub(PlayerRepository::class);
		$repository->method('findAllForDashboard')->willReturn(['active' => '2', 'inactive' => null, 'pending' => '1']);
		$service = new PlayerService($repository, $this->createStub(PlaylistsService::class), $this->createStub(AclValidator::class), $this->createStub(UserSession::class), $this->createStub(AclGrantService::class), $this->createStub(Transactions::class));

		static::assertSame(['active' => 2, 'inactive' => 0, 'pending' => 1], $service->findAllForDashboard());
	}

	#[Group('units')]
	public function testTheModuleAdminCountsAllPlayers(): void
	{
		$validator = $this->createStub(AclValidator::class);
		$validator->method('isModuleAdmin')->willReturn(true);
		$repository = $this->createMock(PlayerRepository::class);
		$repository->expects($this->once())->method('findAllForDashboard')->with()->willReturn(['active' => '5', 'inactive' => '0', 'pending' => '0']);

		static::assertSame(['active' => 5, 'inactive' => 0, 'pending' => 0], $this->createService($repository, $validator)->findAllForDashboard());
	}

	/**
	 * The list of the players is empty for a sub admin until his scope comes with the Enterprise edition.
	 */
	#[Group('units')]
	public function testTheSubAdminCountsNoPlayers(): void
	{
		$validator = $this->createStub(AclValidator::class);
		$validator->method('isSubAdmin')->willReturn(true);
		$repository = $this->createMock(PlayerRepository::class);
		$repository->expects($this->never())->method('findAllForDashboard');

		static::assertSame(['active' => 0, 'inactive' => 0, 'pending' => 0], $this->createService($repository, $validator)->findAllForDashboard());
	}

	#[Group('units')]
	public function testAUserCountsHisOwnAndTheSharedPlayers(): void
	{
		$validator = $this->createMock(AclValidator::class);
		$validator->method('isModuleAdmin')->willReturn(false);
		$validator->method('isSubAdmin')->willReturn(false);
		$validator->expects($this->once())->method('findSharedUnitIds')->with(7, AclSections::EDITOR)->willReturn(['3', '4']);
		$repository = $this->createMock(PlayerRepository::class);
		$repository->expects($this->once())->method('findAllForDashboard')->with(7, [3, 4])->willReturn(['active' => '1', 'inactive' => '1', 'pending' => '0']);

		static::assertSame(['active' => 1, 'inactive' => 1, 'pending' => 0], $this->createService($repository, $validator, 7)->findAllForDashboard());
	}

	private function createService(PlayerRepository $repository, AclValidator $validator, int $UID = 1): PlayerService
	{
		$userSession = $this->createStub(UserSession::class);
		$userSession->method('getUID')->willReturn($UID);

		return new PlayerService($repository, $this->createStub(PlaylistsService::class), $validator, $userSession, $this->createStub(AclGrantService::class), $this->createStub(Transactions::class));
	}
}
