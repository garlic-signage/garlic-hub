<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Unit\Modules\Auth;

use App\Framework\Http\Session;
use App\Modules\Auth\AuthService;
use App\Modules\Auth\OAuth2Controller;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

class OAuth2ControllerTest extends TestCase
{
	private OAuth2Controller $controller;
	private AuthService&MockObject $authServiceMock;
	private AuthorizationServer&MockObject $authServerMock;
	private ServerRequestInterface&Stub $requestStub;
	private ResponseInterface&MockObject $responseMock;
	private Session&MockObject $sessionMock;

	/** @var array<string,string> */
	private array $validParams = ['response_type' => 'code', 'client_id' => '123', 'redirect_uri' => 'https://example.com', 'state' => '123'];

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->authServiceMock = $this->createMock(AuthService::class);
		$this->authServerMock  = $this->createMock(AuthorizationServer::class);
		$this->requestStub     = $this->createStub(ServerRequestInterface::class);
		$this->responseMock    = $this->createMock(ResponseInterface::class);
		$this->sessionMock     = $this->createMock(Session::class);

		$this->controller = new OAuth2Controller($this->authServiceMock, $this->authServerMock, $this->sessionMock);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testAuthorizeRedirectsToLoginIfUserNotLoggedIn(): void
	{
		$this->expectValidAuthorizationRequest();
		$sessionMock = $this->sessionWithoutUser($this->validParams);
		$sessionMock->expects($this->once())->method('set')->with('oauth_redirect_params', $this->validParams);
		$this->authServiceMock->expects($this->never())->method('getCurrentUser');

		$this->responseMock->expects($this->once())->method('withHeader')->with('Location', '/login')->willReturnSelf();
		$this->responseMock->expects($this->once())->method('withStatus')->with(302)->willReturnSelf();

		static::assertSame($this->responseMock, $this->controller->authorize($this->requestStub, $this->responseMock));
	}

