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

namespace Tests\Unit\Modules\Playlists\Helper\Settings;

use App\Framework\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Modules\Auth\UserSession;
use App\Modules\Playlists\Helper\Settings\Parameters;
use App\Modules\Playlists\Helper\Settings\SettingsParametersPolicy;
use App\Modules\Playlists\Services\AclValidator;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SettingsParametersPolicyTest extends TestCase
{
	private Parameters&MockObject $parametersMock;
	private AclValidator&MockObject $aclValidatorMock;
	private SettingsParametersPolicy $policy;

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->parametersMock   = $this->createMock(Parameters::class);
		$this->aclValidatorMock = $this->createMock(AclValidator::class);
		$userSessionStub        = $this->createStub(UserSession::class);
		$userSessionStub->method('getUID')->willReturn(7);

		$this->policy = new SettingsParametersPolicy($this->parametersMock, $this->aclValidatorMock, $userSessionStub);
	}

	/**
	 * @return array<string, array{string, string, bool, bool}>
	 */
	public static function provideAdminCases(): array
	{
		// mode, edition, resolution, time limit
		return [
			'master core'       => ['master', Config::PLATFORM_EDITION_CORE, true, true],
			'master edge'       => ['master', Config::PLATFORM_EDITION_EDGE, true, false],
			'multizone core'    => ['multizone', Config::PLATFORM_EDITION_CORE, true, false],
			'internal core'     => ['internal', Config::PLATFORM_EDITION_CORE, false, true],
			'external core'     => ['external', Config::PLATFORM_EDITION_CORE, false, false],
		];
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws UserException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	#[DataProvider('provideAdminCases')] #[Group('units')]
	public function testAddCreateFormElementsForSimpleAdmin(string $mode, string $edition, bool $resolution, bool $timeLimit): void
	{
		$this->aclValidatorMock->expects($this->once())->method('isSimpleAdmin')->with(7)->willReturn(true);
		$this->expectEdition($edition);
		$this->parametersMock->expects($this->once())->method('addPlaylistMode');
		$this->parametersMock->expects($this->never())->method('addPlaylistId');
		$this->parametersMock->expects($resolution ? $this->once() : $this->never())->method('addResolution');
		$this->parametersMock->expects($this->once())->method('addOwner');
		$this->parametersMock->expects($timeLimit ? $this->once() : $this->never())->method('addTimeLimit');

		$this->policy->addCreateFormElements($mode);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws UserException
	 */
	#[Group('units')]
	public function testAddCreateFormElementsWithoutAdminRights(): void
	{
		$this->aclValidatorMock->expects($this->once())->method('isSimpleAdmin')->with(7)->willReturn(false);
		$this->aclValidatorMock->expects($this->never())->method('getConfig');
		$this->parametersMock->expects($this->once())->method('addPlaylistMode');
		$this->parametersMock->expects($this->once())->method('addResolution');
		$this->parametersMock->expects($this->never())->method('addOwner');
		$this->parametersMock->expects($this->never())->method('addTimeLimit');

		$this->policy->addCreateFormElements('master');
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	#[DataProvider('provideAdminCases')] #[Group('units')]
	public function testAddEditFormElementsForAdmin(string $mode, string $edition, bool $resolution, bool $timeLimit): void
	{
		$playlist = ['UID' => 3, 'company_id' => 1, 'playlist_mode' => $mode];
		$this->aclValidatorMock->expects($this->once())->method('isAdmin')->with(7, $playlist)->willReturn(true);
		$this->expectEdition($edition);
		$this->parametersMock->expects($this->never())->method('addPlaylistMode');
		$this->parametersMock->expects($this->once())->method('addPlaylistId');
		$this->parametersMock->expects($resolution ? $this->once() : $this->never())->method('addResolution');
		$this->parametersMock->expects($this->once())->method('addOwner');
		$this->parametersMock->expects($timeLimit ? $this->once() : $this->never())->method('addTimeLimit');

		$this->policy->addEditFormElements($playlist);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testAddEditFormElementsWithoutAdminRights(): void
	{
		$this->aclValidatorMock->expects($this->once())->method('isAdmin')->willReturn(false);
		$this->aclValidatorMock->expects($this->never())->method('getConfig');
		$this->parametersMock->expects($this->once())->method('addPlaylistId');
		$this->parametersMock->expects($this->never())->method('addResolution');
		$this->parametersMock->expects($this->never())->method('addOwner');
		$this->parametersMock->expects($this->never())->method('addTimeLimit');

		$this->policy->addEditFormElements(['UID' => 3, 'company_id' => 1, 'playlist_mode' => 'internal']);
	}

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	private function expectEdition(string $edition): void
	{
		$configStub = $this->createStub(Config::class);
		$configStub->method('getEdition')->willReturn($edition);
		$this->aclValidatorMock->method('getConfig')->willReturn($configStub);
	}
}
