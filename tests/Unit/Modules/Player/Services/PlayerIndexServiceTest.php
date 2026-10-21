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

use App\Modules\Player\Entities\PlayerEntity;
use App\Modules\Player\Enums\PlayerStatus;
use App\Modules\Player\IndexCreation\IndexProvider;
use App\Modules\Player\IndexCreation\PlayerDataAssembler;
use App\Modules\Player\Services\PlayerIndexService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class PlayerIndexServiceTest extends TestCase
{
	private PlayerDataAssembler&MockObject $playerDataAssemblerMock;
	private IndexProvider&MockObject $indexProviderMock;
	private LoggerInterface&MockObject $loggerMock;
	private PlayerEntity&Stub $playerEntityStub;
	private PlayerIndexService $service;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->playerDataAssemblerMock = $this->createMock(PlayerDataAssembler::class);
		$this->indexProviderMock       = $this->createMock(IndexProvider::class);
		$this->loggerMock              = $this->createMock(LoggerInterface::class);

		$this->playerEntityStub = $this->createStub(PlayerEntity::class);
		$this->service = new PlayerIndexService(
			$this->playerDataAssemblerMock,
			$this->indexProviderMock,
			$this->loggerMock
		);
	}

	#[Group('units')]
	public function testHandleIndexRequestLocalPlayer(): void
	{
		$userAgent   = 'ValidUserAgent';
		$filePath    = '/path/to/index';

		$this->loggerMock->expects($this->once())->method('info')
			->with('Connection from: ' . $userAgent);

		$serverData = ['HTTP_USER_AGENT' => 'TestAgent', 'REQUEST_METHOD' => 'HEAD', 'SERVER_NAME' => 'garlic-hub.ddev.site', 'REMOTE_ADDR' => '192.168.1.10', 'port' => '8080'];
		$this->playerDataAssemblerMock->expects($this->once())->method('setServerData')
			->with($serverData);

		$this->playerDataAssemblerMock->expects($this->once())->method('parseUserAgent')
			->with($userAgent)
			->willReturn(true);

		$this->playerDataAssemblerMock->expects($this->once())->method('handleLocalPlayer')
			->willReturn($this->playerEntityStub);

		$this->playerEntityStub->method('getStatus')->willReturn(PlayerStatus::RELEASED->value);
		$this->indexProviderMock->expects($this->once())->method('handleReleased')
			->with($this->playerEntityStub);

		$this->indexProviderMock->expects($this->once())->method('getFilePath')
			->willReturn($filePath);

		$result = $this->service->handleIndexRequest(1, $userAgent, $serverData, true);

		static::assertSame($filePath, $result);
	}

	#[Group('units')]
	public function testHandleIndexRequestUnreleasedRemotePlayer(): void
	{
		$userAgent = 'ValidUserAgent';
		$filePath  = '/path/to/index';

		$this->loggerMock->expects($this->once())->method('info')
			->with('Connection from: ' . $userAgent);

		$this->playerDataAssemblerMock->expects($this->once())->method('parseUserAgent')
			->with($userAgent)
			->willReturn(true);

		$serverData = ['HTTP_USER_AGENT' => 'TestAgent', 'REQUEST_METHOD' => 'HEAD', 'SERVER_NAME' => 'garlic-hub.ddev.site', 'REMOTE_ADDR' => '192.168.1.10', 'port' => '8080'];
		$this->playerDataAssemblerMock->expects($this->once())->method('setServerData')
			->with($serverData);

		$this->playerDataAssemblerMock->expects($this->once())->method('fetchDatabase')
			->willReturn($this->playerEntityStub);

		$this->playerEntityStub->method('getStatus')->willReturn(PlayerStatus::UNRELEASED->value);
		$this->indexProviderMock->expects($this->once())->method('handleUnreleased');

		$this->indexProviderMock->expects($this->once())->method('getFilePath')
			->willReturn($filePath);

		$result = $this->service->handleIndexRequest(1, $userAgent, $serverData,false);

		static::assertSame($filePath, $result);
	}

	#[Group('units')]
	public function testHandleIndexRequestNewRemotePlayer(): void
	{
		$userAgent = 'ValidUserAgent';
		$filePath  = '/path/to/index';

		$this->loggerMock->expects($this->once())->method('info')
			->with('Connection from: ' . $userAgent);

		$serverData = ['HTTP_USER_AGENT' => 'TestAgent', 'REQUEST_METHOD' => 'HEAD', 'SERVER_NAME' => 'garlic-hub.ddev.site', 'REMOTE_ADDR' => '192.168.1.10', 'port' => '8080'];

		$this->playerDataAssemblerMock->expects($this->once())->method('setServerData')
			->with($serverData);

		$this->playerDataAssemblerMock->expects($this->once())->method('parseUserAgent')
			->with($userAgent)
			->willReturn(true);

		$this->playerEntityStub->method('getStatus')->willReturn(PlayerStatus::UNREGISTERED->value);
		$this->playerDataAssemblerMock->expects($this->once())->method('insertNewPlayer')
			->with(1)
			->willReturn($this->playerEntityStub);

		$this->indexProviderMock->expects($this->once())->method('handleNew');

		$this->indexProviderMock->expects($this->once())->method('getFilePath')
			->willReturn($filePath);

		$result = $this->service->handleIndexRequest(1, $userAgent, $serverData,false);

		static::assertSame($filePath, $result);
	}

	#[Group('units')]
	public function testHandleIndexRequestDebugFtp(): void
	{
		$userAgent = 'ValidUserAgent';
		$filePath  = '/path/to/index';

		$this->loggerMock->expects($this->once())->method('info')
			->with('Connection from: ' . $userAgent);

		$this->playerDataAssemblerMock->expects($this->once())->method('parseUserAgent')
			->with($userAgent)
			->willReturn(true);

		$serverData = ['HTTP_USER_AGENT' => 'TestAgent', 'REQUEST_METHOD' => 'HEAD', 'SERVER_NAME' => 'garlic-hub.ddev.site', 'REMOTE_ADDR' => '192.168.1.10', 'port' => '8080'];
		$this->playerDataAssemblerMock->expects($this->once())->method('setServerData')
			->with($serverData);

		$this->playerDataAssemblerMock->expects($this->once())->method('fetchDatabase')
			->willReturn($this->playerEntityStub);

		$this->playerEntityStub->method('getStatus')->willReturn(PlayerStatus::DEBUG_FTP->value);
		$this->indexProviderMock->expects($this->once())->method('handleTestSMil');

		$this->indexProviderMock->expects($this->once())->method('getFilePath')
			->willReturn($filePath);

		$result = $this->service->handleIndexRequest(1, $userAgent, $serverData, false);

		static::assertSame($filePath, $result);
	}

	#[Group('units')]
	public function testHandleIndexRequestCorrectSmil(): void
	{
		$userAgent = 'ValidUserAgent';
		$filePath = '/path/to/index';

		$this->loggerMock->expects($this->once())->method('info')
			->with('Connection from: ' . $userAgent);

		$this->playerDataAssemblerMock->expects($this->once())->method('parseUserAgent')
			->with($userAgent)
			->willReturn(true);
		$serverData = ['HTTP_USER_AGENT' => 'TestAgent', 'REQUEST_METHOD' => 'HEAD', 'SERVER_NAME' => 'garlic-hub.ddev.site', 'REMOTE_ADDR' => '192.168.1.10', 'port' => '8080'];
		$this->playerDataAssemblerMock->expects($this->once())->method('setServerData')
			->with($serverData);

		$this->playerDataAssemblerMock->expects($this->once())->method('fetchDatabase')
			->willReturn($this->playerEntityStub);

		$this->playerEntityStub->method('getStatus')->willReturn(PlayerStatus::TEST_SMIL_OK->value);
		$this->indexProviderMock->expects($this->once())->method('handleCorrectSMil');

		$this->indexProviderMock->expects($this->once())->method('getFilePath')
			->willReturn($filePath);

		$result = $this->service->handleIndexRequest(1, $userAgent, $serverData,false);

		static::assertSame($filePath, $result);
	}

	#[Group('units')]
	public function testHandleIndexRequestCorruptSmil(): void
	{
		$userAgent   = 'ValidUserAgent';
		$filePath    = '/path/to/index';

		$this->loggerMock->expects($this->once())->method('info')
			->with('Connection from: ' . $userAgent);

		$this->playerDataAssemblerMock->expects($this->once())->method('parseUserAgent')
			->with($userAgent)
			->willReturn(true);
		$serverData = ['HTTP_USER_AGENT' => 'TestAgent', 'REQUEST_METHOD' => 'HEAD', 'SERVER_NAME' => 'garlic-hub.ddev.site', 'REMOTE_ADDR' => '192.168.1.10', 'port' => '8080'];
		$this->playerDataAssemblerMock->expects($this->once())->method('setServerData')
			->with($serverData);

		$this->playerDataAssemblerMock->expects($this->once())->method('fetchDatabase')
			->willReturn($this->playerEntityStub);

		$this->playerEntityStub->method('getStatus')->willReturn(PlayerStatus::TEST_SMIL_ERROR->value);
		$this->indexProviderMock->expects($this->once())->method('handleCorruptSMIL');

		$this->indexProviderMock->expects($this->once())->method('getFilePath')
			->willReturn($filePath);

		$result = $this->service->handleIndexRequest(1, $userAgent, $serverData, false);

		static::assertSame($filePath, $result);
	}

	#[Group('units')]
	public function testHandleIndexRequestCorruptContent(): void
	{
		$userAgent   = 'ValidUserAgent';
		$filePath    = '/path/to/index';

		$this->loggerMock->expects($this->once())->method('info')
			->with('Connection from: ' . $userAgent);

		$this->playerDataAssemblerMock->expects($this->once())->method('parseUserAgent')
			->with($userAgent)
			->willReturn(true);
		$serverData = ['HTTP_USER_AGENT' => 'TestAgent', 'REQUEST_METHOD' => 'HEAD', 'SERVER_NAME' => 'garlic-hub.ddev.site', 'REMOTE_ADDR' => '192.168.1.10', 'port' => '8080'];
		$this->playerDataAssemblerMock->expects($this->once())->method('setServerData')
			->with($serverData);

		$this->playerDataAssemblerMock->expects($this->once())->method('fetchDatabase')
			->willReturn($this->playerEntityStub);

		$this->playerEntityStub->method('getStatus')->willReturn(PlayerStatus::TEST_NO_CONTENT->value);
		$this->indexProviderMock->expects($this->once())->method('handleCorruptContent');

		$this->indexProviderMock->expects($this->once())->method('getFilePath')
			->willReturn($filePath);

		$result = $this->service->handleIndexRequest(1, $userAgent, $serverData, false);

		static::assertSame($filePath, $result);
	}

	#[Group('units')]
	public function testHandleIndexRequestCorruptPrefetch(): void
	{
		$userAgent   = 'ValidUserAgent';
		$filePath    = '/path/to/index';

		$this->loggerMock->expects($this->once())->method('info')
			->with('Connection from: ' . $userAgent);

		$this->playerDataAssemblerMock->expects($this->once())->method('parseUserAgent')
			->with($userAgent)
			->willReturn(true);
		$serverData = ['HTTP_USER_AGENT' => 'TestAgent', 'REQUEST_METHOD' => 'HEAD', 'SERVER_NAME' => 'garlic-hub.ddev.site', 'REMOTE_ADDR' => '192.168.1.10', 'port' => '8080'];
		$this->playerDataAssemblerMock->expects($this->once())->method('setServerData')
			->with($serverData);

		$this->playerDataAssemblerMock->expects($this->once())->method('fetchDatabase')
			->willReturn($this->playerEntityStub);

		$this->playerEntityStub->method('getStatus')->willReturn(PlayerStatus::TEST_NO_PREFETCH->value);
		$this->indexProviderMock->expects($this->once())->method('handleCorruptPrefetchContent');

		$this->indexProviderMock->expects($this->once())->method('getFilePath')
			->willReturn($filePath);

		$result = $this->service->handleIndexRequest(1, $userAgent, $serverData,false);

		static::assertSame($filePath, $result);
	}

	#[Group('units')]
	public function testHandleIndexWithException(): void
	{
		$userAgent   = 'ValidUserAgent';

		$this->loggerMock->expects($this->once())->method('info')
			->with('Connection from: ' . $userAgent);

		$this->playerDataAssemblerMock->expects($this->once())->method('parseUserAgent')
			->with($userAgent)
			->willReturn(true);
		$serverData = ['HTTP_USER_AGENT' => 'TestAgent', 'REQUEST_METHOD' => 'HEAD', 'SERVER_NAME' => 'garlic-hub.ddev.site', 'REMOTE_ADDR' => '192.168.1.10', 'port' => '8080'];
		$this->playerDataAssemblerMock->expects($this->once())->method('setServerData')
			->with($serverData);

		$this->playerDataAssemblerMock->expects($this->once())->method('fetchDatabase')
			->willReturn($this->playerEntityStub);

		$this->playerEntityStub->method('getStatus')->willReturn(14);

		$this->indexProviderMock->expects($this->never())->method('getFilePath');
		$this->loggerMock->expects($this->once())->method('error')
			->with('Error fetch Index: Unknown player status: 14');

		static::assertEmpty($this->service->handleIndexRequest(1, $userAgent, $serverData, false));
	}

	#[Group('units')]
	public function testHandleIndexRequestWithInvalidAgentHandlesForbidden(): void
	{
		$userAgent = 'InvalidUserAgent';

		$this->playerDataAssemblerMock->expects($this->once())->method('parseUserAgent')
			->with($userAgent)
			->willReturn(false);
		$serverData = ['HTTP_USER_AGENT' => 'TestAgent', 'REQUEST_METHOD' => 'HEAD', 'SERVER_NAME' => 'garlic-hub.ddev.site', 'REMOTE_ADDR' => '192.168.1.10', 'port' => '8080'];
		$this->playerDataAssemblerMock->expects($this->never())->method('setServerData');

		$this->indexProviderMock->expects($this->once())->method('handleForbidden');

		$this->loggerMock->expects($this->once())->method('info')
			->with('Connection from: ' . $userAgent);

		$this->service->handleIndexRequest(1, $userAgent, $serverData, false);
	}

	#[Group('units')]
	public function testHandleIndexRequestWhenExceptionThrownLogsErrorAndReturnsEmptyString(): void
	{
		$this->indexProviderMock->expects($this->never())->method($this->anything());
		$userAgent = 'ExceptionUserAgent';

		$this->playerDataAssemblerMock->expects($this->once())->method('parseUserAgent')
			->with($userAgent)
			->willThrowException(new RuntimeException('Parsing error'));

		$serverData = ['HTTP_USER_AGENT' => 'TestAgent', 'REQUEST_METHOD' => 'HEAD', 'SERVER_NAME' => 'garlic-hub.ddev.site', 'REMOTE_ADDR' => '192.168.1.10', 'port' => '8080'];
		$this->playerDataAssemblerMock->expects($this->never())->method('setServerData');

		$this->loggerMock->expects($this->once())->method('info')
			->with('Connection from: ' . $userAgent);

		$this->loggerMock->expects($this->once())->method('error')
			->with('Error fetch Index: Parsing error');

		$result = $this->service->handleIndexRequest(1, $userAgent, $serverData, true);

		static::assertSame('', $result);
	}


}
