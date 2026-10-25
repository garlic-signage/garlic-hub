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

use App\Framework\Http\Session;
use App\Framework\Localization\Locales\Locales;
use App\Modules\Account\Controller\EditLocalesController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Tests\Integration\Support\ContainerTestCase;

/**
 * The change of the language: session, current locale and the language stored for the user in the database.
 * Users of the fixture: 10 regular (de_DE), 14 other (en_US).
 */
#[Group('auth')]
class EditLocalesTest extends ContainerTestCase
{
	private EditLocalesController $controller;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('auth/users.sql');
		$this->controller = $this->container->get(EditLocalesController::class);
	}

	private function setLocale(string $locale, string $referer = '/playlists'): Response
	{
		$request = new ServerRequestFactory()->createServerRequest('GET', '/set-locales/' . $locale)
			->withHeader('Host', 'garlic-hub.test')
			->withHeader('Referer', $referer);

		$response = $this->controller->setLocales($request, new Response(), ['locale' => $locale]);
		static::assertInstanceOf(Response::class, $response);

		return $response;
	}

	private function storedLocale(int $UID): string
	{
		return (string) $this->connection->fetchOne('SELECT locale FROM user_main WHERE UID = ?', [$UID]);
	}

	public function testALoggedInUserKeepsTheLanguageInTheDatabase(): void
	{
		$this->actingAs(10);

		$response = $this->setLocale('en_US', 'https://garlic-hub.test/playlists?elements_page=2');

		static::assertSame(302, $response->getStatusCode());
		static::assertSame('/playlists?elements_page=2', $response->getHeaderLine('Location'));
		static::assertSame('en_US', $this->storedLocale(10));
		static::assertSame('en_US', $this->container->get(Locales::class)->getCurrentLocale());
		static::assertSame('en_US', $this->container->get(Session::class)->get('locale'));
		static::assertSame('en_US', $this->container->get(Session::class)->get('user')['locale']);
	}

	public function testOnlyTheLanguageOfTheLoggedInUserChanges(): void
	{
		$this->actingAs(10);
		$other = $this->storedLocale(14);

		$this->setLocale('fr_FR');

		static::assertSame($other, $this->storedLocale(14));
	}

	public function testWithoutALoginOnlyTheSessionKnowsTheLanguage(): void
	{
		$before = $this->connection->fetchAllKeyValue('SELECT UID, locale FROM user_main');

		$this->setLocale('de_DE');

		static::assertSame('de_DE', $this->container->get(Session::class)->get('locale'));
		static::assertSame('de_DE', $this->container->get(Locales::class)->getCurrentLocale());
		static::assertSame($before, $this->connection->fetchAllKeyValue('SELECT UID, locale FROM user_main'));
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function notAllowedLocales(): array
	{
		return ['script' => ['<script>'], 'sql' => ["de_DE' OR '1'='1"], 'wrong case' => ['de_de'], 'not configured' => ['xx_XX'], 'empty' => [''], 'path' => ['../../etc']];
	}

	#[DataProvider('notAllowedLocales')]
	public function testALocaleOutsideTheWhitelistIsNeverStored(string $locale): void
	{
		$this->actingAs(10);
		$before = $this->connection->fetchAllKeyValue('SELECT UID, locale FROM user_main');

		$response = $this->setLocale($locale);

		static::assertSame(302, $response->getStatusCode());
		static::assertSame($before, $this->connection->fetchAllKeyValue('SELECT UID, locale FROM user_main'));
		static::assertNull($this->container->get(Session::class)->get('locale'));
		static::assertSame('de_DE', $this->container->get(Session::class)->get('user')['locale']);
	}

	public function testTheRedirectNeverLeavesThisSite(): void
	{
		$this->actingAs(10);

		static::assertSame('/', $this->setLocale('en_US', 'https://evil.example/phish')->getHeaderLine('Location'));
	}
}
