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

namespace Tests\Unit\Modules\Auth;

use App\Framework\Localization\Translate\Translator;
use App\Framework\Security\Crypt;
use App\Framework\Security\CsrfToken;
use App\Framework\TemplateEngine\PageResponse;
use App\Modules\Auth\AuthService;
use App\Modules\Auth\LoginController;
use App\Shared\User\UserEntity;
use Defuse\Crypto\Key;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Flash\Messages;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Support\MemorySession;

/**
 * Session, CSRF token and flash messages are the real ones. The AuthService is a stub which knows one
 * user (anna / secret). The login with the database is tested in tests/Integration/Modules/Auth.
 */
class LoginControllerTest extends TestCase
{
	private MemorySession $session;
	private CsrfToken $csrfToken;
	private AuthService&Stub $authService;
	private LoginController $controller;
	/** @var array<mixed>|\ArrayAccess<string,mixed>|null the session array of the flash messages */
	private array|\ArrayAccess|null $flashStorage = [];

	protected function setUp(): void
	{
		parent::setUp();
		$this->session   = new MemorySession();
		$this->csrfToken = new CsrfToken(new Crypt(Key::createNewRandomKey()), $this->session);

		$this->authService = $this->createStub(AuthService::class);
		$this->authService->method('login')->willReturnCallback(
			static fn(string $username, string $password): ?UserEntity => $username === 'anna' && $password === 'secret'
				? new UserEntity(['UID' => 2, 'username' => 'anna', 'locale' => 'de_DE'], [], [], [], [], [])
				: null
		);
		$this->authService->method('getErrorMessage')->willReturn('Invalid credentials.');

		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(static fn(string $key, string $module): string => $module . '.' . $key);

		$this->controller = new LoginController($this->authService, $this->csrfToken, $this->session, new Messages($this->flashStorage), $translator);
	}

	/**
	 * @param array<string,mixed> $changes replaces or (null) removes entries of a correct post
	 */
	private function login(array $changes = []): ResponseInterface
	{
		$post = ['username' => 'anna', 'password' => 'secret', 'csrf_token' => $this->csrfToken->getToken()];
		foreach ($changes as $key => $value)
		{
			if ($value === null)
				unset($post[$key]);
			else
				$post[$key] = $value;
		}

		return $this->controller->login(new ServerRequestFactory()->createServerRequest('POST', '/login')->withParsedBody($post), new Response());
	}

	/**
	 * @return list<string>
	 */
	private function flashErrors(): array
	{
		/** @var array<string,list<string>> $flash */
		$flash = is_array($this->flashStorage) ? ($this->flashStorage['slimFlash'] ?? []) : [];

		return $flash['error'] ?? [];
	}

	private function assertRedirect(ResponseInterface $response, string $location): void
	{
		static::assertSame(302, $response->getStatusCode());
		static::assertSame($location, $response->getHeaderLine('Location'));
	}

