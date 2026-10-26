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

use App\Framework\Middleware\SecurityHeadersMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

class SecurityHeadersMiddlewareTest extends TestCase
{
	private const array HEADERS = [
		'X-Content-Type-Options'  => 'nosniff',
		'X-Frame-Options'         => 'SAMEORIGIN',
		'Referrer-Policy'         => 'same-origin',
		'Content-Security-Policy' => "frame-ancestors 'self'; base-uri 'self'; object-src 'none'; form-action 'self'",
		'Permissions-Policy'      => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
		'Cross-Origin-Opener-Policy' => 'same-origin',
	];

	#[Group('units')]
	public function testAddsSecurityHeaders(): void
	{
		$response = $this->process(new Response());

		foreach (self::HEADERS as $name => $value)
		{
			static::assertSame([$value], $response->getHeader($name), $name);
		}
	}

	#[Group('units')]
	#[DataProvider('headerProvider')]
	public function testKeepsAHeaderSetByTheHandlerAndAddsTheOthers(string $name): void
	{
		$response = $this->process((new Response())->withHeader($name, 'set-by-the-handler'));

		static::assertSame(['set-by-the-handler'], $response->getHeader($name));
		foreach (self::HEADERS as $other => $value)
		{
			if ($other !== $name)
				static::assertSame([$value], $response->getHeader($other), $other);
		}
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function headerProvider(): array
	{
		return array_map(static fn(string $name): array => [$name], array_combine(array_keys(self::HEADERS), array_keys(self::HEADERS)));
	}

	#[Group('units')]
	#[DataProvider('responseProvider')]
	public function testAddsTheHeadersToEveryKindOfResponseAndKeepsTheRest(int $status, string $body): void
	{
		$handlerResponse = new Response($status)->withHeader('Content-Type', 'text/html');
		$handlerResponse->getBody()->write($body);

		$response = $this->process($handlerResponse);

		static::assertSame($status, $response->getStatusCode());
		static::assertSame($body, (string) $response->getBody());
		static::assertSame('text/html', $response->getHeaderLine('Content-Type'));
		static::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
		static::assertSame(self::HEADERS['Content-Security-Policy'], $response->getHeaderLine('Content-Security-Policy'));
	}

	/**
	 * @return array<string, array{int, string}>
	 */
	public static function responseProvider(): array
	{
		return [
			'ok'        => [200, '<html></html>'],
			'redirect'  => [302, ''],
			'not found' => [404, 'not found'],
			'error'     => [500, 'internal error'],
		];
	}

	#[Group('units')]
	public function testDoesNotCatchTheExceptionOfTheHandler(): void
	{
		$handler = $this->createStub(RequestHandlerInterface::class);
		$handler->method('handle')->willThrowException(new RuntimeException('handler failed'));

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('handler failed');

		new SecurityHeadersMiddleware()->process((new ServerRequestFactory())->createServerRequest('GET', '/'), $handler);
	}

	private function process(ResponseInterface $handlerResponse): ResponseInterface
	{
		$handler = $this->createStub(RequestHandlerInterface::class);
		$handler->method('handle')->willReturn($handlerResponse);
		$request = (new ServerRequestFactory())->createServerRequest('GET', '/');

		return new SecurityHeadersMiddleware()->process($request, $handler);
	}
}
