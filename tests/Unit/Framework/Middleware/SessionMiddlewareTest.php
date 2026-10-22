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

namespace Tests\Unit\Framework\Middleware;

use App\Framework\Http\Cookie;
use App\Framework\Http\Session;
use App\Framework\Middleware\SessionMiddleware;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Flash\Messages;

class SessionMiddlewareTest extends TestCase
{
	private Session&Stub $sessionStub;
	private Cookie&Stub $cookieStub;
	private Messages&Stub $flashStub;
	private RequestHandlerInterface&MockObject $handlerMock;
	private ServerRequestInterface&Stub $requestStub;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->sessionStub = $this->createStub(Session::class);
		$this->cookieStub = $this->createStub(Cookie::class);
		$this->flashStub = $this->createStub(Messages::class);
		$this->handlerMock = $this->createMock(RequestHandlerInterface::class);
		$this->requestStub = $this->createStub(ServerRequestInterface::class);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testProcessAddsAttributesToRequest(): void
	{
		$middleware = new SessionMiddleware($this->sessionStub, $this->flashStub, $this->cookieStub);

		// Simulate the `withAttribute` method for the request mock
		$this->requestStub
			->method('withAttribute')
			->willReturnSelf();

		// Expect the handler to handle the request
		$responseStub = $this->createStub(ResponseInterface::class);
		$this->handlerMock
			->expects($this->once())
			->method('handle')
			->with($this->requestStub)
			->willReturn($responseStub);

		// Execute the middleware
		$result = $middleware->process($this->requestStub, $this->handlerMock);

		// Assert the returned response is as expected
		static::assertSame($responseStub, $result);
	}
}
