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

use App\Framework\Exceptions\FrameworkException;
use App\Framework\SimpleApiExecutor;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SimpleApiExecutorTest extends TestCase
{
	private Client&MockObject $httpClientMock;
	private SimpleApiExecutor $simpleApiExecutor;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->httpClientMock    = $this->createMock(Client::class);
		$this->simpleApiExecutor = new SimpleApiExecutor($this->httpClientMock);
	}

	/**
	 * @throws FrameworkException
	 * @throws GuzzleException
	 */
	#[Group('units')]
	public function testExecuteApiRequestReturnsDecodedBody(): void
	{
		$this->httpClientMock->expects($this->once())->method('request')
			->with('GET', 'https://example.com/api?access_token=test_token', [RequestOptions::JSON => ['key' => 'value']])
			->willReturn(new Response(200, [], '{"success":true}'));

		$result = $this->simpleApiExecutor->executeApiRequest('GET', 'https://example.com/api', 'test_token', ['key' => 'value']);

		static::assertSame(['success' => true], $result);
	}

	/**
	 * @throws FrameworkException
	 * @throws GuzzleException
	 */
	#[Group('units')]
	public function testExecuteApiRequestWithoutJsonReturnsEmptyArray(): void
	{
		$this->httpClientMock->expects($this->once())->method('request')
			->willReturn(new Response(200, [], 'no json'));

		static::assertSame([], $this->simpleApiExecutor->executeApiRequest('GET', 'https://example.com/api', 'test_token'));
	}

	/**
	 * @throws FrameworkException
	 * @throws GuzzleException
	 */
	#[Group('units')]
	public function testExecuteApiRequestThrowsOnStatusNot200(): void
	{
		$this->httpClientMock->expects($this->once())->method('request')
			->willReturn(new Response(204, [], 'No Content'));

		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('API request failed: 204 No Content');

		$this->simpleApiExecutor->executeApiRequest('POST', 'https://example.com/api', 'test_token');
	}

	/**
	 * @throws FrameworkException
	 * @throws GuzzleException
	 */
	#[Group('units')]
	public function testExecuteApiRequestPassesTransferErrorsOn(): void
	{
		$exception = new ConnectException('Connection refused', new Request('POST', 'https://example.com/api'));
		$this->httpClientMock->expects($this->once())->method('request')->willThrowException($exception);

		$this->expectExceptionObject($exception);

		$this->simpleApiExecutor->executeApiRequest('POST', 'https://example.com/api', 'test_token');
	}

	/**
	 * @throws FrameworkException
	 * @throws GuzzleException
	 */
	#[Group('units')]
	public function testExecuteAuthReturnsDecodedBody(): void
	{
		$this->httpClientMock->expects($this->once())->method('post')
			->with('https://example.com/oauth2/token', [RequestOptions::JSON => ['grant_type' => 'password']])
			->willReturn(new Response(200, [], '{"access_token":"abc"}'));

		$result = $this->simpleApiExecutor->executeAuth('https://example.com/oauth2/token', ['grant_type' => 'password']);

		static::assertSame(['access_token' => 'abc'], $result);
	}

	/**
	 * @throws FrameworkException
	 * @throws GuzzleException
	 */
	#[Group('units')]
	public function testExecuteAuthThrowsOnStatusNot200(): void
	{
		$this->httpClientMock->expects($this->once())->method('post')
			->willReturn(new Response(202, [], 'Accepted'));

		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('Auth request failed: 202 Accepted');

		$this->simpleApiExecutor->executeAuth('https://example.com/oauth2/token');
	}
}
