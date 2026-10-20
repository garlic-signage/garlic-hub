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

namespace Tests\Unit\Modules\Playlists\Services;

use App\Framework\Core\Acl\AclHelper;
use App\Framework\Core\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Playlists\Services\AclValidator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AclValidatorTest extends TestCase
{
	private AclValidator $aclValidator;
	private AclHelper&MockObject $aclHelperMock;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->aclHelperMock = $this->createMock(AclHelper::class);

		$this->aclValidator    = new AclValidator($this->aclHelperMock);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testIsPlaylistEditableOwner(): void
	{
		$UID = 1;
		$playlist = ['UID' => 1, 'company_id' => 4, 'playlist_id' => 12];

		$this->aclHelperMock->expects($this->never())->method('isModuleAdmin');
		$result = $this->aclValidator->isPlaylistEditable($UID, $playlist);

		static::assertTrue($result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testIsPlaylistEditableModuleAdmin(): void
	{
		$UID = 1;
		$playlist = ['UID' => 2, 'company_id' => 4, 'playlist_id' => 12];

		$this->aclHelperMock->expects($this->once())->method('isModuleAdmin')
			->with($UID, 'playlists')
			->willReturn(true);
		$result = $this->aclValidator->isPlaylistEditable($UID, $playlist);

		static::assertTrue($result);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testIsPlaylistEditableEdgeEdition(): void
	{
		$UID = 1;
		$playlist = ['UID' => 2, 'company_id' => 4, 'playlist_id' => 12];

		$configMock = $this->createMock(Config::class);
		$this->aclHelperMock->expects($this->once())->method('getConfig')->willReturn($configMock);
		$configMock->method('getEdition')->willReturn(Config::PLATFORM_EDITION_EDGE);

		$result = $this->aclValidator->isPlaylistEditable($UID, $playlist);

		static::assertFalse($result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testIsPlaylistEditableSubAdminAccessFailed(): void
	{
		$UID = 1;
		$playlist = ['UID' => 2, 'company_id' => 4, 'playlist_id' => 12];

		$this->aclHelperMock->method('isSubAdmin')
			->with($UID, 'playlists')
			->willReturn(true);

		$this->aclHelperMock->method('hasSubAdminAccessOnCompany')
			->with($UID, $playlist['company_id'], 'playlists')
			->willReturn(false);

		$result = $this->aclValidator->isPlaylistEditable($UID, $playlist);

		static::assertFalse($result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testIsPlaylistEditableSubAdminAccessSucceed(): void
	{
		$UID = 1;
		$playlist = ['UID' => 2, 'company_id' => 4, 'playlist_id' => 12];

		$this->aclHelperMock->method('isSubAdmin')
			->with($UID, 'playlists')
			->willReturn(true);

		$this->aclHelperMock->method('hasSubAdminAccessOnCompany')
			->with($UID, $playlist['company_id'], 'playlists')
			->willReturn(true);

		$result = $this->aclValidator->isPlaylistEditable($UID, $playlist);

		static::assertTrue($result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testIsPlaylistEditableEditorAccess(): void
	{
		$UID = 1;
		$playlist = ['UID' => 2, 'company_id' => 4, 'playlist_id' => 12];

		$this->aclHelperMock->method('isEditor')
			->with($UID, 'playlists')
			->willReturn(true);

		$this->aclHelperMock->method('hasEditorAccessOnUnit')
			->with($UID, $playlist['playlist_id'], 'playlists')
			->willReturn(true);

		$result = $this->aclValidator->isPlaylistEditable($UID, $playlist);

		static::assertTrue($result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testIsPlaylistEditableNoAccess(): void
	{
		$UID = 1;
		$playlist = ['UID' => 2, 'company_id' => 4, 'playlist_id' => 12];

		$this->aclHelperMock->method('isEditor')
			->with($UID, 'playlists')
			->willReturn(true);

		$this->aclHelperMock->method('hasEditorAccessOnUnit')
			->with($UID, $playlist['playlist_id'], 'playlists')
			->willReturn(false);

		$result = $this->aclValidator->isPlaylistEditable($UID, $playlist);

		static::assertFalse($result);
	}
}
