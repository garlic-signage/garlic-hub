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

namespace Tests\Unit\Framework\Middleware;

use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Middleware\CsrfMiddleware;
use App\Framework\Security\Crypt;
use App\Framework\Security\CsrfToken;
use Defuse\Crypto\Key;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Flash\Messages;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Support\MemorySession;

/**
 * Which requests need the CSRF token and what the visitor gets without it. Session, token and flash messages
 * are the real ones, the session lives in an array.
 */
class CsrfMiddlewareTest extends TestCase
{
	private const string TOKEN = 'the-token-of-the-session';

	private MemorySession $session;
	/** @var array<mixed>|\ArrayAccess<mixed,mixed>|null the storage of the flash messages, Messages writes into it by reference */
	private array|\ArrayAccess|null $flashStorage = [];
	private CsrfMiddleware $middleware;
	private RequestHandlerInterface $handler;

	protected function setUp(): void
	{
		parent::setUp();
		$this->session = new MemorySession();
		$this->session->set(CsrfToken::CSRF_TOKEN_SESSION_KEY, self::TOKEN);

		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnMap([['csrf_token_mismatch', 'security', [], 'CSRF token validation error.']]);

		$this->middleware = new CsrfMiddleware(
			new CsrfToken(new Crypt(Key::createNewRandomKey()), $this->session),
			new Messages($this->flashStorage),
			$translator,
			new JsonResponseHandler()
		);
		$this->handler = new class implements RequestHandlerInterface {
			public function handle(ServerRequestInterface $request): ResponseInterface
			{
				return new Response(200)->withHeader('X-Handled', 'yes');
			}
		};
	}

	/**
	 * @param array<string,mixed>|null $body
	 * @param array<string,string> $headers
	 */
	private function process(string $method, string $path, ?array $body = null, array $headers = []): ResponseInterface
	{
		$request = new ServerRequestFactory()->createServerRequest($method, $path)->withParsedBody($body);
		foreach ($headers as $name => $value)
			$request = $request->withHeader($name, $value);

		return $this->middleware->process($request, $this->handler);
	}

	private function assertHandled(ResponseInterface $response): void
	{
		static::assertSame(200, $response->getStatusCode());
		static::assertSame('yes', $response->getHeaderLine('X-Handled'));
	}

	private function assertJsonRefusal(ResponseInterface $response): void
	{
		static::assertSame(403, $response->getStatusCode());
		static::assertFalse($response->hasHeader('X-Handled'));
		static::assertSame('application/json', $response->getHeaderLine('Content-Type'));
		static::assertSame(['success' => false, 'error_message' => 'CSRF token validation error.'], json_decode((string) $response->getBody(), true));
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function changingMethods(): array
	{
		return ['post' => ['POST'], 'put' => ['PUT'], 'patch' => ['PATCH'], 'delete' => ['DELETE']];
	}

	#[Group('units')]
	#[DataProvider('changingMethods')]
	public function testARequestWithTheTokenOfTheSessionPasses(string $method): void
	{
		$this->assertHandled($this->process($method, '/async/playlists', ['csrf_token' => self::TOKEN]));
	}

	/**
	 * @return array<string,array{string,array<string,mixed>|null}>
	 */
	public static function bodiesWithoutTheToken(): array
	{
		return [
			'post without token'   => ['POST', ['name' => 'x']],
			'put with a wrong one' => ['PUT', ['csrf_token' => 'forged']],
			'patch with an empty'  => ['PATCH', ['csrf_token' => '']],
			'delete with a list'   => ['DELETE', ['csrf_token' => [self::TOKEN]]],
			'post without body'    => ['POST', null],
		];
	}

	/**
	 * @param array<string,mixed>|null $body
	 */
	#[Group('units')]
	#[DataProvider('bodiesWithoutTheToken')]
	public function testAnAsyncRequestWithoutTheTokenGetsAJsonError(string $method, ?array $body): void
	{
		$this->assertJsonRefusal($this->process($method, '/async/playlists', $body));
	}

	#[Group('units')]
	public function testWithoutASessionNoTokenIsValid(): void
	{
		$this->session->clear();

		$this->assertJsonRefusal($this->process('POST', '/async/playlists', ['csrf_token' => '']));
	}

	#[Group('units')]
	public function testACallerWhichExpectsJsonGetsAJsonError(): void
	{
		$this->assertJsonRefusal($this->process('POST', '/users/edit', [], ['Accept' => 'application/json']));
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function safeMethods(): array
	{
		return ['get' => ['GET'], 'head' => ['HEAD'], 'options' => ['OPTIONS']];
	}

	#[Group('units')]
	#[DataProvider('safeMethods')]
	public function testARequestWhichChangesNothingNeedsNoToken(string $method): void
	{
		$this->assertHandled($this->process($method, '/async/playlists'));
	}

	/**
	 * OAuth clients call /api without a session.
	 */
	#[Group('units')]
	public function testTheApiIsLeftOut(): void
	{
		$this->assertHandled($this->process('POST', '/api/token', ['grant_type' => 'x']));
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function protectedPaths(): array
	{
		return [
			'api as prefix'   => ['/apis/token'],
			'api upper case'  => ['/API/token'],
			'api in the path' => ['/async/api'],
		];
	}

	#[Group('units')]
	#[DataProvider('protectedPaths')]
	public function testOnlyTheApiSectionIsLeftOut(string $path): void
	{
		static::assertSame(403, $this->process('POST', $path, [], ['Accept' => 'application/json'])->getStatusCode());
	}

	#[Group('units')]
	public function testAFormWithoutTheTokenGoesBackWithAMessage(): void
	{
		$response = $this->process('POST', '/users/edit', ['username' => 'x'], ['Referer' => 'https://hub.example.com/users/edit/3?tab=1']);

		static::assertSame(303, $response->getStatusCode());
		static::assertSame('/users/edit/3?tab=1', $response->getHeaderLine('Location'));
		static::assertFalse($response->hasHeader('X-Handled'));
		static::assertSame(['error' => ['CSRF token validation error.']], $this->flashStorage['slimFlash'] ?? null);
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function referersWhichAreNoWayBack(): array
	{
		return [
			'none'           => [''],
			'no path'        => ['https://hub.example.com'],
			'two slashes'    => ['https://hub.example.com//evil.example.com/x'],
			'a backslash'    => ['https://hub.example.com/\\evil.example.com'],
			'not an address' => ['::::'],
		];
	}

	/**
	 * Only the path of the referer is used, and only when a browser can not take it for another site.
	 */
	#[Group('units')]
	#[DataProvider('referersWhichAreNoWayBack')]
	public function testWithoutAUsableRefererTheFormGoesBackToItsAddress(string $referer): void
	{
		$response = $this->process('POST', '/account/password', [], ['Referer' => $referer]);

		static::assertSame(303, $response->getStatusCode());
		static::assertSame('/account/password', $response->getHeaderLine('Location'));
	}

	#[Group('units')]
	public function testTheRefererOfAnotherSiteLeadsOnlyToThePathOnThisSite(): void
	{
		$response = $this->process('POST', '/account/password', [], ['Referer' => 'https://evil.example.com/phish']);

		static::assertSame('/phish', $response->getHeaderLine('Location'));
	}
}
