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

namespace Tests\Unit\Modules\Playlists\Services;

use App\Modules\Auth\UserSession;
use App\Modules\Playlists\Services\AclValidator;
use App\Modules\Playlists\Services\PlaylistRelease;
use App\Modules\Playlists\Services\PlaylistsService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Who may export while the release is on: an admin of the owner. The owner comes from the database,
 * the ids can be text there.
 */
#[Group('units')]
class PlaylistReleaseTest extends TestCase
{
	/**
	 * @param array<string,mixed> $playlist
	 * @param array{UID:int, company_id:int} $owner
	 */
	private function mayExport(array $playlist, array $owner): bool
	{
		$userSession = $this->createStub(UserSession::class);
		$userSession->method('getUID')->willReturn(5);
		$aclValidator = $this->createMock(AclValidator::class);
		$aclValidator->expects($this->once())->method('isAdmin')->with(5, $owner)->willReturn(true);

		return new PlaylistRelease(true, $this->createStub(PlaylistsService::class), $aclValidator, $userSession)->mayExport($playlist);
	}

	public function testTheAdminIsAskedForTheOwnerAndTheCompanyOfThePlaylist(): void
	{
		static::assertTrue($this->mayExport(['playlist_id' => 1, 'UID' => '2', 'company_id' => '4'], ['UID' => 2, 'company_id' => 4]));
	}

	public function testAPlaylistWithoutOwnerHasNone(): void
	{
		static::assertTrue($this->mayExport(['playlist_id' => 1], ['UID' => 0, 'company_id' => 0]));
	}

	public function testWhileTheReleaseIsOffEverybodyExports(): void
	{
		$aclValidator = $this->createMock(AclValidator::class);
		$aclValidator->expects($this->never())->method('isAdmin');

		static::assertTrue(new PlaylistRelease(false, $this->createStub(PlaylistsService::class), $aclValidator, $this->createStub(UserSession::class))->mayExport([]));
	}
}
