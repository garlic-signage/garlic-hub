<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Unit\Framework\Http;

use App\Framework\Http\Cookie;
use App\Framework\Exceptions\FrameworkException;
use DateTime;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class CookieTest extends TestCase
{
	use PHPMock;
	private Cookie $cookie;

	/** @var array<string,mixed> */
	private array $serverBackup;

	protected function setUp(): void
	{
		parent::setUp();
		$this->serverBackup = $_SERVER;
		$_COOKIE            = [];
		unset($_SERVER['HTTPS'], $_SERVER['SERVER_PORT'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
		$this->cookie       = new Cookie();
	}

	protected function tearDown(): void
	{
		$_COOKIE = [];
		$_SERVER = $this->serverBackup;
		parent::tearDown();
	}

	#[Group('units')]
	public function testGetCookieExists(): void
	{
		$_COOKIE = ['test_cookie' => 'test_value'];
		$result = $this->cookie->getCookie('test_cookie');
		self::assertSame('test_value', $result);
	}

	#[Group('units')]
	public function testGetCookieDoesNotExist(): void
	{
		$result = $this->cookie->getCookie('nonexistent_cookie');

		self::assertNull($result);
	}


	/**
	 * @throws FrameworkException
	 */
	#[RunInSeparateProcess] #[Group('units')]
	public function testCreateCookieSuccess(): void
	{
		// the timestamp is taken from the object, a hard coded one would depend on the local timezone
		$expire = new DateTime('2035-05-17');

		$setcookieMock = $this->getFunctionMock('App\Framework\Http', 'setcookie');
		$setcookieMock->expects($this->once())
			->with('test_cookie', 'test_value', ['expires' => $expire->getTimestamp(), 'path' => '/', 'secure' => false, 'httponly' => true, 'samesite' => 'Lax'])
			->willReturn(true);

		$this->cookie->createCookie('test_cookie', 'test_value', $expire);
	}

	/**
	 * @throws FrameworkException
	 */
	#[RunInSeparateProcess] #[Group('units')]
	public function testCreateCookieIsSecureOverHttps(): void
	{
		$_SERVER['HTTPS'] = 'on';
		$expire = new DateTime('2035-05-17');

		$setcookieMock = $this->getFunctionMock('App\Framework\Http', 'setcookie');
		$setcookieMock->expects($this->once())
			->with('test_cookie', 'test_value', ['expires' => $expire->getTimestamp(), 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax'])
			->willReturn(true);

		$this->cookie->createCookie('test_cookie', 'test_value', $expire);
	}

	#[RunInSeparateProcess] #[Group('units')]
	public function testCreateCookieFailure(): void
	{
		$expire = new DateTime('2035-05-17');

		$setcookieMock = $this->getFunctionMock('App\Framework\Http', 'setcookie');
		$setcookieMock->expects($this->once())
			->with('test_cookie', 'test_value', ['expires' => $expire->getTimestamp(), 'path' => '/', 'secure' => false, 'httponly' => true, 'samesite' => 'Lax'])
			->willReturn(false);

		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('Cookie failed to set.');

		$this->cookie->createCookie('test_cookie', 'test_value', $expire);
	}

	#[RunInSeparateProcess] #[Group('units')]
	public function testDeleteCookieExpiresItInThePastForThePathOfTheApp(): void
	{
		$setcookieMock = $this->getFunctionMock('App\Framework\Http', 'setcookie');
		$setcookieMock->expects($this->once())
			->with('test_cookie', '', static::lessThan(time()), '/')
			->willReturn(true);

		$this->cookie->deleteCookie('test_cookie');
	}

	#[Group('units')]
	public function testHasCookieExists(): void
	{
		$_COOKIE = ['existing_cookie' => 'value'];
		$result = $this->cookie->hasCookie('existing_cookie');

		self::assertTrue($result);
	}

	#[Group('units')]
	public function testHasCookieDoesNotExist(): void
	{
		$result = $this->cookie->hasCookie('missing_cookie');

		self::assertFalse($result);
	}
}
