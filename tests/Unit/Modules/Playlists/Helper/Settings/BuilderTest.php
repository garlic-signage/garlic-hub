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
namespace Tests\Unit\Modules\Playlists\Helper\Settings;

use App\Framework\Core\Session;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\FormParameters\BaseParameters;
use App\Framework\Utils\Html\FieldInterface;
use App\Modules\Playlists\Helper\PlaylistMode;
use App\Modules\Playlists\Helper\Settings\Builder;
use App\Modules\Playlists\Helper\Settings\FormElementsCreator;
use App\Modules\Playlists\Helper\Settings\Parameters;
use App\Modules\Playlists\Helper\Settings\Validator;
use App\Modules\Playlists\Services\AclValidator;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\InvalidArgumentException;

class BuilderTest extends TestCase
{
	private FormElementsCreator&MockObject $collectorMock;
	private AclValidator&MockObject $aclValidatorMock;
	private Validator&MockObject $validatorMock;
	private Parameters&MockObject $parametersMock;
	private Session&MockObject $sessionMock;
	private Builder $builder;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->collectorMock    = $this->createMock(FormElementsCreator::class);
		$this->aclValidatorMock = $this->createMock(AclValidator::class);
		$this->validatorMock    = $this->createMock(Validator::class);
		$this->parametersMock   = $this->createMock(Parameters::class);
		$this->sessionMock      = $this->createMock(Session::class);

