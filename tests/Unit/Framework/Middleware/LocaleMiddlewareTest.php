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

namespace Tests\Unit\Framework\Middleware;

use App\Framework\Localization\Locales\Locales;
use App\Framework\Middleware\LocaleMiddleware;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Slim\Psr7\Response;

class LocaleMiddlewareTest extends TestCase
{
	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testDeterminesLocaleBeforeHandlingRequest(): void
	{
		$calls       = [];
		$localesMock = $this->createMock(Locales::class);
		$localesMock->expects($this->once())->method('determineCurrentLocale')
			->willReturnCallback(static function () use (&$calls): void { $calls[] = 'locale'; });

		$request     = $this->createStub(ServerRequestInterface::class);
		$response    = $this->createStub(ResponseInterface::class);
		$handlerMock = $this->createMock(RequestHandlerInterface::class);
		$handlerMock->expects($this->once())->method('handle')->with($request)
			->willReturnCallback(static function () use (&$calls, $response): ResponseInterface
			{
				$calls[] = 'handler';
				return $response;
			});

		static::assertSame($response, new LocaleMiddleware($localesMock)->process($request, $handlerMock));
		static::assertSame(['locale', 'handler'], $calls);
	}

	#[Group('units')]
	public function testAFailingLocaleStopsTheRequest(): void
	{
		$localesStub = $this->createStub(Locales::class);
		$localesStub->method('determineCurrentLocale')->willThrowException(new RuntimeException('no locale'));
		$request     = $this->createStub(ServerRequestInterface::class);
		$called      = false;
		$handlerStub = $this->createStub(RequestHandlerInterface::class);
		$handlerStub->method('handle')->willReturnCallback(static function () use (&$called): ResponseInterface
		{
			$called = true;
			return new Response();
		});

		try
		{
			new LocaleMiddleware($localesStub)->process($request, $handlerStub);
		}
		catch (RuntimeException $e)
		{
			static::assertSame('no locale', $e->getMessage());
			static::assertFalse($called, 'The request must not be handled without a locale.');

			return;
		}

		static::fail('Expected a RuntimeException.');
	}
}
