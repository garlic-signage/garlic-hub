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

namespace Tests\Blackbox\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;
use Tests\Blackbox\Support\Browser;
use Tests\Blackbox\Support\BlackboxTestCase;

/**
 * The security headers and the cookie flags on every kind of answer of the server.
 */
#[Group('blackbox')]
class SecurityHeadersTest extends BlackboxTestCase
{
	private const array HEADERS = [
		'X-Content-Type-Options' => 'nosniff',
		'X-Frame-Options'        => 'SAMEORIGIN',
		'Referrer-Policy'        => 'same-origin',
		'Cross-Origin-Opener-Policy' => 'same-origin',
		'Permissions-Policy'     => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
	];

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
	}

	private function assertSecurityHeaders(ResponseInterface $response, string $what): void
	{
		foreach (self::HEADERS as $name => $value)
			static::assertSame($value, $response->getHeaderLine($name), $what . ': header ' . $name);

		static::assertFalse($response->hasHeader('X-Powered-By'), $what . ': the version of PHP is shown');

		$csp = $response->getHeaderLine('Content-Security-Policy');
		static::assertStringContainsString("frame-ancestors 'self'", $csp, $what);
		static::assertStringContainsString("object-src 'none'", $csp, $what);
		static::assertStringContainsString("base-uri 'self'", $csp, $what);
		static::assertStringContainsString("form-action 'self'", $csp, $what);
	}

	public function testAGuestPageAndItsRedirectsAndErrors(): void
	{
		$browser = $this->browser();

		$this->assertSecurityHeaders($browser->get('/login'), 'login page 200');
		$this->assertSecurityHeaders($browser->get('/player'), 'redirect to the login 302');
		$this->assertSecurityHeaders($browser->get('/async/playlists/items/load/1'), 'json 401');
		$this->assertSecurityHeaders($browser->get('/does-not-exist'), 'not found 404');
		$this->assertSecurityHeaders($browser->get('/set-locales/de_DE'), 'language switch');
	}

	public function testALoggedInUserAndTheJsonEndpoints(): void
	{
		$browser = $this->browser();
		$login   = $browser->login('anna', 'Test1234!');
		$this->assertSecurityHeaders($login, 'login 302');

		$this->assertSecurityHeaders($browser->get('/'), 'start page');
		$this->assertSecurityHeaders($browser->get('/player'), 'player list');
		$this->assertSecurityHeaders($browser->get('/async/mediapool/node/0'), 'json 200');
		$this->assertSecurityHeaders($browser->request('DELETE', '/async/playlists/items', ['form_params' => ['csrf_token' => 'x']]), 'json 400');
		$this->assertSecurityHeaders($browser->get('/users/edit/1'), 'a page without the right');
	}

	public function testTheAnswerForAPlayerHasThemToo(): void
	{
		$response = $this->browser()->request('GET', '/smil-index', ['headers' => ['User-Agent' => 'GAPI/1.0 (UUID:3f0cd56c-d511-486a-a8e1-9d2cefd78b3f; NAME:a) garlic-macOS/v0.6.0.679 (MODEL:Garlic)']]);

		static::assertSame('application/smil+xml', $response->getHeaderLine('Content-Type'));
		$this->assertSecurityHeaders($response, 'player index');
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function cookies(): array
	{
		return ['session' => ['GhSid'], 'remember me' => ['UserLogin']];
	}

	#[DataProvider('cookies')]
	public function testCookiesAreHttpOnlyAndSameSite(string $name): void
	{
		$browser = $this->browser();
		$browser->login('anna', 'Test1234!', true);

		$cookie = $browser->cookie($name);

		static::assertNotNull($cookie, $name);
		static::assertTrue($cookie->getHttpOnly(), $name . ' must be HttpOnly');
		static::assertNotNull($cookie->toArray()['SameSite'] ?? null, $name . ' needs SameSite');
		static::assertSame('/', $cookie->getPath());
	}

	/**
	 * Session fixation: somebody who knows the session id before the login must not know it afterwards.
	 */
	public function testTheSessionIdChangesWithTheLogin(): void
	{
		$browser = $this->browser();
		$browser->get('/login');
		$before = $browser->cookie('GhSid')?->getValue();

		$browser->login('anna', 'Test1234!');
		$after = $browser->cookie('GhSid')?->getValue();

		static::assertNotNull($before);
		static::assertNotNull($after);
		static::assertNotSame($before, $after, 'The session id must be replaced at the login.');
	}

	public function testNoServerDetailsInTheErrorPageOfAnUnknownPath(): void
	{
		$body = (string) $this->browser()->get('/does-not-exist/../../etc/passwd')->getBody();

		static::assertStringNotContainsString('/var/www', $body);
		static::assertStringNotContainsString('Stack trace', $body);
		static::assertStringNotContainsString('vendor/', $body);
	}

	public function testAPageDoesNotRunInsideAForeignFrame(): void
	{
		// the browser obeys these two headers; the test checks that both are there and say the same
		$response = $this->browser()->get('/login');

		static::assertSame('SAMEORIGIN', $response->getHeaderLine('X-Frame-Options'));
		static::assertStringContainsString("frame-ancestors 'self'", $response->getHeaderLine('Content-Security-Policy'));
	}

	public function testUnknownHostnamesAreServedLikeTheOwnOne(): void
	{
		$browser = new Browser($this->deployment()->baseUrl());

		$response = $browser->request('GET', '/login', ['headers' => ['Host' => 'evil.example']]);

		// the login form posts to its own path, a foreign Host header only changes the url the browser sees
		static::assertSame(200, $response->getStatusCode());
		$this->assertSecurityHeaders($response, 'foreign host header');
	}
}