		$this->builder = new Builder($this->aclValidatorMock, $this->parametersMock, $this->validatorMock, $this->collectorMock);
	}

	#[Group('units')]
	public function testInit(): void
	{
		$user = ['UID' => 123, 'username' => 'username'];
		$this->sessionMock->expects($this->once())->method('get')->with('user')->willReturn($user);
		static::assertSame($this->builder, $this->builder->init($this->sessionMock));
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testConfigNewParameterWithSimpleAdmin(): void
	{
		$this->setStandardMocks();

		$this->aclValidatorMock->expects($this->once())
			->method('isSimpleAdmin')
			->with(567)
			->willReturn(true);

		$this->parametersMock->expects($this->once())->method('addPlaylistMode');
		$this->parametersMock->expects($this->once())->method('addOwner');
		$this->parametersMock->expects($this->once())->method('addTimeLimit');

		$this->builder->configNewParameter('internal');
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testConfigNewParameterWithoutAdminPrivileges(): void
	{
		$this->setStandardMocks();
		$this->aclValidatorMock->expects($this->once())
			->method('isSimpleAdmin')
			->willReturn(false);

		$this->parametersMock->expects($this->once())->method('addPlaylistMode');
		$this->parametersMock->expects($this->never())->method('addOwner');
		$this->parametersMock->expects($this->never())->method('addTimeLimit');

		$this->builder->configNewParameter('internal');
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testConfigNewParameterWithNoTimeLimit(): void
	{
		$this->setStandardMocks();
		$this->aclValidatorMock->expects($this->once())
			->method('isSimpleAdmin')
			->willReturn(true);

		$this->parametersMock->expects($this->once())->method('addPlaylistMode');
		$this->parametersMock->expects($this->once())->method('addOwner');
		$this->parametersMock->expects($this->never())->method('addTimeLimit');

		$this->builder->configNewParameter('external');
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testConfigEditParameterAddsPlaylistId(): void
	{
		$this->setStandardMocks();
		$this->parametersMock->expects($this->once())->method('addPlaylistId');
		$this->aclValidatorMock->method('isAdmin')->willReturn(true);

		$this->builder->configEditParameter(['UID' => 1, 'company_id' => 1, 'playlist_mode' => '', 'playlist_name' => '']);
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testConfigEditParameterAddsOwnerIfAdmin(): void
	{
		$this->setStandardMocks();

		$this->parametersMock->method('addPlaylistId');
		$this->aclValidatorMock->method('isAdmin')->willReturn(true);

		$this->parametersMock->expects($this->once())->method('addOwner');

		$this->builder->configEditParameter(['UID' => 1, 'company_id' => 123, 'playlist_mode' => '', 'playlist_name' => '']);
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testConfigEditParameterAddsTimeLimitIfTimeLimitPlaylist(): void
	{
		$this->setStandardMocks();

		$this->parametersMock->method('addPlaylistId');
		$this->parametersMock->method('addOwner');
		$this->aclValidatorMock->method('isAdmin')->willReturn(true);

		$this->parametersMock->expects($this->once())->method('addTimeLimit');

		$this->builder->configEditParameter(['UID' => 1, 'company_id' => 123, 'playlist_mode' => 'internal', 'playlist_name' => '']);
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testConfigEditParameterReturnsIfNotAdmin(): void
	{
		$this->setStandardMocks();

		$this->parametersMock->expects($this->once())->method('addPlaylistId');
		$this->aclValidatorMock->method('isAdmin')->willReturn(false);

		$this->parametersMock->expects($this->never())->method('addOwner');
		$this->parametersMock->expects($this->never())->method('addTimeLimit');

		$this->builder->configEditParameter(['UID' => 1, 'company_id' => 456, 'playlist_mode' => 'internal', 'playlist_name' => '']);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws Exception
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildFormWithEmptyPlaylistStandard(): void
	{
		$this->setStandardMocks();

		$fieldInterfaceMock = $this->createMock(FieldInterface::class);

		$form = [
			'playlist_name' => $fieldInterfaceMock,
			'csrf_token' => $fieldInterfaceMock
		];

		$this->parametersMock->method('hasParameter')->willReturn(false);

		$this->collectorMock->expects($this->once())
			->method('createPlaylistNameField')
			->with('')
			->willReturn($fieldInterfaceMock);

		$this->collectorMock->expects($this->once())
			->method('createCSRFTokenField')
			->willReturn($fieldInterfaceMock);

		$preparedFormWithAllFields = ['key1' => 'value1', 'key2' => 123, 'key3' => true];
		$this->collectorMock->expects($this->once())
			->method('prepareForm')
			->with($form)
			->willReturn($preparedFormWithAllFields);

		$result = $this->builder->buildForm([]);

		static::assertSame($preparedFormWithAllFields, $result);
	}

	/**
	 * @throws ModuleException
	 * @throws Exception
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildFormWithAllParameters(): void
	{
		$playlist = [
			BaseParameters::PARAMETER_UID => 123,
			'username' => 'mockUsername',
			Parameters::PARAMETER_TIME_LIMIT => 600,
			Parameters::PARAMETER_PLAYLIST_ID => 456,
			Parameters::PARAMETER_PLAYLIST_MODE => 2,
			Parameters::PARAMETER_PLAYLIST_NAME => 'Test Playlist'
		];

		$this->setStandardMocks();

		$this->parametersMock->method('hasParameter')
			->willReturnMap([
				[BaseParameters::PARAMETER_UID, true],
				[Parameters::PARAMETER_TIME_LIMIT, true],
				[Parameters::PARAMETER_PLAYLIST_ID, true],
				[Parameters::PARAMETER_PLAYLIST_MODE, true]
			]);

		$this->parametersMock->method('getDefaultValueOfParameter')
			->with(Parameters::PARAMETER_TIME_LIMIT)
			->willReturn(500);

		$fieldInterfaceMock = $this->createMock(FieldInterface::class);

		$this->collectorMock->method('createPlaylistNameField')
			->with( $playlist[Parameters::PARAMETER_PLAYLIST_NAME])
			->willReturn($fieldInterfaceMock);

		$this->collectorMock->method('createUIDField')
			->with($playlist[BaseParameters::PARAMETER_UID])
			->willReturn($fieldInterfaceMock);

		$this->collectorMock->method('createTimeLimitField')
			->with(600, 500)
			->willReturn($fieldInterfaceMock);

		$this->collectorMock->method('createHiddenPlaylistIdField')
			->with(456)
			->willReturn($fieldInterfaceMock);

		$this->collectorMock->method('createPlaylistModeField')
			->with(2)
			->willReturn($fieldInterfaceMock);

		$this->collectorMock->method('createCSRFTokenField')
			->willReturn($fieldInterfaceMock);

		$preparedFormWithAllFields = ['key1' => 'value1', 'key2' => 123, 'key3' => true];

		$this->collectorMock->expects($this->once())
			->method('prepareForm')
			->with([
				'playlist_name' => $fieldInterfaceMock,
				'UID' => $fieldInterfaceMock,
				'time_limit' => $fieldInterfaceMock,
				'playlist_id' => $fieldInterfaceMock,
				'playlist_mode' => $fieldInterfaceMock,
				'csrf_token' => $fieldInterfaceMock,
			])
			->willReturn($preparedFormWithAllFields);

		$result = $this->builder->buildForm($playlist);

		static::assertSame($preparedFormWithAllFields, $result);
	}


	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testHandleUserInput(): void
	{
		$userInput = ['key1' => 'value1', 'key2' => 123, 'key3' => true];
		$this->parametersMock->expects($this->once())->method('setUserInputs')
			->with($userInput)
			->willReturnSelf();
		$this->parametersMock->expects($this->once())->method('parseInputAllParameters');

		$this->validatorMock->expects($this->once())->method('validateUserInput')->with($userInput);

		$this->builder->handleUserInput($userInput);
	}


	private function setStandardMocks(): void
	{
		$user = ['UID' => 567, 'username' => 'username'];
		$this->sessionMock->expects($this->once())->method('get')->with('user')->willReturn($user);
		$this->builder->init($this->sessionMock);
	}
}
