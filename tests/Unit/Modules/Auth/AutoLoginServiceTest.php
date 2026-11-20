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
use App\Shared\User\TokenPurposes;
use App\Shared\User\UsersService;
use App\Shared\User\UserTokenService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * The race of parallel requests, which the database tests can not run: the token is unused when the request reads
 * it and used or deleted when it wants to use it. The rest is tested against the database (AutoLoginTest).
 */
#[Group('units')]
class AutoLoginServiceTest extends TestCase
{
	/**
	 * @throws \Exception
	 */
	public function testARequestWhichLostTheRaceLogsInWithoutANewCookie(): void
	{
		$tokens = $this->createMock(UserTokenService::class);
		$tokens->method('findByTokenForAction')->willReturnOnConsecutiveCalls(self::token(null), self::token(date('Y-m-d H:i:s')));
		$tokens->method('useToken')->willReturn(0);
		$tokens->expects($this->never())->method('insertToken');
		$tokens->expects($this->never())->method('deleteAllUserTokens');
		$cookie = $this->createMock(Cookie::class);
		$cookie->expects($this->never())->method('createCookie');
		$cookie->expects($this->never())->method('deleteCookie');
		$users = $this->createMock(UsersService::class);
		$users->expects($this->never())->method('endSessions');

		$service = new AutoLoginService($tokens, $cookie, new NullLogger(), $users);

		static::assertSame(10, $service->loginSilent('token'));
	}

	/**
	 * Deleted between reading and using it (logout in another tab, new password): no login.
	 *
	 * @throws \Exception
	 */
	public function testATokenDeletedMeanwhileDoesNotLogIn(): void
	{
		$tokens = $this->createMock(UserTokenService::class);
		$tokens->method('findByTokenForAction')->willReturnOnConsecutiveCalls(self::token(null), null);
		$tokens->method('useToken')->willReturn(0);
		$tokens->expects($this->never())->method('insertToken');
		$cookie = $this->createMock(Cookie::class);
		$cookie->expects($this->never())->method('createCookie');

		$service = new AutoLoginService($tokens, $cookie, new NullLogger(), $this->createStub(UsersService::class));

		$this->expectException(AuthException::class);
		$this->expectExceptionMessage('Autologin token deleted meanwhile.');
		$service->loginSilent('token');
	}

	/**
	 * @return array{UID:int, company_id:int, username:string, status:int, purpose:string, expires_at:string, used_at:null|string}
	 */
	private static function token(?string $usedAt): array
	{
		return [
			'UID' => 10, 'company_id' => 1, 'username' => 'user', 'status' => 3, 'purpose' => TokenPurposes::AUTOLOGIN->value,
			'expires_at' => date('Y-m-d H:i:s', time() + 3600), 'used_at' => $usedAt
		];
	}
}
