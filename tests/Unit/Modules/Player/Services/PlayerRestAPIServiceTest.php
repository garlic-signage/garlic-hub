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

use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\SimpleApiExecutor;
use App\Modules\Player\Services\PlayerRestAPIService;
use App\Modules\Player\Services\PlayerTokenService;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PlayerRestAPIServiceTest extends TestCase
{
	use PHPMock;

	private PlayerRestAPIService $service;
	private SimpleApiExecutor&MockObject $apiExecutorMock;
	private PlayerTokenService&MockObject $playerTokenServiceMock;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->apiExecutorMock        = $this->createMock(SimpleApiExecutor::class);
		$this->playerTokenServiceMock = $this->createMock(PlayerTokenService::class);

		$this->service = new PlayerRestAPIService($this->apiExecutorMock, $this->playerTokenServiceMock);
	}

	#[Group('units')]
	public function testAuthenticateSkipsWhenTokenExists(): void
	{
		$this->apiExecutorMock->expects($this->never())->method($this->anything());
		$this->playerTokenServiceMock->expects($this->once())->method('hasValidToken')->with(1)->willReturn(true);
		$this->playerTokenServiceMock->expects($this->never())->method('storeToken');

		$this->service->authenticate('http://example.com', 'user', 'pass', 1);
	}

	#[Group('units')]
	public function testAuthenticateStoresToken(): void
	{
		$this->playerTokenServiceMock->expects($this->once())->method('hasValidToken')->with(1)->willReturn(false);
		$this->apiExecutorMock->expects($this->once())->method('executeAuth')
			->with('http://example.com/oauth2/token', ['grant_type' => 'password', 'username' => 'user', 'password' => 'pass'])
			->willReturn(['access_token' => 'token123', 'expires_in' => '3600', 'token_type' => 'Bearer']);
		$this->playerTokenServiceMock->expects($this->once())->method('storeToken')->with(1, 'token123', '3600', 'Bearer');

		$this->service->authenticate('http://example.com', 'user', 'pass', 1);
	}

	#[Group('units')]
	public function testAuthenticateThrowsWhenResponseIsInvalid(): void
	{
		$this->playerTokenServiceMock->expects($this->once())->method('hasValidToken')->willReturn(false);
		$this->apiExecutorMock->expects($this->once())->method('executeAuth')->willReturn(['invalid_field' => 'value']);
		$this->playerTokenServiceMock->expects($this->never())->method('storeToken');

		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('player_request_failed {"player_id":1,"reason":"Invalid authentication response"}');

		$this->service->authenticate('http://example.com', 'user', 'pass', 1);
	}

	#[Group('units')]
	public function testAuthenticateThrowsNotReachableOnConnectError(): void
	{
		$this->playerTokenServiceMock->expects($this->once())->method('hasValidToken')->willReturn(false);
		$this->apiExecutorMock->expects($this->once())->method('executeAuth')
			->willThrowException(new ConnectException('Connection refused', new Request('POST', 'http://example.com')));
		$this->playerTokenServiceMock->expects($this->never())->method('storeToken');

		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('player_not_reachable');

		$this->service->authenticate('http://example.com', 'user', 'pass', 1);
	}

	#[Group('units')]
	public function testRequestThrowsWhenTokenIsNotFound(): void
	{
		// without token no request is sent to the player
		$this->apiExecutorMock->expects($this->never())->method($this->anything());
		$this->playerTokenServiceMock->expects($this->once())->method('getToken')->with(1)->willReturn([]);

		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('player_not_authenticated {"player_id":1}');

		$this->service->startPlaybackOnce('http://example.com', 1, 'http://example.com/index.smil');
	}

	/**
	 * @return array<string, array{\Exception}>
	 */
	public static function rejectedRequestProvider(): array
	{
		return [
			'http error'     => [new ServerException('Server error', new Request('POST', 'http://example.com'), new Response(500))],
			'not status 200' => [new FrameworkException('API request failed: 204')],
		];
	}

	#[Group('units')]
	#[DataProvider('rejectedRequestProvider')]
	public function testRequestThrowsWhenPlayerRejects(\Exception $exception): void
	{
		$this->playerTokenServiceMock->expects($this->once())->method('getToken')->willReturn(['access_token' => 'dummy_token']);
		$this->apiExecutorMock->expects($this->once())->method('executeApiRequest')->willThrowException($exception);

		try
		{
			$this->service->switchToDefaultContentUrl('http://example.com', 1);
			static::fail('Exception expected.');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertSame('player_request_failed', $e->getTranslationKey());
			static::assertSame($exception, $e->getPrevious());
		}
	}

	#[RunInSeparateProcess] #[Group('units')]
	public function testUploadFileThrowsWhenFileDoesNotExist(): void
	{
		$this->playerTokenServiceMock->expects($this->never())->method('getToken');
		$this->apiExecutorMock->expects($this->never())->method('executeApiRequest');
		$fileSize = $this->getFunctionMock('App\Modules\Player\Services', 'filesize');
		$fileSize->expects($this->once())->willReturn(false);

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Filepath /invalid/path/to/file.txt does not exist.');

		$this->service->uploadFile('http://example.com', 1, '/invalid/path/to/file.txt', 'file.txt');
	}

	#[RunInSeparateProcess] #[Group('units')]
	public function testUploadFileThrowsWhenUploadIsNotCompleted(): void
	{
		$this->prepareUpload(['completed' => false]);

		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('Upload file failed');

		$this->service->uploadFile('http://example.com', 1, '/valid/path/to/file.txt', 'file.txt');
	}

	#[RunInSeparateProcess] #[Group('units')]
	public function testUploadFileSucceeds(): void
	{
		$this->prepareUpload(['completed' => true]);

		$this->service->uploadFile('http://example.com', 1, '/valid/path/to/file.txt', 'file.txt');
	}

	#[Group('units')]
	public function testStartPlaybackOnceThrowsWhenUriDiffers(): void
	{
		$this->expectRequest('/app/exec', ['uri' => 'http://example.com/index.smil'], ['uri' => 'other']);

		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('Start playback failed');

		$this->service->startPlaybackOnce('http://example.com', 1, 'http://example.com/index.smil');
	}

	#[Group('units')]
	public function testStartPlaybackOnceSucceeds(): void
	{
		$this->expectRequest('/app/exec', ['uri' => 'http://example.com/index.smil'], ['uri' => 'http://example.com/index.smil']);

		$this->service->startPlaybackOnce('http://example.com', 1, 'http://example.com/index.smil');
	}

	#[Group('units')]
	public function testSetDefaultContentUrlThrowsWhenUriDiffers(): void
	{
		$this->expectRequest('/app/start', ['uri' => 'http://example.com/index.smil'], []);

		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('Set default content uri failed');

		$this->service->setDefaultContentUrl('http://example.com', 1, 'http://example.com/index.smil');
	}

	#[Group('units')]
	public function testSetDefaultContentUrlSucceeds(): void
	{
		$this->expectRequest('/app/start', ['uri' => 'http://example.com/index.smil'], ['uri' => 'http://example.com/index.smil']);

		$this->service->setDefaultContentUrl('http://example.com', 1, 'http://example.com/index.smil');
	}

	#[Group('units')]
	public function testSwitchToDefaultContentUrlThrowsWithoutUri(): void
	{
		$this->expectRequest('/app/switch', ['mode' => 'start'], ['uri' => '']);

		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('Switch play default content uri failed');

		$this->service->switchToDefaultContentUrl('http://example.com', 1);
	}

	#[Group('units')]
	public function testSwitchToDefaultContentUrlSucceeds(): void
	{
		$this->expectRequest('/app/switch', ['mode' => 'start'], ['uri' => 'http://example.com/index.smil']);

		$this->service->switchToDefaultContentUrl('http://example.com', 1);
	}

	/**
	 * @param array<string,string> $body
	 * @param array<string,mixed> $response
	 */
	private function expectRequest(string $path, array $body, array $response): void
	{
		$this->playerTokenServiceMock->expects($this->once())->method('getToken')->with(1)->willReturn(['access_token' => 'dummy_token']);
		$this->apiExecutorMock->expects($this->once())->method('executeApiRequest')
			->with('POST', 'http://example.com' . $path, 'dummy_token', $body)
			->willReturn($response);
	}

	/**
	 * @param array<string,bool> $response
	 */
	private function prepareUpload(array $response): void
	{
		$this->playerTokenServiceMock->expects($this->once())->method('getToken')->with(1)->willReturn(['access_token' => 'dummy_token']);
		$fileSize = $this->getFunctionMock('App\Modules\Player\Services', 'filesize');
		$fileSize->expects($this->once())->willReturn(1234);
		$fileGet = $this->getFunctionMock('App\Modules\Player\Services', 'file_get_contents');
		$fileGet->expects($this->once())->willReturn('some file content');

		$this->apiExecutorMock->expects($this->once())->method('executeApiRequest')
			->with('POST', 'http://example.com/files/new', 'dummy_token', $this->callback(
				fn(array $body) => $body['downloadPath'] === '/valid/path/to/file.txt' && $body['fileSize'] === 1234
			))
			->willReturn($response);
	}
}
