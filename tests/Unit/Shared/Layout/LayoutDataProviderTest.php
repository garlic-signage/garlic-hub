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

namespace Tests\Unit\Shared\Layout;

use App\Framework\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Localization\Locales\Locales;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Security\CsrfToken;
use App\Shared\Layout\FlashMessagesBuilder;
use App\Shared\Layout\LanguageSelectBuilder;
use App\Shared\Layout\LayoutDataProvider;
use App\Shared\Layout\MainMenuBuilder;
use App\Shared\Layout\UserMenuBuilder;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

class LayoutDataProviderTest extends TestCase
{
	private Config&Stub $configStub;
	private ServerRequestInterface&Stub $requestStub;
	private LayoutDataProvider $provider;

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->configStub  = $this->createStub(Config::class);
		$this->requestStub = $this->createStub(ServerRequestInterface::class);
		$this->provider    = $this->createProvider('ltr');
	}

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	private function createProvider(string $textDirection): LayoutDataProvider
	{
		$mainMenuStub = $this->createStub(MainMenuBuilder::class);
		$mainMenuStub->method('build')->willReturn([['URL' => '/login', 'LANG_MENU_POINT' => 'Login']]);
		$userMenuStub = $this->createStub(UserMenuBuilder::class);
		$userMenuStub->method('build')->willReturn([]);
		$languageSelectStub = $this->createStub(LanguageSelectBuilder::class);
		$languageSelectStub->method('build')->willReturn([['LOCALE_LONG' => 'de_DE']]);
		$flashStub = $this->createStub(FlashMessagesBuilder::class);
		$flashStub->method('build')->willReturn([['MESSAGE_TEXT' => 'Saved']]);
		$csrfTokenStub = $this->createStub(CsrfToken::class);
		$csrfTokenStub->method('getToken')->willReturn('csrf');
		$localesStub = $this->createStub(Locales::class);
		$localesStub->method('getLanguageCode')->willReturn('de');
		$localesStub->method('getTextDirection')->willReturn($textDirection);
		$translatorStub = $this->createStub(Translator::class);
		$translatorStub->method('translate')->willReturnCallback(static fn(string $key, string $module): string => $module . '.' . $key);

		return new LayoutDataProvider($mainMenuStub, $userMenuStub, $languageSelectStub, $flashStub,
			$csrfTokenStub, $localesStub, $translatorStub, $this->configStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testCollectReturnsLayoutData(): void
	{
		$this->configStub->method('getEnv')->willReturnMap([['APP_NAME', 'Garlic Hub'], ['APP_DEBUG', 'false']]);

		static::assertSame([
			'messages'             => [['MESSAGE_TEXT' => 'Saved']],
			'main_menu'            => [['URL' => '/login', 'LANG_MENU_POINT' => 'Login']],
			'user_menu'            => [],
			'language_select'      => [['LOCALE_LONG' => 'de_DE']],
			'CSRF_TOKEN'           => 'csrf',
			'CURRENT_LOCALE_LOWER' => 'de',
			'CURRENT_LOCALE_UPPER' => 'DE',
			'CURRENT_TEXT_DIRECTION' => 'ltr',
			'APP_NAME'             => 'Garlic Hub',
			'LANG_LEGAL_NOTICE'    => 'menu.legal_notice',
			'LANG_PRIVACY'         => 'menu.privacy',
			'LANG_TERMS'           => 'menu.terms'
		], $this->provider->collect($this->requestStub));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testCollectPassesTheTextDirectionOfTheLocale(): void
	{
		$this->configStub->method('getEnv')->willReturnMap([['APP_NAME', 'Garlic Hub'], ['APP_DEBUG', 'false']]);

		$data = $this->createProvider('rtl')->collect($this->requestStub);

		static::assertSame('rtl', $data['CURRENT_TEXT_DIRECTION']);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testCollectAddsDebugStatsInDebugMode(): void
	{
		$this->configStub->method('getEnv')->willReturnMap([['APP_NAME', 'Garlic Hub'], ['APP_DEBUG', 'true']]);
		$startTime = microtime(true) - 0.5;
		$this->requestStub->method('getAttribute')->willReturnCallback(
			static fn(string $name): float|int => $name === 'start_time' ? $startTime : 0
		);

		$data = $this->provider->collect($this->requestStub);

		static::assertMatchesRegularExpression('/^0\.5\d{5}sec$/', $data['EXECUTION_TIME']);
		static::assertStringEndsWith(' KB', $data['MEMORY_USAGE']);
		static::assertStringEndsWith(' KB', $data['PEAK_MEMORY_USAGE']);
	}
}
