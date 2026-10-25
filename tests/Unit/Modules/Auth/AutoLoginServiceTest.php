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


namespace Tests\Unit\Modules\Auth;

use App\Framework\Exceptions\AuthException;
use App\Framework\Http\Cookie;
use App\Modules\Auth\AutoLoginService;
use App\Shared\User\UserTokenService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * The cookie is the only border here. Everything which works with the tokens in the database is tested
 * with the real database in tests/Integration/Modules/Auth/Services/AutoLoginTest.
 */
class AutoLoginServiceTest extends TestCase
{
	private Cookie&Stub $cookie;
	private AutoLoginService $autoLoginService;

	protected function setUp(): void
	{
		parent::setUp();
		$this->cookie           = $this->createStub(Cookie::class);
		$this->autoLoginService = new AutoLoginService($this->createStub(UserTokenService::class), $this->cookie, new NullLogger());
	}

	#[Group('units')]
	public function testTheTokenIsTheValueOfTheCookie(): void
	{
		$this->cookie->method('hasCookie')->willReturnCallback(static fn(string $name): bool => $name === 'UserLogin');
		$this->cookie->method('getCookie')->willReturnCallback(static fn(string $name): ?string => $name === 'UserLogin' ? 'valid_token' : null);

		static::assertSame('valid_token', $this->autoLoginService->loadTokenFromCookie());
	}

	#[Group('units')]
	public function testWithoutACookieThereIsNoToken(): void
	{
		$this->cookie->method('hasCookie')->willReturn(false);

		$this->expectException(AuthException::class);
		$this->expectExceptionMessage('No cookie for autologin was found.');

		$this->autoLoginService->loadTokenFromCookie();
	}

	#[Group('units')]
	public function testACookieWithoutAValueIsNoToken(): void
	{
		$this->cookie->method('hasCookie')->willReturn(true);
		$this->cookie->method('getCookie')->willReturn(null);

		$this->expectException(AuthException::class);
		$this->expectExceptionMessage('No token in cookie cookie found.');

		$this->autoLoginService->loadTokenFromCookie();
	}
}
