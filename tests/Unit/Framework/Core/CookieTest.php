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

namespace Tests\Unit\Framework\Core;

use App\Framework\Core\Cookie;
use App\Framework\Exceptions\FrameworkException;
use DateTime;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[Group('units')]
class CookieTest extends TestCase
{
	use PHPMock;
	private Cookie $cookie;

	protected function setUp(): void
	{
		parent::setUp();
		$this->cookie    = new Cookie();
	}

	#[Group('units')]
	public function testGetCookieExists(): void
	{
		$_COOKIE = ['test_cookie' => 'test_value'];
		$result = $this->cookie->getCookie('test_cookie');
		self::assertSame('test_value', $result);
		$_COOKIE = [];
	}

	#[Group('units')]
	public function testGetCookieDoesNotExist(): void
	{
		$_COOKIE = [];
		$result = $this->cookie->getCookie('nonexistent_cookie');

		self::assertNull($result);
	}


	/**
	 * @throws FrameworkException
	 */
	#[RunInSeparateProcess] #[Group('units')]
	public function testCreateCookieSuccess(): void
	{
		$setcookieMock = $this->getFunctionMock('App\Framework\Core', 'setcookie');
		$setcookieMock->expects($this->once())
			->with('test_cookie', 'test_value', 2062965600, '/', '', false, true)
			->willReturn(true);

		$this->cookie->createCookie('test_cookie', 'test_value', new DateTime('2035-05-17'));
	}

	#[RunInSeparateProcess] #[Group('units')]
	public function testCreateCookieFailure(): void
	{
		$setcookieMock = $this->getFunctionMock('App\Framework\Core', 'setcookie');
		$setcookieMock->expects($this->once())
			->with('test_cookie', 'test_value', 2062965600, '/', '', false, true)
			->willReturn(false);

		$this->expectException(FrameworkException::class);
		$this->expectExceptionMessage('Cookie failed to set.');

		$this->cookie->createCookie('test_cookie', 'test_value', new DateTime('2035-05-17'));
	}

	#[RunInSeparateProcess] #[Group('units')]
	public function testDeleteCookieSuccess(): void
	{
		$setcookieMock = $this->getFunctionMock('App\Framework\Core', 'setcookie');
		$setcookieMock->expects($this->once())
			->with('test_cookie', '', static::lessThan(time()), '/')
			->willReturn(true);

		$this->cookie->deleteCookie('test_cookie');
	}

	#[RunInSeparateProcess] #[Group('units')]
	public function testDeleteCookieWhenCookieNotExists(): void
	{
		$_COOKIE = [];

		$setcookieMock = $this->getFunctionMock('App\Framework\Core', 'setcookie');
		$setcookieMock->expects($this->once())
			->with('nonexistent_cookie', '', static::lessThan(time()), '/')
			->willReturn(true);

		$this->cookie->deleteCookie('nonexistent_cookie');
	}

	#[Group('units')]
	public function testHasCookieExists(): void
	{
		$_COOKIE = ['existing_cookie' => 'value'];
		$result = $this->cookie->hasCookie('existing_cookie');

		self::assertTrue($result);
		$_COOKIE = [];
	}

	#[Group('units')]
	public function testHasCookieDoesNotExist(): void
	{
		$_COOKIE = [];
		$result = $this->cookie->hasCookie('missing_cookie');

		self::assertFalse($result);
	}
}
