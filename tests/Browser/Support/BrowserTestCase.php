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

namespace Tests\Browser\Support;

use Facebook\WebDriver\Exception\TimeoutException;
use Facebook\WebDriver\Interactions\WebDriverActions;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverHasInputDevices;
use Symfony\Component\Panther\Client;
use Tests\Blackbox\Support\BlackboxTestCase;

/**
 * Smoke tests in a headless Chromium: the same server, database and fixtures as the blackbox tests,
 * with the JavaScript of the project. They check that a page loads, an action works and no error shows up,
 * not the details of the features.
 *
 * Not in the default run: vendor/bin/phpunit --testsuite browser
 * PHPUnit does not inherit groups: every concrete class needs #[Group('browser')] itself.
 */
abstract class BrowserTestCase extends BlackboxTestCase
{
	protected const string PASSWORD = 'Test1234!';

	private ?Client $client = null;

	protected static function withAssets(): bool
	{
		return true;
	}

	protected function tearDown(): void
	{
		$this->client?->quit();
		$this->client = null;
		parent::tearDown();
	}

	/**
	 * A new browser per test: no cookies of the test before.
	 */
	protected function client(): Client
	{
		if ($this->client !== null)
			return $this->client;

		$arguments = ['--headless=new', '--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu', '--window-size=1400,1000',
			// the webcam and the screencast get a fake device, without asking
			'--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream'];
		$options   = ['port' => $this->freePort(), 'capabilities' => ['goog:loggingPrefs' => ['browser' => 'ALL']]];

		$this->client = Client::createChromeClient('/usr/bin/chromedriver', $arguments, $options, $this->deployment()->baseUrl());

		return $this->client;
	}

	protected function login(string $username): void
	{
		$crawler = $this->client()->request('GET', '/login');
		$form    = $crawler->filter('form button[type="submit"]')->form(['username' => $username, 'password' => self::PASSWORD]);
		$this->client()->submit($form);
		// the submit returns before the redirect
		try
		{
			$this->client()->getWebDriver()->wait(10)->until(fn() => !str_contains((string) $this->client()->getCurrentURL(), '/login'));
		}
		catch (TimeoutException)
		{
			static::fail('The login of ' . $username . ' failed: ' . trim(strip_tags((string) $this->client()->getPageSource())));
		}
	}

	/**
	 * Mouse and keyboard: drag and drop, the context menu.
	 */
	protected function actions(): WebDriverActions
	{
		$driver = $this->client()->getWebDriver();
		static::assertInstanceOf(WebDriverHasInputDevices::class, $driver);

		return new WebDriverActions($driver);
	}

	protected function visit(string $path): void
	{
		$this->client()->request('GET', $path);
	}

	protected function click(string $selector): void
	{
		$this->client()->waitForVisibility($selector, 10);
		$this->client()->getWebDriver()->findElement(WebDriverBy::cssSelector($selector))->click();
	}

	protected function waitForVisible(string $selector, int $seconds = 10): void
	{
		$this->client()->waitForVisibility($selector, $seconds);
	}

	protected function waitForGone(string $selector, int $seconds = 10): void
	{
		$this->client()->getWebDriver()->wait($seconds)->until(WebDriverExpectedCondition::invisibilityOfElementLocated(WebDriverBy::cssSelector($selector)));
	}

	protected function text(string $selector): string
	{
		return $this->client()->getWebDriver()->findElement(WebDriverBy::cssSelector($selector))->getText();
	}

	/**
	 * Errors of the JavaScript and console.error() of the application. Network errors are not counted:
	 * the fixtures have no thumbnails.
	 *
	 * @return list<string>
	 */
	protected function javaScriptErrors(): array
	{
		$errors = [];
		foreach ($this->client()->getWebDriver()->manage()->getLog('browser') as $entry)
		{
			if (($entry['level'] ?? '') === 'SEVERE' && ($entry['source'] ?? '') !== 'network')
				$errors[] = (string) ($entry['message'] ?? '');
		}

		return $errors;
	}

	/**
	 * Neither the console nor the page has errors: the services catch the errors of the requests and show them as messages.
	 */
	protected function assertNoErrors(): void
	{
		static::assertSame([], $this->javaScriptErrors(), 'The console has errors. Server log: ' . $this->deployment()->serverLog());

		$messages = [];
		foreach ($this->client()->getWebDriver()->findElements(WebDriverBy::cssSelector('.message-bar--error')) as $message)
			$messages[] = $message->getText();
		static::assertSame([], $messages, 'The page shows errors.');
	}

	private function freePort(): int
	{
		$server = stream_socket_server('tcp://127.0.0.1:0');
		static::assertNotFalse($server);
		$port = (int) parse_url('tcp://' . stream_socket_get_name($server, false), PHP_URL_PORT);
		fclose($server);

		return $port;
	}
}
