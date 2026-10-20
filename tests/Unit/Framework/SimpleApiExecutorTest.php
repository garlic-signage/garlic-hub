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


namespace Tests\Unit\Framework;

use App\Framework\SimpleApiExecutor;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

class SimpleApiExecutorTest extends TestCase
{
	private Client&MockObject $httpClientMock;
	private LoggerInterface&MockObject $loggerMock;
	private SimpleApiExecutor $simpleApiExecutor;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->httpClientMock = $this->createMock(Client::class);
		$this->loggerMock = $this->createMock(LoggerInterface::class);
		$this->simpleApiExecutor = new SimpleApiExecutor($this->httpClientMock, $this->loggerMock);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testExecuteApiRequestSuccess(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$endpoint = 'https://example.com/api';
		$token = 'test_token';
		$method = 'GET';
		$options = ['key' => 'value'];
		$responseBody = '{"success":true}';

		$responseMock = $this->createMock(ResponseInterface::class);
		$responseMock->expects($this->once())->method('getStatusCode')
			->willReturn(200);

		$streamInterfaceMock = $this->createMock(StreamInterface::class);
		$responseMock->expects($this->once())->method('getBody')
			->willReturn($streamInterfaceMock);
		$streamInterfaceMock->expects($this->once())->method('getContents')
			->willReturn($responseBody);

		$this->httpClientMock->expects($this->once())
			->method('request')
			->with($method, "$endpoint?access_token=$token", [RequestOptions::JSON => $options])
			->willReturn($responseMock);

		$result = $this->simpleApiExecutor->executeApiRequest($method, $endpoint, $token, $options);

		static::assertTrue($result);
		static::assertSame($responseBody, $this->simpleApiExecutor->getBodyContents());
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testExecuteApiRequestFailure(): void
	{
		$endpoint = 'https://example.com/api';
		$token = 'test_token';
		$method = 'POST';
		$options = ['key' => 'value'];

		$responseMock = $this->createMock(ResponseInterface::class);
		$responseMock->expects($this->once())->method('getStatusCode')
			->willReturn(500);

		$streamInterfaceMock = $this->createMock(StreamInterface::class);
		$responseMock->expects($this->once())->method('getBody')
			->willReturn($streamInterfaceMock);
		$streamInterfaceMock->expects($this->once())->method('getContents')
			->willReturn('Internal Server Error');

		$this->httpClientMock->expects($this->once())
			->method('request')
			->with($method, "$endpoint?access_token=$token", [RequestOptions::JSON => $options])
			->willReturn($responseMock);

		$this->loggerMock->expects($this->once())->method('error')
			->with('API request failed: 500');

		$result = $this->simpleApiExecutor->executeApiRequest($method, $endpoint, $token, $options);

		static::assertFalse($result);
	}

	#[Group('units')]
	public function testExecuteApiRequestException(): void
	{
		$endpoint = 'https://example.com/api';
		$token = 'test_token';
		$method = 'DELETE';
		$options = ['key' => 'value'];

		$this->httpClientMock->expects($this->once())->method('request')
			->with($method, "$endpoint?access_token=$token", [RequestOptions::JSON => $options])
			->willThrowException(new RuntimeException('Connection failed'));

		$this->loggerMock->expects($this->once())->method('error')
			->with('API request error: Connection failed');

		$result = $this->simpleApiExecutor->executeApiRequest($method, $endpoint, $token, $options);

		static::assertFalse($result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testExecuteAuthSuccess(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$endpoint = 'https://example.com/auth';
		$options = ['username' => 'test', 'password' => 'secret'];
		$responseBody = '{"authenticated":true}';

		$responseMock = $this->createMock(ResponseInterface::class);
		$responseMock->expects($this->once())->method('getStatusCode')
			->willReturn(200);

		$streamInterfaceMock = $this->createMock(StreamInterface::class);
		$responseMock->expects($this->once())->method('getBody')
			->willReturn($streamInterfaceMock);
		$streamInterfaceMock->expects($this->once())->method('getContents')
			->willReturn($responseBody);

		$this->httpClientMock->expects($this->once())
			->method('post')
			->with($endpoint, [RequestOptions::JSON => $options])
			->willReturn($responseMock);

		$result = $this->simpleApiExecutor->executeAuth($endpoint, $options);

		static::assertTrue($result);
		static::assertSame($responseBody, $this->simpleApiExecutor->getBodyContents());
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testExecuteAuthFailure(): void
	{
		$endpoint = 'https://example.com/auth';
		$options = ['username' => 'test', 'password' => 'wrong_secret'];

		$responseMock = $this->createMock(ResponseInterface::class);
		$responseMock->expects($this->once())->method('getStatusCode')
			->willReturn(401);

		$streamInterfaceMock = $this->createMock(StreamInterface::class);
		$responseMock->expects($this->once())->method('getBody')
			->willReturn($streamInterfaceMock);
		$streamInterfaceMock->expects($this->once())->method('getContents')
			->willReturn('Unauthorized');

		$this->httpClientMock->expects($this->once())
			->method('post')
			->with($endpoint, [RequestOptions::JSON => $options])
			->willReturn($responseMock);

		$this->loggerMock->expects($this->once())->method('error')
			->with('Auth request failed: 401');

		$result = $this->simpleApiExecutor->executeAuth($endpoint, $options);

		static::assertFalse($result);
	}

	#[Group('units')]
	public function testExecuteAuthException(): void
	{
		$endpoint = 'https://example.com/auth';
		$options = ['username' => 'test', 'password' => 'secret'];

		$this->httpClientMock->expects($this->once())
			->method('post')
			->with($endpoint, [RequestOptions::JSON => $options])
			->willThrowException(new RuntimeException('Connection failed'));

		$this->loggerMock->expects($this->once())->method('error')
			->with('API request error: Connection failed');

		$result = $this->simpleApiExecutor->executeAuth($endpoint, $options);

		static::assertFalse($result);
	}
}
