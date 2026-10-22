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

use App\Shared\Acl\AclHelper;
use App\Framework\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Player\Services\AclValidator;
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

		$this->aclValidator  = new AclValidator($this->aclHelperMock);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testIsPlaylistEditableModuleAdmin(): void
	{
		$UID = 1;
		$player = ['UID' => 2, 'company_id' => 4, 'player_id' => 12];

		$this->aclHelperMock->expects($this->once())->method('isModuleAdmin')
			->with($UID, 'player')
			->willReturn(true);
		$result = $this->aclValidator->isPlayerEditable($UID, $player);

		static::assertTrue($result);
	}

	/**
	 * @throws ModuleException
	 * @throws Exception
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testIsPlaylistEditableEdgeEdition(): void
	{
		$UID = 1;
		$player = ['UID' => 2, 'company_id' => 4, 'player_id' => 12];

		$configStub = $this->createStub(Config::class);
		$this->aclHelperMock->expects($this->once())->method('getConfig')->willReturn($configStub);
		$configStub->method('getEdition')->willReturn(Config::PLATFORM_EDITION_EDGE);

		$result = $this->aclValidator->isPlayerEditable($UID, $player);

		static::assertFalse($result);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testIsPlaylistEditableSubAdminAccessFailed(): void
	{
		$UID = 1;
		$player = ['UID' => 2, 'company_id' => 4, 'player_id' => 12];

		$this->aclHelperMock->method('isSubAdmin')
			->with($UID, 'player')
			->willReturn(true);

		$this->aclHelperMock->method('hasSubAdminAccessOnCompany')
			->with($UID, $player['company_id'], 'player')
			->willReturn(false);

		$result = $this->aclValidator->isPlayerEditable($UID, $player);

		static::assertFalse($result);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testIsPlaylistEditableSubAdminAccessSucceed(): void
	{
		$UID = 1;
		$player = ['UID' => 2, 'company_id' => 4, 'player_id' => 12];

		$this->aclHelperMock->method('isSubAdmin')
			->with($UID, 'player')
			->willReturn(true);

		$this->aclHelperMock->method('hasSubAdminAccessOnCompany')
			->with($UID, $player['company_id'], 'player')
			->willReturn(true);

		$result = $this->aclValidator->isPlayerEditable($UID, $player);

		static::assertTrue($result);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testIsPlaylistEditableEditorAccess(): void
	{
		$UID = 1;
		$player = ['UID' => 2, 'company_id' => 4, 'player_id' => 12];

		$this->aclHelperMock->method('isEditor')
			->with($UID, 'player')
			->willReturn(true);

		$this->aclHelperMock->method('hasEditorAccessOnUnit')
			->with($UID, $player['player_id'], 'player')
			->willReturn(true);

		$result = $this->aclValidator->isPlayerEditable($UID, $player);

		static::assertTrue($result);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testIsPlaylistEditableNoAccess(): void
	{
		$UID = 1;
		$player = ['UID' => 2, 'company_id' => 4, 'player_id' => 12];

		$this->aclHelperMock->method('isEditor')
			->with($UID, 'player')
			->willReturn(true);

		$this->aclHelperMock->method('hasEditorAccessOnUnit')
			->with($UID, $player['player_id'], 'player')
			->willReturn(false);

		$result = $this->aclValidator->isPlayerEditable($UID, $player);

		static::assertFalse($result);
	}

}