	/**
	 * Invalid parameters of a not logged-in user end in an error response and are never stored.
	 *
	 * @param array<string,string> $params
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	#[DataProvider('provideInvalidRedirectParams')]
	public function testAuthorizeRejectsInvalidRedirectParams(array $params, string $error): void
	{
		$this->expectValidAuthorizationRequest();
		$sessionMock = $this->sessionWithoutUser($params);
		$sessionMock->expects($this->never())->method('set');
		$this->authServiceMock->expects($this->never())->method('getCurrentUser');
		$this->expectJsonError($error);

		$this->controller->authorize($this->requestStub, $this->responseMock);
	}

	/**
	 * @return array<string, array{array<string,string>, string}>
	 */
	public static function provideInvalidRedirectParams(): array
	{
		$valid = ['response_type' => 'code', 'client_id' => '123', 'redirect_uri' => 'https://example.com', 'state' => '123'];

		return [
			'unknown response type' => [['response_type' => 'unknown'] + $valid, 'Invalid response_type'],
			'empty client id'       => [['client_id' => ''] + $valid, 'Invalid client_id'],
			'too long client id'    => [['client_id' => str_repeat('a', 256)] + $valid, 'Invalid client_id'],
			'missing redirect uri'  => [array_diff_key($valid, ['redirect_uri' => '']), 'Invalid redirect_uri'],
			'invalid redirect uri'  => [['redirect_uri' => 'http//example.com'] + $valid, 'Invalid redirect_uri'],
			'empty state'           => [['state' => ''] + $valid, 'Invalid state'],
			'too long state'        => [['state' => str_repeat('a', 256)] + $valid, 'Invalid state'],
		];
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testAuthorizeOAuthServerException(): void
	{
		$this->sessionMock->expects($this->never())->method($this->anything());
		$this->authServerMock->expects($this->once())->method('validateAuthorizationRequest')
			->willThrowException(new OAuthServerException('error dam dam', 123, 'error'));
		$this->authServiceMock->expects($this->never())->method('getCurrentUser');

		$this->responseMock->expects($this->once())->method('withStatus')->with(400)->willReturnSelf();
		$this->responseMock->expects($this->once())->method('withHeader')->willReturnSelf();

		$this->controller->authorize($this->requestStub, $this->responseMock);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testAuthorizeException(): void
	{
		$this->sessionMock->expects($this->never())->method($this->anything());
		$this->authServerMock->expects($this->once())->method('validateAuthorizationRequest')
			->willThrowException(new \Exception('unknown bum bum'));
		$this->authServiceMock->expects($this->never())->method('getCurrentUser');
		$this->expectJsonError('unknown bum bum');

		$this->controller->authorize($this->requestStub, $this->responseMock);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testAuthorizeCompletesAuthorizationRequest(): void
	{
		$authRequestMock = $this->createMock(AuthorizationRequestInterface::class);
		$this->authServerMock->expects($this->once())->method('validateAuthorizationRequest')
			->with($this->requestStub)
			->willReturn($authRequestMock);

		$this->sessionMock->method('exists')->willReturnMap([['user', true]]);
		$this->sessionMock->expects($this->never())->method('set');
		$this->sessionMock->expects($this->once())->method('get')->with('user')->willReturn(['UID' => 159]);

		$this->authServiceMock->expects($this->once())->method('getCurrentUser')->with(159);
		$authRequestMock->expects($this->once())->method('setUser');
		$authRequestMock->expects($this->once())->method('setAuthorizationApproved')->with(true);
		$this->authServerMock->expects($this->once())->method('completeAuthorizationRequest')
			->with($authRequestMock, $this->responseMock)
			->willReturn($this->responseMock);
		$this->responseMock->expects($this->never())->method('withStatus');

		static::assertSame($this->responseMock, $this->controller->authorize($this->requestStub, $this->responseMock));
	}

	#[Group('units')]
	public function testTokenReturnsAccessTokenResponse(): void
	{
		$this->sessionMock->expects($this->never())->method($this->anything());
		$tokenResponse = $this->createStub(ResponseInterface::class);
		$this->authServerMock->expects($this->once())->method('respondToAccessTokenRequest')
			->with($this->requestStub, $this->responseMock)
			->willReturn($tokenResponse);
		$this->authServiceMock->expects($this->never())->method($this->anything());
		$this->responseMock->expects($this->never())->method($this->anything());

		static::assertSame($tokenResponse, $this->controller->token($this->requestStub, $this->responseMock));
	}

	#[Group('units')]
	public function testTokenOAuthServerException(): void
	{
		$this->sessionMock->expects($this->never())->method($this->anything());
		$this->authServerMock->expects($this->once())->method('respondToAccessTokenRequest')
			->willThrowException(new OAuthServerException('bäm', 123, 'error'));
		$this->authServiceMock->expects($this->never())->method($this->anything());

		$this->responseMock->expects($this->once())->method('withStatus')->with(400)->willReturnSelf();
		$this->responseMock->expects($this->once())->method('withHeader')->willReturnSelf();

		$this->controller->token($this->requestStub, $this->responseMock);
	}

	#[Group('units')]
	public function testTokenException(): void
	{
		$this->sessionMock->expects($this->never())->method($this->anything());
		$this->authServerMock->expects($this->once())->method('respondToAccessTokenRequest')
			->willThrowException(new \Exception('unknown double bäm'));
		$this->authServiceMock->expects($this->never())->method($this->anything());
		$this->expectJsonError('unknown double bäm');

		$this->controller->token($this->requestStub, $this->responseMock);
	}

	private function expectValidAuthorizationRequest(): void
	{
		$this->authServerMock->expects($this->once())->method('validateAuthorizationRequest')
			->with($this->requestStub)
			->willReturn($this->createStub(AuthorizationRequestInterface::class));
		$this->authServerMock->expects($this->never())->method('completeAuthorizationRequest');
	}

	/**
	 * @param array<string,string> $queryParams
	 */
	private function sessionWithoutUser(array $queryParams): Session&MockObject
	{
		$this->sessionMock->method('exists')->willReturnMap([['user', false]]);
		$this->requestStub->method('getQueryParams')->willReturn($queryParams);

		return $this->sessionMock;
	}

	private function expectJsonError(string $error): void
	{
		$streamMock = $this->createMock(StreamInterface::class);
		$streamMock->expects($this->once())->method('write')->with((string) json_encode(['error' => $error]));
		$this->responseMock->expects($this->once())->method('getBody')->willReturn($streamMock);
		$this->responseMock->expects($this->once())->method('withStatus')->with(500)->willReturnSelf();
	}
}
