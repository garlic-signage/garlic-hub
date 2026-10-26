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

namespace Tests\Integration\Modules\Account\Controller;

use App\Framework\Exceptions\UserException;
use App\Framework\Localization\Translate\Translator;
use App\Framework\TemplateEngine\MustacheAdapter;
use App\Framework\TemplateEngine\MustacheEngineFactory;
use App\Framework\TemplateEngine\PageResponse;
use App\Modules\Account\Controller\ShowPasswordController;
use App\Shared\User\TokenPurposes;
use App\Shared\User\UserTokenService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Integration\Support\ContainerTestCase;

/**
 * The pages to change the own password (logged in) and to set a password by a link (initial password, reset):
 * controller, orchestrator, validation, tokens and the database work together.
 * Users of the fixture: 10 regular (registered), 13 unverified, 14 other. All have the password Test1234!
 */
#[Group('auth')]
class ShowPasswordTest extends ContainerTestCase
{
	private const string NEW_PASSWORD = 'New-Pass1234';

	private ShowPasswordController $controller;
	private UserTokenService $tokenService;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('auth/users.sql');
		$this->controller   = $this->container->get(ShowPasswordController::class);
		$this->tokenService = $this->container->get(UserTokenService::class);
	}

	private function createLink(int $UID, TokenPurposes $purpose = TokenPurposes::PASSWORD_RESET): string
	{
		$token = $this->tokenService->generateToken();
		$this->tokenService->insertToken($UID, $token, $purpose);

		return bin2hex($token);
	}


	private function passwordOf(int $UID): string
	{
		return (string) $this->connection->fetchOne('SELECT password FROM user_main WHERE UID = ?', [$UID]);
	}

	private function translate(string $key): string
	{
		return $this->container->get(Translator::class)->translate($key, 'account');
	}

	/**
	 * @return list<string> the messages of a type which are kept for the next page
	 */
	private function flash(string $type): array
	{
		/** @var array<string,list<string>> $flash */
		$flash = $_SESSION['slimFlash'] ?? [];

		return $flash[$type] ?? [];
	}

	/**
	 * @param array<string,mixed> $post
	 */
	private function postForcedPassword(array $post): ResponseInterface
	{
		$request = new ServerRequestFactory()->createServerRequest('POST', '/force-password')->withParsedBody($post);

		return $this->controller->storeForcedPassword($request, new Response());
	}

	/**
	 * @param array<string,mixed> $changes
	 * @return array<string,mixed>
	 */
	private function forcedPost(string $link, array $changes = []): array
	{
		return array_merge(['token' => $link, 'password' => self::NEW_PASSWORD, 'password_confirm' => self::NEW_PASSWORD], $changes);
	}

	private function page(ResponseInterface $response): PageResponse
	{
		static::assertInstanceOf(PageResponse::class, $response);
		static::assertSame(200, $response->getStatusCode());

		return $response;
	}

	private function assertRedirect(ResponseInterface $response, string $location): void
	{
		static::assertSame(302, $response->getStatusCode());
		static::assertSame($location, $response->getHeaderLine('Location'));
	}

	public function testTheFormToChangeTheOwnPasswordIsShown(): void
	{
		$this->actingAs(10);

		$page = $this->page($this->controller->showPasswordForm(new ServerRequestFactory()->createServerRequest('GET', '/account/password'), new Response()));

		static::assertSame('account/edit', $page->getTemplate());
		static::assertSame('/account/password', $page->getTemplateData()['FORM_ACTION']);
		static::assertSame(['password', 'password_confirm'], array_column($page->getTemplateData()['form_element'], 'LANG_ELEMENT_NAME'));
		static::assertCount(1, $page->getTemplateData()['element_hidden'], 'only the CSRF token');
	}

	public function testALoggedInUserChangesThePassword(): void
	{
		$this->actingAs(10);
		$other = $this->passwordOf(14);

		$response = $this->controller->store(
			new ServerRequestFactory()->createServerRequest('POST', '/account/password')->withParsedBody($this->forcedPost('', ['token' => null])),
			new Response()
		);

		$this->assertRedirect($response, '/');
		static::assertTrue(password_verify(self::NEW_PASSWORD, $this->passwordOf(10)));
		static::assertSame($other, $this->passwordOf(14));
		static::assertSame([$this->container->get(Translator::class)->translate('password_changed', 'account')], $this->flash('success'));
	}

	/**
	 * @return array<string,array{array<string,mixed>}>
	 */
	public static function wrongOwnPasswordPosts(): array
	{
		return [
			'passwords differ'    => [['password_confirm' => 'Other-Pass1234']],
			'password too weak'   => [['password' => 'weak', 'password_confirm' => 'weak']],
			'nothing entered'     => [['password' => '', 'password_confirm' => '']],
			'arrays'              => [['password' => ['x'], 'password_confirm' => ['x']]],
		];
	}

	/**
	 * @param array<string,mixed> $changes
	 */
	#[DataProvider('wrongOwnPasswordPosts')]
	public function testAWrongPostKeepsThePasswordAndShowsTheFormAgain(array $changes): void
	{
		$this->actingAs(10);
		$before = $this->passwordOf(10);

		$response = $this->controller->store(
			new ServerRequestFactory()->createServerRequest('POST', '/account/password')->withParsedBody($this->forcedPost('', ['token' => null] + $changes)),
			new Response()
		);

		$page = $this->page($response);
		static::assertSame('/account/password', $page->getTemplateData()['FORM_ACTION']);
		static::assertSame($before, $this->passwordOf(10));
		static::assertNotSame([], $this->container->get(\Slim\Flash\Messages::class)->getMessages()['error'] ?? [], 'The reason is shown.');
		static::assertSame([], $this->flash('success'));
	}

	public function testWithoutALoginThePasswordCanNotBeChanged(): void
	{
		$before = $this->passwordOf(10);

		try
		{
			$this->controller->store(
				new ServerRequestFactory()->createServerRequest('POST', '/account/password')->withParsedBody($this->forcedPost('', ['token' => null])),
				new Response()
			);
			static::fail('Expected a UserException');
		}
		catch (UserException)
		{
			static::assertSame($before, $this->passwordOf(10));
		}
	}

	public function testTheFormOfALinkNamesTheUserAndCarriesTheToken(): void
	{
		$link = $this->createLink(13, TokenPurposes::INITIAL_PASSWORD);

		$page = $this->page($this->controller->showForcedPasswordForm(
			new ServerRequestFactory()->createServerRequest('GET', '/force-password')->withQueryParams(['token' => $link]),
			new Response()
		));

		static::assertSame('/force-password', $page->getTemplateData()['FORM_ACTION']);
		static::assertStringContainsString('unverified', $page->getTemplateData()['LANG_PAGE_HEADER']);
		static::assertStringContainsString('value="' . $link . '"', implode('', array_column($page->getTemplateData()['element_hidden'], 'HIDDEN_HTML_ELEMENT')));
	}

	/**
	 * The name of the user is shown in the title of a page which everybody with the link can open.
	 */
	public function testTheNameOfTheUserIsEscapedInThePage(): void
	{
		$this->connection->executeStatement("UPDATE user_main SET username = ? WHERE UID = 13", ['<b>x</b>"<script>']);
		$link = $this->createLink(13, TokenPurposes::INITIAL_PASSWORD);
		$page = $this->page($this->controller->showForcedPasswordForm(
			new ServerRequestFactory()->createServerRequest('GET', '/force-password')->withQueryParams(['token' => $link]),
			new Response()
		));

		$adapter = new MustacheAdapter(new MustacheEngineFactory()->create(__DIR__ . '/../../../../../templates'));
		$html    = $adapter->render($page->getTemplate(), $page->getTemplateData());

		static::assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
		static::assertStringNotContainsString('<b>x</b>', $html);
		static::assertStringNotContainsString('<script>', $html);
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function unusableLinks(): array
	{
		return [
			'no token'          => [''],
			'not hex'           => ['zz11'],
			'odd length'        => ['abc'],
			'unknown'           => [str_repeat('ab', 32)],
			'sql'               => ["' OR '1'='1"],
		];
	}

	#[DataProvider('unusableLinks')]
	public function testALinkWithoutAUsableTokenLeadsToTheLogin(string $token): void
	{
		$response = $this->controller->showForcedPasswordForm(
			new ServerRequestFactory()->createServerRequest('GET', '/force-password')->withQueryParams($token === '' ? [] : ['token' => $token]),
			new Response()
		);

		$this->assertRedirect($response, '/login');
		static::assertCount(1, $this->flash('error'));
		static::assertSame([$this->translate($token === '' ? 'no_token' : 'token_error')], $this->flash('error'));
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function deadLinks(): array
	{
		return ['used' => ['used'], 'expired' => ['expired'], 'cookie token' => ['autologin'], 'verification mail' => ['verification']];
	}

	#[DataProvider('deadLinks')]
	public function testAUsedExpiredOrForeignTokenIsNoLink(string $kind): void
	{
		$purpose = match ($kind) { 'autologin' => TokenPurposes::AUTOLOGIN, 'verification' => TokenPurposes::EMAIL_VERIFICATION, default => TokenPurposes::PASSWORD_RESET };
		$link    = $this->createLink(10, $purpose);
		if ($kind === 'used')
			$this->tokenService->useToken((string) hex2bin($link));
		if ($kind === 'expired')
			$this->connection->executeStatement("UPDATE user_tokens SET expires_at = '2000-01-01 00:00:00'");
		$before = $this->passwordOf(10);

		$show = $this->controller->showForcedPasswordForm(
			new ServerRequestFactory()->createServerRequest('GET', '/force-password')->withQueryParams(['token' => $link]),
			new Response()
		);
		$store = $this->postForcedPassword($this->forcedPost($link));

		$this->assertRedirect($show, '/login');
		$this->assertRedirect($store, '/login');
		static::assertSame($before, $this->passwordOf(10));
	}

	public function testThePasswordIsSetByTheLinkAndTheUserIsVerified(): void
	{
		$link  = $this->createLink(13, TokenPurposes::INITIAL_PASSWORD);
		$other = $this->passwordOf(14);

		$response = $this->postForcedPassword($this->forcedPost(strtoupper($link)));

		$this->assertRedirect($response, '/login');
		static::assertTrue(password_verify(self::NEW_PASSWORD, $this->passwordOf(13)));
		static::assertSame($other, $this->passwordOf(14));
		static::assertSame(3, (int) $this->connection->fetchOne('SELECT status FROM user_main WHERE UID = 13'));
		static::assertSame([$this->translate('forced_password_changed')], $this->flash('success'));
		static::assertNull($this->tokenService->findByToken((string) hex2bin($link)), 'The link works once.');
	}

	public function testALinkWorksOnlyOnce(): void
	{
		$link = $this->createLink(10);
		$this->postForcedPassword($this->forcedPost($link));
		$_SESSION['slimFlash'] = [];

		$second = $this->postForcedPassword($this->forcedPost($link, ['password' => 'Second-Pass1234', 'password_confirm' => 'Second-Pass1234']));

		$this->assertRedirect($second, '/login');
		static::assertTrue(password_verify(self::NEW_PASSWORD, $this->passwordOf(10)));
	}

	/**
	 * @return array<string,array{array<string,mixed>}>
	 */
	public static function wrongForcedPosts(): array
	{
		return [
			'passwords differ' => [['password_confirm' => 'Other-Pass1234']],
			'too weak'         => [['password' => 'weak', 'password_confirm' => 'weak']],
		];
	}

	/**
	 * @param array<string,mixed> $changes
	 */
	#[DataProvider('wrongForcedPosts')]
	public function testAWrongPostShowsTheFormAgainAndKeepsLinkAndPassword(array $changes): void
	{
		$link   = $this->createLink(13, TokenPurposes::INITIAL_PASSWORD);
		$before = $this->passwordOf(13);

		$page = $this->page($this->postForcedPassword($this->forcedPost($link, $changes)));

		static::assertSame('/force-password', $page->getTemplateData()['FORM_ACTION']);
		static::assertStringContainsString('value="' . $link . '"', implode('', array_column($page->getTemplateData()['element_hidden'], 'HIDDEN_HTML_ELEMENT')));
		static::assertSame($before, $this->passwordOf(13));
		static::assertSame(2, (int) $this->connection->fetchOne('SELECT status FROM user_main WHERE UID = 13'));
		static::assertNotNull($this->tokenService->findByToken((string) hex2bin($link)), 'The link can be used again.');
		static::assertNotSame([], $this->container->get(\Slim\Flash\Messages::class)->getMessages()['error'] ?? []);
	}

	public function testATokenWhichIsNoTextInThePostLeadsToTheLogin(): void
	{
		$response = $this->postForcedPassword($this->forcedPost('x', ['token' => ['abcd']]));

		$this->assertRedirect($response, '/login');
		static::assertSame([$this->translate('no_token')], $this->flash('error'));
	}

	public function testTheLinkChangesOnlyTheUserOfTheToken(): void
	{
		$link = $this->createLink(14);
		$own  = $this->passwordOf(10);

		$this->postForcedPassword($this->forcedPost($link));

		static::assertSame($own, $this->passwordOf(10));
		static::assertTrue(password_verify(self::NEW_PASSWORD, $this->passwordOf(14)));
	}
}
