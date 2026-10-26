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

namespace Tests\Integration\Shared\Layout;

use App\Framework\Config\Config;
use App\Framework\Http\Session;
use App\Framework\Localization\Locales\Locales;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Security\CsrfToken;
use App\Shared\Layout\FlashMessagesBuilder;
use App\Shared\Layout\LanguageSelectBuilder;
use App\Shared\Layout\LayoutDataProvider;
use App\Shared\Layout\MainMenuBuilder;
use App\Shared\Layout\UserMenuBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Slim\Flash\Messages;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Integration\Support\ContainerTestCase;

/**
 * What every page gets for its layout: menus, language switch, messages, the CSRF token and the texts of the footer.
 * Users: 1 admin (module admin), 2 anna and 3 bernd without rights.
 */
#[Group('acl')]
class LayoutDataProviderTest extends ContainerTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
	}

	/**
	 * @return array<string,mixed>
	 */
	private function layout(): array
	{
		return $this->container->get(LayoutDataProvider::class)->collect((new ServerRequestFactory())->createServerRequest('GET', '/'));
	}

	public function testAGuestGetsTheLoginAndNoUserMenu(): void
	{
		$data = $this->layout();

		static::assertSame([['URL' => '/login', 'LANG_MENU_POINT' => 'Login']], $data['main_menu']);
		static::assertSame([], $data['user_menu']);
		static::assertSame([], $data['messages']);
	}

	public function testAUserGetsTheMenuOfTheModulesAndHerOwnName(): void
	{
		$this->actingAs(2);

		$data = $this->layout();

		static::assertSame(['/player', '/playlists', '/mediapool'], array_column($data['main_menu'], 'URL'));
		static::assertSame('anna', $data['user_menu'][0]['USERNAME']);
		static::assertSame([], $data['user_menu'][0]['has_user_access']);
	}

	public function testTheAdminGetsTheLinksToTheAdministration(): void
	{
		$this->actingAs(1);

		$access = $this->layout()['user_menu'][0]['has_user_access'];

		static::assertSame(['/users', '/templates'], array_column($access, 'LINK_USER_ACCESS'));
		static::assertSame(['User Management', 'Template overview'], array_column($access, 'LANG_USER_ACCESS'));
	}

	/**
	 * @return array<string,array{list<array{string,int}>,list<string>}> the rights of the user (module and bits) and the links
	 */
	public static function rights(): array
	{
		return [
			'sub admin of the users'     => [[['users', 1]], ['/users']],
			'module admin of the users'  => [[['users', 2]], ['/users']],
			'sub admin of the templates' => [[['templates', 4]], ['/templates']],
			'module admin of templates'  => [[['templates', 8]], ['/templates']],
			'both'                       => [[['users', 1], ['templates', 8]], ['/users', '/templates']],
			'editor of the templates'    => [[['templates', 2]], []],
			'a player admin'             => [[['player', 8]], []],
		];
	}

	/**
	 * @param list<array{string,int}> $rights
	 * @param list<string> $links
	 */
	#[DataProvider('rights')]
	public function testTheLinksAreThoseOfTheModulesTheUserAdministrates(array $rights, array $links): void
	{
		foreach ($rights as [$module, $bits])
			$this->connection->executeStatement('INSERT INTO user_acl (UID, acl, module) VALUES (2, ?, ?)', [$bits, $module]);
		$this->actingAs(2);

		static::assertSame($links, array_column($this->layout()['user_menu'][0]['has_user_access'], 'LINK_USER_ACCESS'));
	}

	public function testTheLanguageSwitchOffersTheLanguagesWhichAreTranslated(): void
	{
		$select = $this->layout()['language_select'];

		static::assertContains('en_US', array_column($select, 'LOCALE_LONG'));
		static::assertContains('de_DE', array_column($select, 'LOCALE_LONG'));
		$german = $select[array_search('de_DE', array_column($select, 'LOCALE_LONG'), true)];
		static::assertSame(['de_DE', 'de', 'German'], [$german['LOCALE_LONG'], $german['LOCALE_SMALL'], $german['LANGUAGE_NAME']]);
	}

	public function testTheLayoutFollowsTheLanguageOfTheUser(): void
	{
		$this->actingAs(3);
		// the login stores the language, the middleware of the application sets it
		$this->container->get(Session::class)->set('locale', 'de_DE');
		$this->container->get(Locales::class)->determineCurrentLocale();

		$data = $this->layout();

		static::assertSame(['de', 'DE', 'ltr'], [$data['CURRENT_LOCALE_LOWER'], $data['CURRENT_LOCALE_UPPER'], $data['CURRENT_TEXT_DIRECTION']]);
		static::assertSame($this->container->get(Translator::class)->translate('privacy', 'menu'), $data['LANG_PRIVACY']);
		static::assertNotSame('Privacy', $data['LANG_PRIVACY']);
	}

	public function testTheMessagesOfTheRequestAreShownWithTheirType(): void
	{
		$flash = $this->container->get(Messages::class);
		$flash->addMessageNow('success', 'Saved');
		$flash->addMessageNow('error', 'Not saved');
		$flash->addMessageNow('error', 'Really not');

		static::assertSame(
			[
				['MESSAGE_TYPE' => 'error', 'has_close_button' => true, 'MESSAGE_TEXT' => 'Not saved'],
				['MESSAGE_TYPE' => 'error', 'has_close_button' => true, 'MESSAGE_TEXT' => 'Really not'],
				['MESSAGE_TYPE' => 'success', 'has_close_button' => false, 'MESSAGE_TEXT' => 'Saved'],
			],
			$this->layout()['messages']
		);
	}

	public function testEveryPageGetsTheCsrfTokenAndTheTextsOfTheFooter(): void
	{
		$data = $this->layout();

		static::assertSame($this->container->get(CsrfToken::class)->getToken(), $data['CSRF_TOKEN']);
		static::assertNotSame('', $data['CSRF_TOKEN']);
		foreach (['LANG_LEGAL_NOTICE', 'LANG_PRIVACY', 'LANG_TERMS'] as $key)
			static::assertNotSame('', $data[$key], $key);
	}

	public function testTheDebugNumbersAreOnlyGivenInTheDebugMode(): void
	{
		static::assertArrayNotHasKey('EXECUTION_TIME', $this->layout());

		$config = $this->createStub(Config::class);
		$config->method('getEnv')->willReturnMap([['APP_DEBUG', 'true'], ['APP_NAME', 'garlic-hub']]);
		$provider = new LayoutDataProvider(
			$this->container->get(MainMenuBuilder::class),
			$this->container->get(UserMenuBuilder::class),
			$this->container->get(LanguageSelectBuilder::class),
			$this->container->get(FlashMessagesBuilder::class),
			$this->container->get(CsrfToken::class),
			$this->container->get(Locales::class),
			$this->container->get(Translator::class),
			$config
		);

		$data = $provider->collect((new ServerRequestFactory())->createServerRequest('GET', '/')->withAttribute('start_time', microtime(true) - 1.0)->withAttribute('start_memory', 0));

		static::assertSame('garlic-hub', $data['APP_NAME']);
		static::assertMatchesRegularExpression('/^1\.\d{6}sec$/', $data['EXECUTION_TIME']);
		static::assertMatchesRegularExpression('/^[\d.]+ KB$/', $data['MEMORY_USAGE']);
		static::assertMatchesRegularExpression('/^[\d.]+ KB$/', $data['PEAK_MEMORY_USAGE']);
	}
}
