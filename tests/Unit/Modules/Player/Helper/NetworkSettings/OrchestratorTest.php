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

namespace Tests\Unit\Modules\Player\Helper\NetworkSettings;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Modules\Player\Helper\NetworkSettings\FormInputHandler;
use App\Modules\Player\Helper\NetworkSettings\Orchestrator;
use App\Modules\Player\Helper\NetworkSettings\SettingsFormBuilder;
use App\Modules\Player\Services\PlayerService;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrchestratorTest extends TestCase
{
	private PlayerService&MockObject $playerServiceMock;
	private FormInputHandler&MockObject $inputHandlerMock;
	private SettingsFormBuilder&MockObject $formBuilderMock;
	private Orchestrator $orchestrator;

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->playerServiceMock = $this->createMock(PlayerService::class);
		$this->inputHandlerMock  = $this->createMock(FormInputHandler::class);
		$this->formBuilderMock   = $this->createMock(SettingsFormBuilder::class);

		$this->orchestrator = new Orchestrator(
			$this->playerServiceMock,
			$this->inputHandlerMock,
			$this->formBuilderMock
		);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testLoadPlayerWithoutIdTouchesNothing(): void
	{
		$this->inputHandlerMock->expects($this->never())->method($this->anything());
		$this->formBuilderMock->expects($this->never())->method($this->anything());
		$this->playerServiceMock->expects($this->never())->method($this->anything());

		static::assertSame([], $this->orchestrator->loadPlayer(0));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testLoadPlayerChecksAclForCurrentUser(): void
	{
		$this->inputHandlerMock->expects($this->never())->method($this->anything());
		$this->formBuilderMock->expects($this->never())->method($this->anything());
		$player = ['player_id' => 5, 'player_name' => 'Lobby'];
		$this->playerServiceMock->expects($this->once())->method('fetchAclCheckedPlayerData')
			->with(5)->willReturn($player);

		static::assertSame($player, $this->orchestrator->loadPlayer(5));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildForm(): void
	{
		$this->playerServiceMock->expects($this->never())->method($this->anything());
		$this->inputHandlerMock->expects($this->never())->method($this->anything());
		$form = ['hidden' => [], 'visible' => []];
		$this->formBuilderMock->expects($this->once())->method('buildForm')
			->with(['player_id' => 5])->willReturn($form);

		static::assertSame($form, $this->orchestrator->buildForm(['player_id' => 5]));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testStoreReturnsValidationErrors(): void
	{
		$this->formBuilderMock->expects($this->never())->method($this->anything());
		$this->inputHandlerMock->expects($this->once())->method('validate')
			->with(['is_intranet' => 1])->willReturn(['No API endpoint.']);
		$this->inputHandlerMock->expects($this->never())->method('getParsed');
		$this->playerServiceMock->expects($this->never())->method('updatePlayer');

		static::assertSame(
			['success' => false, 'errors' => ['No API endpoint.']],
			$this->orchestrator->store(5, ['is_intranet' => 1])
		);
	}

	/**
	 * A save without changes affects no rows and must count as success.
	 *
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testStoreSucceedsEvenWithoutAffectedRows(): void
	{
		$this->formBuilderMock->expects($this->never())->method($this->anything());
		$parsed = ['csrf_token' => 'token', 'is_intranet' => false, 'api_endpoint' => ''];
		$this->inputHandlerMock->expects($this->once())->method('validate')->willReturn([]);
		$this->inputHandlerMock->expects($this->once())->method('getParsed')->willReturn($parsed);
		$this->playerServiceMock->expects($this->once())->method('updatePlayer')
			->with(5, $parsed)->willReturn(0);

		static::assertSame(['success' => true], $this->orchestrator->store(5, []));
	}
}
