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

namespace Tests\Unit\Modules\Account\Controller;

use App\Framework\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Localization\Locales\Locales;
use App\Framework\Localization\Locales\SessionLocaleExtractor;
use App\Modules\Account\Controller\EditLocalesController;
use App\Modules\Account\Services\AccountService;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Support\MemorySession;
use Tests\Support\RecordingConfigLoader;

/**
 * The write into the database is tested in tests/Integration/Modules/Account/Controller/EditLocalesTest.
 */
class EditLocalesControllerTest extends TestCase
{
	private MemorySession $session;
	private Locales $locales;
	private EditLocalesController $controller;

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->session = new MemorySession();
		$config        = new Config(new RecordingConfigLoader([Locales::CONFIG_MODULE_NAME => [
			'general' => ['default_locale' => 'en_US'],
			'en_US'   => ['language_code' => 'en'],
			'de_DE'   => ['language_code' => 'de'],
		]]));
		$this->locales    = new Locales($config, new SessionLocaleExtractor($this->session, 'en_US'));
		$this->controller = new EditLocalesController($this->createStub(AccountService::class), $this->session, $this->locales);
	}

	/**
	 * @throws Exception
	 */
	private function setLocale(string $locale, string $referer = ''): Response
	{
		$request = new ServerRequestFactory()->createServerRequest('GET', '/set-locales/' . $locale)->withHeader('Host', 'garlic-hub.test');
		if ($referer !== '')
			$request = $request->withHeader('Referer', $referer);

		$response = $this->controller->setLocales($request, new Response(), ['locale' => $locale]);
		static::assertInstanceOf(Response::class, $response);

		return $response;
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testAValidLocaleIsStoredAndBecomesTheCurrentOne(): void
	{
		$this->setLocale('de_DE');

		static::assertSame('de_DE', $this->session->get('locale'));
		static::assertSame('de_DE', $this->locales->getCurrentLocale());
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testTheLocaleOfALoggedInUserIsChangedInTheSessionUser(): void
	{
		$this->session->set('user', ['UID' => 1, 'username' => 'regular', 'locale' => 'en_US']);

		$this->setLocale('de_DE');

		static::assertSame(['UID' => 1, 'username' => 'regular', 'locale' => 'de_DE'], $this->session->get('user'));
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function notAllowedLocales(): array
	{
		return ['script' => ['<script>'], 'wrong case' => ['de_de'], 'not configured' => ['fr_FR'], 'section name' => ['general'], 'empty' => ['']];
	}

	/**
	 * Only the locales of the whitelist get into the session, from there they would be written to the database.
	 *
	 * @throws Exception
	 */
	#[Group('units')]
	#[DataProvider('notAllowedLocales')]
	public function testALocaleOutsideTheWhitelistChangesNothing(string $locale): void
	{
		$this->session->set('user', ['UID' => 1, 'locale' => 'en_US']);

		$this->setLocale($locale);

		static::assertNull($this->session->get('locale'));
		static::assertSame(['UID' => 1, 'locale' => 'en_US'], $this->session->get('user'));
		static::assertSame('en_US', $this->locales->getCurrentLocale());
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testAnUnreadableSessionUserDoesNotStopTheChange(): void
	{
		$this->session->set('user', 'not_an_array');

		$response = $this->setLocale('de_DE');

		static::assertSame(302, $response->getStatusCode());
		static::assertSame('de_DE', $this->session->get('locale'));
		static::assertSame('not_an_array', $this->session->get('user'));
	}

	/**
	 * @return array<string,array{string,string}>
	 */
	public static function redirectProvider(): array
	{
		return [
			'back to the page'             => ['/playlists?elements_page=2', '/playlists?elements_page=2'],
			'full url of this site'        => ['https://garlic-hub.test/playlists?elements_page=2#top', '/playlists?elements_page=2'],
			'no referer'                   => ['', '/'],
			'url of this site without path' => ['https://garlic-hub.test', '/'],
			'host only looks the same'     => ['https://garlic-hub.test.evil.example/x', '/'],
			'user info trick'              => ['https://garlic-hub.test@evil.example/x', '/'],
			'unparsable url'               => ['http:///evil', '/'],
			'ftp'                          => ['ftp://garlic-hub.test/x', '/'],
			'absolute url of another site' => ['https://evil.example/login', '/'],
			'protocol relative url'        => ['//evil.example/login', '/'],
			'backslash trick'              => ['/\evil.example', '/'],
			'javascript'                   => ['javascript:alert(1)', '/'],
			'relative without slash'       => ['playlists', '/'],
		];
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	#[DataProvider('redirectProvider')]
	public function testTheRedirectGoesOnlyToAPathOfThisSite(string $referer, string $expected): void
	{
		$response = $this->setLocale('de_DE', $referer);

		static::assertSame(302, $response->getStatusCode());
		static::assertSame($expected, $response->getHeaderLine('Location'));
	}
}
