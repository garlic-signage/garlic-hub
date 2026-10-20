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


namespace Tests\Unit\Modules\Player\Helper\NetworkSettings;

use App\Framework\Core\Session;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Player\Helper\NetworkSettings\Builder;
use App\Modules\Player\Helper\NetworkSettings\Facade;
use App\Modules\Player\Helper\NetworkSettings\Parameters;
use App\Modules\Player\Services\PlayerService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FacadeTest extends TestCase
{
	private Builder&MockObject $settingsFormBuilderMock;
	private PlayerService&MockObject $playerServiceMock;
	private Parameters&MockObject $settingsParametersMock;
	private Translator&MockObject $translatorMock;
	private Facade $facade;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->settingsFormBuilderMock = $this->createMock(Builder::class);
		$this->playerServiceMock           = $this->createMock(PlayerService::class);
		$this->settingsParametersMock  = $this->createMock(Parameters::class);
		$this->translatorMock          = $this->createMock(Translator::class);
		$this->facade = new Facade(
			$this->settingsFormBuilderMock,
			$this->playerServiceMock,
			$this->settingsParametersMock,
		);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testInit(): void
	{
		$sessionMock = $this->createMock(Session::class);

		$sessionMock->expects($this->once())
			->method('get')
			->with('user')
			->willReturn(['UID' => 123]);

		$this->playerServiceMock->expects($this->once())
			->method('setUID')
			->with(123);

		$this->facade->init($this->translatorMock, $sessionMock);
	}

	#[Group('units')]
	public function testLoadPlayerForEdit(): void
	{
		$playerId = 1;
		$expectedPlayerData = [
			'player_id' => 1,
			'player_name' => 'Test Player',
			'model' => 1234,
			'is_intranet' => 0,
			'api_endpoint' => 'http://example.com/api'
		];

		$this->playerServiceMock->expects($this->once())
			->method('fetchAclCheckedPlayerData')
			->with($playerId)
			->willReturn($expectedPlayerData);

		$this->facade->loadPlayerForEdit($playerId);

		static::assertSame($expectedPlayerData, $this->facade->getPlayer());
	}

	/**
	 * @return void
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testConfigureFormParameterWithPlayerId(): void
	{
		$post = ['player_id' => 123, 'player_name' => 'Test Player'];
		$expectedResult = ['processed_input_key' => 'processed_value'];

		$this->playerServiceMock->expects($this->once())->method('fetchAclCheckedPlayerData')
			->with(123)
			->willReturn(['player_id' => 123, 'player_name' => 'Test Player']);

		$this->settingsFormBuilderMock->expects($this->once())
			->method('handleUserInput')
			->with($post)
			->willReturn($expectedResult);

		$result = $this->facade->configureFormParameter($post);

		static::assertSame($expectedResult, $result);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testConfigureFormParameterFailsNoPlayer(): void
	{
		$post = ['player_id' => 123, 'player_name' => 'Test Player'];
		$expectedResult = ['player_not_found'];

		$this->playerServiceMock->expects($this->once())->method('fetchAclCheckedPlayerData')
		->with(123)
		->willReturn([]);

		$this->translatorMock->expects($this->once())
			->method('translate')
			->with('player_not_found', 'player')
			->willReturn('player_not_found');

		$this->initMock();
		$result = $this->facade->configureFormParameter($post);

		static::assertSame($expectedResult, $result);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testConfigureFormParameterWithoutPlayerId(): void
	{
		$post = [];
		$expectedResult = ['player_not_found'];

		$this->playerServiceMock->expects($this->never())->method('fetchAclCheckedPlayerData');

		$this->translatorMock->expects($this->once())
			->method('translate')
			->with('player_not_found', 'player')
			->willReturn('player_not_found');

		$this->initMock();
		$result = $this->facade->configureFormParameter($post);

		static::assertSame($expectedResult, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testStoreNetworkDataSuccess(): void
	{
		$expectedKeys = ['api_endpoint', 'is_intranet'];
		$expectedValues = ['http://example-api.com', 1];
		$playerId = 123;

		$this->settingsParametersMock->expects($this->once())->method('getInputParametersKeys')
			->willReturn($expectedKeys);

		$this->settingsParametersMock->expects($this->once())->method('getInputValuesArray')
			->willReturn($expectedValues);

		$this->playerServiceMock->expects($this->once())->method('updatePlayer')
			->with($playerId, array_combine($expectedKeys, $expectedValues))
			->willReturn($playerId);

		$this->loadPlayerMock();

		$result = $this->facade->storeNetworkData();

		static::assertSame($playerId, $result);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testGetUserServiceErrorsWithErrors(): void
	{
		$errors = ['error_1', 'error_2'];
		$translatedErrors = ['Translated Error 1', 'Translated Error 2'];

		$this->playerServiceMock->expects($this->once())
			->method('getErrorMessages')
			->willReturn($errors);

		$this->translatorMock->expects($this->exactly(2))
			->method('translate')
			->willReturnMap([
				['error_1', 'player', [], $translatedErrors[0]],
				['error_2', 'player', [], $translatedErrors[1]],
			]);
		$this->initMock();

		$result = $this->facade->getUserServiceErrors();

		static::assertSame($translatedErrors, $result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testGetUserServiceErrorsWithoutErrors(): void
	{
		$this->playerServiceMock->expects($this->once())
			->method('getErrorMessages')
			->willReturn([]);

		$this->translatorMock->expects($this->never())
			->method('translate');

		$result = $this->facade->getUserServiceErrors();

		static::assertSame([], $result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException|Exception
	 */
	#[Group('units')]
	public function testPrepareUITemplateWithExistingPlayer(): void
	{
		$post = ['parameter' => 'value'];
		$playerId = 123;
		$name = 'Existing Player';

		$this->translatorMock->expects($this->exactly(2))->method('translate')
			->willReturnMap([
				['api_connectivity', 'player', [], 'API Connectivity'],
				['save', 'main', [], 'Save']
			]);

		$this->settingsFormBuilderMock->expects($this->once())->method('buildForm')
			->with($post)
			->willReturn(['field1' => 'value1']);

		$playerData = [
			'player_id' => $playerId,
			'player_name' => $name,
			'model' => 1,
			'is_intranet' => 1,
			'api_endpoint' => 'https://api.example.com'
		];

		$this->playerServiceMock->expects($this->once())->method('fetchAclCheckedPlayerData')
			->with($playerId)
			->willReturn($playerData);

		$this->initMock();
		$this->facade->loadPlayerForEdit($playerId);

		$result = $this->facade->prepareUITemplate($post);

		self::assertSame([
			'field1' => 'value1',
			'title' => 'API Connectivity: Existing Player',
			'additional_css' => ['/css/player/edit.css'],
			'footer_modules' => [],
			'template_name' => 'player/edit',
			'form_action' => '/player/connectivity',
			'save_button_label' => 'Save'
		], $result);
	}

	/**
	 * @throws Exception
	 */
	private function initMock(): void
	{
		$sessionMock = $this->createMock(Session::class);

		$sessionMock->expects($this->once())
			->method('get')
			->with('user')
			->willReturn(['UID' => 123]);

		$this->playerServiceMock->expects($this->once())
			->method('setUID')
			->with(123);

		$this->facade->init($this->translatorMock, $sessionMock);
	}

	private function loadPlayerMock(): void
	{
		$playerId = 123;
		$expectedPlayerData = [
			'player_id' => 123,
			'player_name' => 'Test Player',
			'model' => 1234,
			'is_intranet' => 0,
			'api_endpoint' => 'http://example.com/api'
		];

		$this->playerServiceMock->expects($this->once())
			->method('fetchAclCheckedPlayerData')
			->with($playerId)
			->willReturn($expectedPlayerData);

		$this->facade->loadPlayerForEdit($playerId);
	}
}
