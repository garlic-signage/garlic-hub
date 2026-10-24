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
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * SimpleApiExecutor with a real Guzzle client. Only the transfer is replaced: the test sees the request which
 * would go over the wire, and the client behaves like the one of the application (4xx and 5xx throw by itself).
 */
class SimpleApiExecutorTest extends TestCase
{
	private MockHandler $mock;
	/** @var array<array{request: RequestInterface, response: ResponseInterface|null, error: mixed, options: array<mixed>}> */
	private array $history = [];
	private SimpleApiExecutor $simpleApiExecutor;

	protected function setUp(): void
	{
		parent::setUp();

		$this->history = [];
		$this->mock    = new MockHandler();
		$stack         = HandlerStack::create($this->mock);
		// @phpstan-ignore-next-line // Guzzle takes the history by reference and types it as array|ArrayAccess
		$stack->push(Middleware::history($this->history));

		$this->simpleApiExecutor = new SimpleApiExecutor(new Client(['handler' => $stack]));
	}

	private function sentRequest(): RequestInterface
	{
		static::assertCount(1, $this->history, 'Exactly one request is expected.');

		return array_values($this->history)[0]['request'];
	}

	/**
	 * @throws FrameworkException
	 * @throws GuzzleException
	 */
	#[Group('units')]
	public function testExecuteApiRequestReturnsDecodedBody(): void
	{
		$this->mock->append(new Response(200, [], '{"success":true}'));

		$result = $this->simpleApiExecutor->executeApiRequest('GET', 'https://example.com/api', 'test_token', ['key' => 'value']);

		static::assertSame(['success' => true], $result);
	}

	/**
	 * @throws FrameworkException
	 * @throws GuzzleException
	 */
	#[Group('units')]
	public function testExecuteApiRequestSendsMethodTokenAndJsonBody(): void
	{
		$this->mock->append(new Response(200, [], '{}'));

		$this->simpleApiExecutor->executeApiRequest('POST', 'https://example.com/api/players', 'test_token', ['key' => 'value', 'count' => 3]);

		$request = $this->sentRequest();
		static::assertSame('POST', $request->getMethod());
		static::assertSame('example.com', $request->getUri()->getHost());
		static::assertSame('/api/players', $request->getUri()->getPath());
		static::assertSame('access_token=test_token', $request->getUri()->getQuery());
		static::assertSame('application/json', $request->getHeaderLine('Content-Type'));
		static::assertSame('{"key":"value","count":3}', (string) $request->getBody());
	}

