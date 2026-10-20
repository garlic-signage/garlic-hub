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

namespace Tests\Unit\Modules\Playlists\Controller;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Modules\Playlists\Controller\PlaylistsController;
use App\Modules\Playlists\Helper\Playlists\Orchestrator;
use Doctrine\DBAL\Exception as DBALException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class PlaylistsControllerTest extends TestCase
{
	private Orchestrator&MockObject $orchestratorMock;
	private ServerRequestInterface&Stub $requestStub;
	private ResponseInterface&Stub $responseStub;
	private ResponseInterface&Stub $expectedResponseStub;
	private PlaylistsController $controller;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->orchestratorMock     = $this->createMock(Orchestrator::class);
		$this->requestStub          = $this->createStub(ServerRequestInterface::class);
		$this->responseStub         = $this->createStub(ResponseInterface::class);
		$this->expectedResponseStub = $this->createStub(ResponseInterface::class);

		$this->controller = new PlaylistsController($this->orchestratorMock);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testDeleteReturnsValidationError(): void
	{
		$input = ['playlist_id' => 12, 'csrf_token' => 'token'];
		$this->requestStub->method('getParsedBody')->willReturn($input);
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($input)
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateWithToken')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);
		$this->orchestratorMock->expects($this->never())->method('delete');

		$result = $this->controller->delete($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testDeleteSucceeds(): void
	{
		$input = ['playlist_id' => 12, 'csrf_token' => 'token'];
		$this->requestStub->method('getParsedBody')->willReturn($input);
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($input)
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateWithToken')
			->with($this->responseStub)
			->willReturn(null);
		$this->orchestratorMock->expects($this->once())->method('delete')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);

		$result = $this->controller->delete($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testToggleShuffleReturnsValidationError(): void
	{
		$input = ['playlist_id' => 12, 'csrf_token' => 'token'];
		$this->requestStub->method('getParsedBody')->willReturn($input);
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($input)
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateWithToken')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);
		$this->orchestratorMock->expects($this->never())->method('toggleShuffle');

		$result = $this->controller->toggleShuffle($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testToggleShuffleSucceeds(): void
	{
		$input = ['playlist_id' => 12, 'csrf_token' => 'token'];
		$this->requestStub->method('getParsedBody')->willReturn($input);
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($input)
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateWithToken')
			->with($this->responseStub)
			->willReturn(null);
		$this->orchestratorMock->expects($this->once())->method('toggleShuffle')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);

		$result = $this->controller->toggleShuffle($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testShufflePickingReturnsValidationError(): void
	{
		$input = ['playlist_id' => 12, 'csrf_token' => 'token'];
		$this->requestStub->method('getParsedBody')->willReturn($input);
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($input)
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateShufflePicking')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);
		$this->orchestratorMock->expects($this->never())->method('shufflePicking');

		$result = $this->controller->shufflePicking($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testShufflePickingSucceeds(): void
	{
		$input = ['playlist_id' => 12, 'csrf_token' => 'token'];
		$this->requestStub->method('getParsedBody')->willReturn($input);
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($input)
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateShufflePicking')
			->with($this->responseStub)
			->willReturn(null);
		$this->orchestratorMock->expects($this->once())->method('shufflePicking')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);

		$result = $this->controller->shufflePicking($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testLoadZoneReturnsValidationError(): void
	{
		$args = ['playlist_id' => 12];
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($args)
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validate')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);
		$this->orchestratorMock->expects($this->never())->method('loadZone');

		$result = $this->controller->loadZone($this->requestStub, $this->responseStub, $args);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testLoadZoneSucceeds(): void
	{
		$args = ['playlist_id' => 12];
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($args)
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validate')
			->with($this->responseStub)
			->willReturn(null);
		$this->orchestratorMock->expects($this->once())->method('loadZone')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);

		$result = $this->controller->loadZone($this->requestStub, $this->responseStub, $args);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testSaveZoneReturnsValidationError(): void
	{
		$this->requestStub->method('getParsedBody')->willReturn(['zones' => [], 'csrf_token' => 'token']);
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with(['zones' => [], 'csrf_token' => 'token', 'playlist_id' => 12])
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateWithToken')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);
		$this->orchestratorMock->expects($this->never())->method('saveZone');

		$result = $this->controller->saveZone($this->requestStub, $this->responseStub, ['playlist_id' => 12]);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testSaveZoneSucceeds(): void
	{
		$this->requestStub->method('getParsedBody')->willReturn(['zones' => [], 'csrf_token' => 'token']);
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with(['zones' => [], 'csrf_token' => 'token', 'playlist_id' => 12])
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateWithToken')
			->with($this->responseStub)
			->willReturn(null);
		$this->orchestratorMock->expects($this->once())->method('saveZone')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);

		$result = $this->controller->saveZone($this->requestStub, $this->responseStub, ['playlist_id' => 12]);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testFindByIdReturnsValidationError(): void
	{
		$args = ['playlist_id' => 12];
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($args)
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validate')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);
		$this->orchestratorMock->expects($this->never())->method('findById');

		$result = $this->controller->findById($this->requestStub, $this->responseStub, $args);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testFindByIdSucceeds(): void
	{
		$args = ['playlist_id' => 12];
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($args)
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validate')
			->with($this->responseStub)
			->willReturn(null);
		$this->orchestratorMock->expects($this->once())->method('findById')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);

		$result = $this->controller->findById($this->requestStub, $this->responseStub, $args);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testFindByName(): void
	{
		$args = ['playlist_name' => 'play'];
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($args)
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('findByName')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);

		$result = $this->controller->findByName($this->requestStub, $this->responseStub, $args);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testFindForPlayerAssignment(): void
	{
		$args = ['playlist_name' => 'play'];
		$this->orchestratorMock->expects($this->once())->method('setInput')
			->with($args)
			->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('findForPlayerAssignment')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);

		$result = $this->controller->findForPlayerAssignment($this->requestStub, $this->responseStub, $args);

		static::assertSame($this->expectedResponseStub, $result);
	}
}
