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
use Psr\Http\Message\ServerRequestInterface;
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
		'Permissions-Policy'      => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
		'Cross-Origin-Opener-Policy' => 'same-origin',
	];

	private const string POLICY = "frame-ancestors 'self'; base-uri 'self'; object-src 'none'; form-action 'self'; script-src 'self' 'nonce-%s'";

	#[Group('units')]
	public function testAddsSecurityHeaders(): void
	{
		$response = $this->process(new Response());
		static::assertMatchesRegularExpression('/^' . preg_quote(sprintf(self::POLICY, 'NONCE'), '/') . '$/', str_replace(self::nonceOf($response), 'NONCE', $response->getHeaderLine('Content-Security-Policy')));

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
		static::assertNotSame('', self::nonceOf($response));
	}

	#[Group('units')]
	public function testKeepsAPolicyOfTheHandler(): void
	{
		$response = $this->process((new Response())->withHeader('Content-Security-Policy', "default-src 'none'"));

		static::assertSame(["default-src 'none'"], $response->getHeader('Content-Security-Policy'));
		static::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
	}

	#[Group('units')]
	public function testTheNonceIsRandomPerRequestAndGivenToTheHandler(): void
	{
		$seen    = [];
		$handler = $this->createStub(RequestHandlerInterface::class);
		$handler->method('handle')->willReturnCallback(static function (ServerRequestInterface $request) use (&$seen): ResponseInterface
		{
			$seen[] = $request->getAttribute(SecurityHeadersMiddleware::NONCE_ATTRIBUTE);

			return new Response();
		});
		$request   = (new ServerRequestFactory())->createServerRequest('GET', '/');
		$first     = new SecurityHeadersMiddleware()->process($request, $handler);
		$second    = new SecurityHeadersMiddleware()->process($request, $handler);

		static::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', (string) $seen[0]);
		static::assertSame($seen[0], self::nonceOf($first));
		static::assertSame($seen[1], self::nonceOf($second));
		static::assertNotSame($seen[0], $seen[1]);
	}

	private static function nonceOf(ResponseInterface $response): string
	{
		return preg_match("/'nonce-([0-9a-f]+)'/", $response->getHeaderLine('Content-Security-Policy'), $match) === 1 ? $match[1] : '';
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
		static::assertStringContainsString("script-src 'self' 'nonce-", $response->getHeaderLine('Content-Security-Policy'));
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