	/**
	 * @throws FrameworkException
	 * @throws GuzzleException
	 */
	#[Group('units')]
	#[DataProvider('tokenProvider')]
	public function testTheTokenIsEncodedInTheQuery(string $token, string $expectedQuery): void
	{
		$this->mock->append(new Response(200, [], '{}'));

		$this->simpleApiExecutor->executeApiRequest('GET', 'https://example.com/api', $token);

		static::assertSame($expectedQuery, $this->sentRequest()->getUri()->getQuery());
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function tokenProvider(): array
	{
		return [
			'plain'            => ['abc123', 'access_token=abc123'],
			'base64 signs'     => ['a+b/c=', 'access_token=a%2Bb%2Fc%3D'],
			'ampersand'        => ['a&admin=1', 'access_token=a%26admin%3D1'],
			'hash and space'   => ['a#b c', 'access_token=a%23b%20c'],
		];
	}

	/**
	 * @throws FrameworkException
	 * @throws GuzzleException
	 */
	#[Group('units')]
	public function testAnEndpointWithAQueryGetsTheTokenAsAnotherParameter(): void
	{
		$this->mock->append(new Response(200, [], '{}'));

		$this->simpleApiExecutor->executeApiRequest('GET', 'https://example.com/api?playlist=5', 'test_token');

		static::assertSame('playlist=5&access_token=test_token', $this->sentRequest()->getUri()->getQuery());
	}

	/**
	 * Without options the body is an empty JSON array, not an object.
	 *
	 * @throws FrameworkException
	 * @throws GuzzleException
	 */
	#[Group('units')]
	public function testWithoutOptionsAnEmptyJsonArrayIsSent(): void
	{
		$this->mock->append(new Response(200, [], '{}'));

		$this->simpleApiExecutor->executeApiRequest('GET', 'https://example.com/api', 'test_token');

		static::assertSame('[]', (string) $this->sentRequest()->getBody());
	}

	/**
	 * @throws FrameworkException
	 * @throws GuzzleException
	 */
	#[Group('units')]
	#[DataProvider('unusableBodyProvider')]
	public function testAnAnswerWhichIsNoJsonObjectOrListReturnsAnEmptyArray(string $body): void
	{
		$this->mock->append(new Response(200, [], $body));

		static::assertSame([], $this->simpleApiExecutor->executeApiRequest('GET', 'https://example.com/api', 'test_token'));
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function unusableBodyProvider(): array
	{
		return ['no json' => ['no json'], 'empty' => [''], 'string' => ['"text"'], 'number' => ['5'], 'null' => ['null'], 'cut off' => ['{"a":']];
	}

	/**
	 * @throws FrameworkException
	 * @throws GuzzleException
	 */
	#[Group('units')]
	public function testAListIsReturnedAsItIs(): void
	{
		$this->mock->append(new Response(200, [], '[{"id":1},{"id":2}]'));

		static::assertSame([['id' => 1], ['id' => 2]], $this->simpleApiExecutor->executeApiRequest('GET', 'https://example.com/api', 'test_token'));
	}

	/**
	 * @throws GuzzleException
	 */
	#[Group('units')]
	public function testExecuteApiRequestThrowsOnASuccessStatusWhichIsNot200(): void
	{
		$this->mock->append(new Response(204, [], 'No Content'));

		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('API request failed: 204 No Content');

		$this->simpleApiExecutor->executeApiRequest('POST', 'https://example.com/api', 'test_token');
	}

	/**
	 * The client throws for 4xx and 5xx by itself, the caller gets the Guzzle exception with the response.
	 *
	 * @param class-string<Throwable> $exceptionClass
	 * @throws FrameworkException
	 */
	#[Group('units')]
	#[DataProvider('errorStatusProvider')]
	public function testExecuteApiRequestPassesTheErrorsOfTheClientOn(int $status, string $exceptionClass): void
	{
		$this->mock->append(new Response($status, [], 'error body'));

		try
		{
			$this->simpleApiExecutor->executeApiRequest('GET', 'https://example.com/api', 'test_token');
		}
		catch (BadResponseException $e)
		{
			static::assertInstanceOf($exceptionClass, $e);
			static::assertSame($status, $e->getResponse()->getStatusCode());

			return;
		}

		static::fail('Expected a BadResponseException.');
	}

	/**
	 * @return array<string, array{int, class-string<Throwable>}>
	 */
	public static function errorStatusProvider(): array
	{
		return ['not found' => [404, ClientException::class], 'unauthorized' => [401, ClientException::class], 'server error' => [500, ServerException::class]];
	}

	/**
	 * @throws FrameworkException
	 * @throws GuzzleException
	 */
	#[Group('units')]
	public function testExecuteApiRequestPassesTransferErrorsOn(): void
	{
		$exception = new ConnectException('Connection refused', new Request('POST', 'https://example.com/api'));
		$this->mock->append($exception);

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
		$this->mock->append(new Response(200, [], '{"access_token":"abc"}'));

		$result = $this->simpleApiExecutor->executeAuth('https://example.com/oauth2/token', ['grant_type' => 'password']);

		static::assertSame(['access_token' => 'abc'], $result);
	}

	/**
	 * @throws FrameworkException
	 * @throws GuzzleException
	 */
	#[Group('units')]
	public function testExecuteAuthPostsTheJsonBodyWithoutAToken(): void
	{
		$this->mock->append(new Response(200, [], '{}'));

		$this->simpleApiExecutor->executeAuth('https://example.com/oauth2/token', ['grant_type' => 'password', 'username' => 'anna']);

		$request = $this->sentRequest();
		static::assertSame('POST', $request->getMethod());
		static::assertSame('/oauth2/token', $request->getUri()->getPath());
		static::assertSame('', $request->getUri()->getQuery());
		static::assertSame('application/json', $request->getHeaderLine('Content-Type'));
		static::assertSame('{"grant_type":"password","username":"anna"}', (string) $request->getBody());
	}

	/**
	 * @throws GuzzleException
	 */
	#[Group('units')]
	public function testExecuteAuthThrowsOnASuccessStatusWhichIsNot200(): void
	{
		$this->mock->append(new Response(202, [], 'Accepted'));

		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('Auth request failed: 202 Accepted');

		$this->simpleApiExecutor->executeAuth('https://example.com/oauth2/token');
	}

	/**
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testExecuteAuthPassesTheErrorsOfTheClientOn(): void
	{
		$this->mock->append(new Response(401, [], 'denied'));

		$this->expectException(ClientException::class);

		$this->simpleApiExecutor->executeAuth('https://example.com/oauth2/token', ['grant_type' => 'password']);
	}
}