	/**
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testTheLoginPageCarriesTheCsrfTokenOfTheSession(): void
	{
		$response = $this->controller->showLogin(new ServerRequestFactory()->createServerRequest('GET', '/login'), new Response());

		static::assertInstanceOf(PageResponse::class, $response);
		static::assertSame('auth/login', $response->getTemplate());
		$data = $response->getTemplateData();
		static::assertSame($this->session->get('csrf_token'), $data['CSRF_TOKEN']);
		static::assertNotSame('', $data['CSRF_TOKEN']);
		static::assertSame('login.login', $data['LANG_PAGE_HEADER']);
		static::assertSame('main.username / main.email', $data['LANG_USERNAME']);
		static::assertSame('login.password', $data['LANG_PASSWORD']);
		static::assertSame('login.autologin', $data['LANG_AUTOLOGIN']);
	}

	#[Group('units')]
	public function testALoginPutsTheUserIntoTheSessionWithANewSessionId(): void
	{
		$tokenBefore = $this->csrfToken->getToken();

		$response = $this->login();

		$this->assertRedirect($response, '/');
		static::assertSame(['UID' => 2, 'username' => 'anna', 'locale' => 'de_DE'], $this->session->get('user'));
		static::assertSame('de_DE', $this->session->get('locale'));
		static::assertSame(1, $this->session->regenerations);
		static::assertNotSame($tokenBefore, $this->session->get('csrf_token'), 'The token of the login page is used up.');
		static::assertSame([], $this->flashErrors());
	}

	#[Group('units')]
	public function testALoginWithTheRememberMeBoxWorksAsWell(): void
	{
		$this->assertRedirect($this->login(['autologin' => 'on']), '/');
		static::assertSame(2, $this->session->get('user')['UID'] ?? null);
	}

	/**
	 * @return array<string,array{array<string,mixed>}>
	 */
	public static function wrongCredentials(): array
	{
		return [
			'wrong password'     => [['password' => 'wrong']],
			'unknown user'       => [['username' => 'nobody']],
			'password missing'   => [['password' => null]],
			'user missing'       => [['username' => null]],
			'both missing'       => [['username' => null, 'password' => null]],
			'user is an array'   => [['username' => ['anna']]],
			'password is array'  => [['password' => ['secret']]],
			'user is a number'   => [['username' => 5]],
		];
	}

	/**
	 * A credential which is missing or has the wrong shape is a wrong credential, not an error of the page.
	 *
	 * @param array<string,mixed> $changes
	 */
	#[Group('units')]
	#[DataProvider('wrongCredentials')]
	public function testWrongCredentialsLeadBackToTheLoginWithTheMessage(array $changes): void
	{
		$response = $this->login($changes);

		$this->assertRedirect($response, '/login');
		static::assertSame(['Invalid credentials.'], $this->flashErrors());
		static::assertNull($this->session->get('user'));
		static::assertSame(0, $this->session->regenerations);
	}

	#[Group('units')]
	public function testAPostWithoutAnyBodyIsRefused(): void
	{
		$response = $this->controller->login(new ServerRequestFactory()->createServerRequest('POST', '/login'), new Response());

		$this->assertRedirect($response, '/login');
		static::assertNull($this->session->get('user'));
	}

	/**
	 * The page which started the OAuth login is continued after it, with the parameters it had.
	 */
	#[Group('units')]
	public function testALoginContinuesTheOauthRequest(): void
	{
		$this->session->set('oauth_redirect_params', ['client_id' => 'abc', 'state' => 'x y&z=1']);

		$response = $this->login();

		$this->assertRedirect($response, '/api/authorize?client_id=abc&state=x+y%26z%3D1');
		static::assertFalse($this->session->exists('oauth_redirect_params'), 'The request is continued once.');
	}

	#[Group('units')]
	public function testUnreadableOauthParametersAreIgnored(): void
	{
		$this->session->set('oauth_redirect_params', 'broken');

		$this->assertRedirect($this->login(), '/');
	}

	#[Group('units')]
	public function testALogoutEndsTheSessionAndGivesANewToken(): void
	{
		$this->session->set('user', ['UID' => 2, 'username' => 'anna', 'locale' => 'de_DE']);
		$this->session->set('locale', 'de_DE');
		$tokenBefore = $this->csrfToken->getToken();

		$response = $this->controller->logout(new ServerRequestFactory()->createServerRequest('GET', '/logout'), new Response());

		$this->assertRedirect($response, '/login');
		static::assertNull($this->session->get('user'));
		static::assertNull($this->session->get('locale'));
		static::assertSame(1, $this->session->regenerations);
		static::assertNotSame('', $this->session->get('csrf_token'));
		static::assertNotSame($tokenBefore, $this->session->get('csrf_token'));
	}

	#[Group('units')]
	public function testALogoutWithoutALoginWorks(): void
	{
		$response = $this->controller->logout(new ServerRequestFactory()->createServerRequest('GET', '/logout'), new Response());

		$this->assertRedirect($response, '/login');
	}
}
