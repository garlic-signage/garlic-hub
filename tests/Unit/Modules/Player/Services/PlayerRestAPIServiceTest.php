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

use App\Framework\SimpleApiExecutor;
use App\Modules\Player\Services\PlayerRestAPIService;
use App\Modules\Player\Services\PlayerTokenService;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PlayerRestAPIServiceTest extends TestCase
{
	use PHPMock;

	private PlayerRestAPIService $service;
	private SimpleApiExecutor&MockObject $apiExecutorMock;
	private PlayerTokenService&MockObject $playerTokenServiceMock;
	private LoggerInterface&MockObject $loggerMock;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->apiExecutorMock = $this->createMock(SimpleApiExecutor::class);
		$this->playerTokenServiceMock = $this->createMock(PlayerTokenService::class);
		$this->loggerMock = $this->createMock(LoggerInterface::class);

		$this->service = new PlayerRestAPIService(
			$this->apiExecutorMock,
			$this->playerTokenServiceMock,
			$this->loggerMock
		);
	}

	#[Group('units')]
	public function testAuthenticateReturnsTrueWhenTokenExists(): void
	{
		$this->apiExecutorMock->expects($this->never())->method($this->anything());
		$this->loggerMock->expects($this->never())->method($this->anything());
		$this->playerTokenServiceMock->expects($this->once())->method('hasValidToken')
			->with(1)
			->willReturn(true);

		$result = $this->service->authenticate('http://example.com', 'user', 'pass', 1);

		static::assertTrue($result);
	}

	#[Group('units')]
	public function testAuthenticateReturnsFalseWhenApiExecutorFails(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$this->playerTokenServiceMock->expects($this->once())->method('hasValidToken')
			->with(1)
			->willReturn(false);

		$this->apiExecutorMock->expects($this->once())->method('executeAuth')
			->with('http://example.com/oauth2/token', [
				'grant_type' => 'password',
				'username' => 'user',
				'password' => 'pass',
			])
			->willReturn(false);

		$result = $this->service->authenticate('http://example.com', 'user', 'pass', 1);

		static::assertFalse($result);
	}

	#[Group('units')]
	public function testAuthenticateReturnsFalseWhenResponseIsInvalid(): void
	{
		$this->playerTokenServiceMock->expects($this->once())->method('hasValidToken')
			->with(1)
			->willReturn(false);

		$this->apiExecutorMock->expects($this->once())->method('executeAuth')
			->willReturn(true);

		$this->apiExecutorMock->expects($this->once())->method('getBodyContentsArray')
			->willReturn(['invalid_field' => 'value']);

		$this->loggerMock
			->expects($this->once())
			->method('error')
			->with('Invalid authentication response', ['response' => ['invalid_field' => 'value']]);

		$result = $this->service->authenticate('http://example.com', 'user', 'pass', 1);

		static::assertFalse($result);
	}

	#[Group('units')]
	public function testAuthenticateReturnsTrueWhenTokenStoredSuccessfully(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$this->playerTokenServiceMock->expects($this->once())->method('hasValidToken')
			->with(1)
			->willReturn(false);

		$this->apiExecutorMock->expects($this->once())->method('executeAuth')
			->willReturn(true);

		$this->apiExecutorMock->expects($this->once())->method('getBodyContentsArray')
			->willReturn([
				'access_token' => 'token123',
				'expires_in' => '3600',
				'token_type' => 'Bearer',
			]);

		$this->playerTokenServiceMock->expects($this->once())->method('storeToken')
			->with(
				1,
				'token123',
				'3600',
				'Bearer'
			)
			->willReturn(true);

		$result = $this->service->authenticate('http://example.com', 'user', 'pass', 1);

		static::assertTrue($result);
	}

	#[Group('units')]
	public function testUploadFileFailsWhenTokenIsNotFound(): void
	{
		// without token no request is sent to the player
		$this->apiExecutorMock->expects($this->never())->method($this->anything());
		$this->loggerMock->expects($this->once())->method('error')->with('No valid token found for player 1');
		$this->playerTokenServiceMock->expects($this->once())->method('getToken')
			->with(1)
			->willReturn([]);

		$result = $this->service->uploadFile('http://example.com', 1, '/path/to/file.txt', 'file.txt');

		static::assertFalse($result);
	}

	#[RunInSeparateProcess] #[Group('units')]
	public function testUploadFileFailsWhenFileDoesNotExist(): void
	{
		$this->playerTokenServiceMock->expects($this->once())->method('getToken')
			->with(1)
			->willReturn(['access_token' => 'dummy_token']);

		$fileSize = $this->getFunctionMock('App\Modules\Player\Services', 'filesize');
		$fileSize->expects($this->once())->willReturn(false);


		$this->loggerMock->expects($this->once())->method('error');
		$this->apiExecutorMock->expects($this->never())->method('executeApiRequest');

		$result = $this->service->uploadFile('http://example.com', 1, '/invalid/path/to/file.txt', 'file.txt');

		static::assertFalse($result);
	}

	#[RunInSeparateProcess] #[Group('units')]
	public function testUploadFileFailsWhenApiExecution(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$this->playerTokenServiceMock->expects($this->once())->method('getToken')
			->with(1)
			->willReturn(['access_token' => 'dummy_token']);

		$fileSize = $this->getFunctionMock('App\Modules\Player\Services', 'filesize');
		$fileSize->expects($this->once())->willReturn(1234);

		$fileGet = $this->getFunctionMock('App\Modules\Player\Services', 'file_get_contents');
		$fileGet->expects($this->once())->willReturn('some file content');

		$this->apiExecutorMock->expects($this->once())->method('executeApiRequest')
			->willReturn(false);

		$result = $this->service->uploadFile('http://example.com', 1, '/valid/path/to/file.txt', 'file.txt');

		static::assertFalse($result);
	}

	#[RunInSeparateProcess] #[Group('units')]
	public function testUploadFailsWhenUploadFails(): void
	{
		$this->playerTokenServiceMock->expects($this->once())->method('getToken')
			->with(1)
			->willReturn(['access_token' => 'dummy_token']);

		$fileSize = $this->getFunctionMock('App\Modules\Player\Services', 'filesize');
		$fileSize->expects($this->once())->willReturn(1234);

		$fileGet = $this->getFunctionMock('App\Modules\Player\Services', 'file_get_contents');
		$fileGet->expects($this->once())->willReturn('some file content');

		$this->apiExecutorMock->expects($this->once())->method('executeApiRequest')
			->willReturn(true);

		$this->apiExecutorMock->expects($this->once())->method('getBodyContentsArray')
			->willReturn(['completed' => false]);

		$this->loggerMock->expects($this->once())->method('error')
			->with('Upload file failed', ['response' => ['completed' => false]]);

		$result = $this->service->uploadFile('http://example.com', 1, '/valid/path/to/file.txt', 'file.txt');

		static::assertFalse($result);
	}

	#[RunInSeparateProcess] #[Group('units')]
	public function testUploadFileSucceedsWithValidInputs(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$this->playerTokenServiceMock->expects($this->once())->method('getToken')
			->with(1)
			->willReturn(['access_token' => 'dummy_token']);

		$fileSize = $this->getFunctionMock('App\Modules\Player\Services', 'filesize');
		$fileSize->expects($this->once())->willReturn(1234);

		$fileGet = $this->getFunctionMock('App\Modules\Player\Services', 'file_get_contents');
		$fileGet->expects($this->once())->willReturn('some file content');

		$this->apiExecutorMock->expects($this->once())->method('executeApiRequest')
			->willReturn(true);

		$this->apiExecutorMock->expects($this->once())->method('getBodyContentsArray')
			->willReturn(['completed' => true]);

		$result = $this->service->uploadFile('http://example.com', 1, '/valid/path/to/file.txt', 'file.txt');

		static::assertTrue($result);
	}

	#[Group('units')]
	public function testStartPlaybackOnceReturnsFalseWhenTokenNotFound(): void
	{
		// without token no request is sent to the player
		$this->apiExecutorMock->expects($this->never())->method($this->anything());
		$this->loggerMock->expects($this->once())->method('error')->with('No valid token found for player 1');
		$this->playerTokenServiceMock->expects($this->once())->method('getToken')
			->with(1)
			->willReturn([]);

		$result = $this->service->startPlaybackOnce('http://example.com', 1, '/example/uri');

		static::assertFalse($result);
	}

	#[Group('units')]
	public function testStartPlaybackOnceReturnsFalseWhenApiExecutionFails(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$this->playerTokenServiceMock->expects($this->once())->method('getToken')
			->with(1)
			->willReturn(['access_token' => 'dummy_token']);

		$this->apiExecutorMock->expects($this->once())->method('executeApiRequest')
			->with('POST', 'http://example.com/app/exec', 'dummy_token', ['uri' => '/example/uri'])
			->willReturn(false);

		$result = $this->service->startPlaybackOnce('http://example.com', 1, '/example/uri');

		static::assertFalse($result);
	}

	#[Group('units')]
	public function testStartPlaybackOnceReturnsFalseWhenResponseUriDiffers(): void
	{
		$this->playerTokenServiceMock->expects($this->once())->method('getToken')
			->with(1)
			->willReturn(['access_token' => 'dummy_token']);

		$this->apiExecutorMock->expects($this->once())->method('executeApiRequest')
			->willReturn(true);

		$this->apiExecutorMock->expects($this->once())->method('getBodyContentsArray')
			->willReturn(['uri' => '/different/uri']);

		$this->loggerMock->expects($this->once())->method('error')
			->with('Start playback failed', ['response' => ['uri' => '/different/uri']]);

		$result = $this->service->startPlaybackOnce('http://example.com', 1, '/example/uri');

		static::assertFalse($result);
	}

	#[Group('units')]
	public function testStartPlaybackOnceReturnsTrueWhenSuccessful(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$this->playerTokenServiceMock->expects($this->once())->method('getToken')
			->with(1)
			->willReturn(['access_token' => 'dummy_token']);

		$this->apiExecutorMock->expects($this->once())->method('executeApiRequest')
			->with('POST', 'http://example.com/app/exec', 'dummy_token', ['uri' => '/example/uri'])
			->willReturn(true);

		$this->apiExecutorMock->expects($this->once())->method('getBodyContentsArray')
			->willReturn(['uri' => '/example/uri']);

		$result = $this->service->startPlaybackOnce('http://example.com', 1, '/example/uri');

		static::assertTrue($result);
	}

	#[Group('units')]
	public function testSetDefaultContentUrlReturnsFalseWhenTokenIsNotFound(): void
	{
		// without token no request is sent to the player
		$this->apiExecutorMock->expects($this->never())->method($this->anything());
		$this->loggerMock->expects($this->once())->method('error')->with('No valid token found for player 1');
		$this->playerTokenServiceMock->expects($this->once())->method('getToken')
			->with(1)
			->willReturn([]);

		$result = $this->service->setDefaultContentUrl('http://example.com', 1, '/default/uri');

		static::assertFalse($result);
	}

	#[Group('units')]
	public function testSetDefaultContentUrlReturnsFalseWhenApiExecutionFails(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$this->playerTokenServiceMock->expects($this->once())->method('getToken')
			->with(1)
			->willReturn(['access_token' => 'dummy_token']);

		$this->apiExecutorMock->expects($this->once())->method('executeApiRequest')
			->with('POST', 'http://example.com/app/start', 'dummy_token', ['uri' => '/default/uri'])
			->willReturn(false);

		$result = $this->service->setDefaultContentUrl('http://example.com', 1, '/default/uri');

		static::assertFalse($result);
	}

	#[Group('units')]
	public function testSetDefaultContentUrlReturnsFalseWhenResponseUriDiffers(): void
	{
		$this->playerTokenServiceMock->expects($this->once())->method('getToken')
			->with(1)
			->willReturn(['access_token' => 'dummy_token']);

		$this->apiExecutorMock->expects($this->once())->method('executeApiRequest')
			->with('POST', 'http://example.com/app/start', 'dummy_token', ['uri' => '/default/uri'])
			->willReturn(true);

		$this->apiExecutorMock->expects($this->once())->method('getBodyContentsArray')
			->willReturn(['uri' => '/different/uri']);

		$this->loggerMock->expects($this->once())->method('error')
			->with('Set default content uri failed', ['response' => ['uri' => '/different/uri']]);

		$result = $this->service->setDefaultContentUrl('http://example.com', 1, '/default/uri');

		static::assertFalse($result);
	}

	#[Group('units')]
	public function testSetDefaultContentUrlReturnsTrueWhenSuccessful(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$this->playerTokenServiceMock->expects($this->once())->method('getToken')
			->with(1)
			->willReturn(['access_token' => 'dummy_token']);

		$this->apiExecutorMock->expects($this->once())->method('executeApiRequest')
			->with('POST', 'http://example.com/app/start', 'dummy_token', ['uri' => '/default/uri'])
			->willReturn(true);

		$this->apiExecutorMock->expects($this->once())->method('getBodyContentsArray')
			->willReturn(['uri' => '/default/uri']);

		$result = $this->service->setDefaultContentUrl('http://example.com', 1, '/default/uri');

		static::assertTrue($result);
	}

	#[Group('units')]
	public function testSwitchToDefaultContentUrlFailsWhenTokenIsNotFound(): void
	{
		// without token no request is sent to the player
		$this->apiExecutorMock->expects($this->never())->method($this->anything());
		$this->loggerMock->expects($this->once())->method('error')->with('No valid token found for player 1');
		$this->playerTokenServiceMock->expects($this->once())->method('getToken')
			->with(1)
			->willReturn([]);

		$result = $this->service->switchToDefaultContentUrl('http://example.com', 1);

		static::assertFalse($result);
	}

	#[Group('units')]
	public function testSwitchToDefaultContentUrlFailsWhenApiExecutionFails(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$this->playerTokenServiceMock->expects($this->once())->method('getToken')
			->with(1)
			->willReturn(['access_token' => 'dummy_token']);

		$this->apiExecutorMock->expects($this->once())->method('executeApiRequest')
			->with('POST', 'http://example.com/app/switch', 'dummy_token', ['mode' => 'start'])
			->willReturn(false);

		$result = $this->service->switchToDefaultContentUrl('http://example.com', 1);

		static::assertFalse($result);
	}

	#[Group('units')]
	public function testSwitchToDefaultContentUrlFailsWithInvalidResponse(): void
	{
		$this->playerTokenServiceMock->expects($this->once())->method('getToken')
			->with(1)
			->willReturn(['access_token' => 'dummy_token']);

		$this->apiExecutorMock->expects($this->once())->method('executeApiRequest')
			->willReturn(true);

		$this->apiExecutorMock->expects($this->once())->method('getBodyContentsArray')
			->willReturn(['unexpected_field' => 'value']);

		$this->loggerMock->expects($this->once())->method('error')
			->with('Switch play default content uri failed', ['response' => ['unexpected_field' => 'value']]);

		$result = $this->service->switchToDefaultContentUrl('http://example.com', 1);

		static::assertFalse($result);
	}

	#[Group('units')]
	public function testSwitchToDefaultContentUrlSucceedsWithValidResponse(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$this->playerTokenServiceMock->expects($this->once())->method('getToken')
			->with(1)
			->willReturn(['access_token' => 'dummy_token']);

		$this->apiExecutorMock->expects($this->once())->method('executeApiRequest')
			->with('POST', 'http://example.com/app/switch', 'dummy_token', ['mode' => 'start'])
			->willReturn(true);

		$this->apiExecutorMock->expects($this->once())->method('getBodyContentsArray')
			->willReturn(['uri' => '/default/content']);

		$result = $this->service->switchToDefaultContentUrl('http://example.com', 1);

		static::assertTrue($result);
	}


}
