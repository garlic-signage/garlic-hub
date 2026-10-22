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


namespace Tests\Unit\Modules\Auth;

use App\Framework\Http\Session;
use App\Framework\Exceptions\UserException;
use App\Modules\Auth\UserSession;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UserSessionTest extends TestCase
{
	private Session&MockObject $sessionMock;
	private UserSession $userSession;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->sessionMock = $this->createMock(Session::class);
		$this->userSession = new UserSession($this->sessionMock);
	}

	/**
	 * @throws UserException
	 */
	#[Group('units')]
	public function testGetUIDSuccessfully(): void
	{
		$sessionData = ['UID' => 42, 'username' => 'testuser', 'locale' => 'en'];
		$this->sessionMock->expects($this->once())->method('get')
			->with('user')
			->willReturn($sessionData);

		$result = $this->userSession->getUID();

		static::assertSame(42, $result);
	}

	#[Group('units')]
	public function testGetUIDThrowsExceptionForIncompleteUserDataInSession(): void
	{
		$sessionData = ['UID' => 42, 'username' => 'testuser'];
		$this->sessionMock->expects($this->once())->method('get')
			->with('user')
			->willReturn($sessionData);

		$this->expectException(UserException::class);
		$this->expectExceptionMessage('User not found in session.');

		$this->userSession->getUID();
	}

	/**
	 * @throws UserException
	 */
	#[Group('units')]
	public function testGetUsernameSuccessfully(): void
	{
		$sessionData = ['UID' => 42, 'username' => 'testuser', 'locale' => 'en'];
		$this->sessionMock->expects($this->once())->method('get')
			->with('user')
			->willReturn($sessionData);

		$result = $this->userSession->getUsername();

		static::assertSame('testuser', $result);
	}

	#[Group('units')]
	public function testGetUsernameThrowsExceptionForIncompleteUserDataInSession(): void
	{
		$sessionData = ['UID' => 42, 'locale' => 'en'];
		$this->sessionMock->expects($this->once()) ->method('get')
			->with('user')
			->willReturn($sessionData);

		$this->expectException(UserException::class);
		$this->expectExceptionMessage('User not found in session.');

		$this->userSession->getUsername();
	}

	/**
	 * @throws UserException
	 */
	#[Group('units')]
	public function testGetLocalesSuccessfully(): void
	{
		$sessionData = ['UID' => 42, 'username' => 'testuser', 'locale' => 'en'];
		$this->sessionMock->expects($this->once())->method('get')
			->with('user')
			->willReturn($sessionData);

		$result = $this->userSession->getLocales();

		static::assertSame('en', $result);
	}

	/**
	 * @throws UserException
	 */
	#[Group('units')]
	public function testGetLocalesUserNull(): void
	{
		$sessionData = ['UID' => 42, 'username' => 'testuser', 'locale' => 'en'];
		$this->sessionMock->expects($this->once())->method('get')
			->with('user')
			->willReturn($sessionData);

		$result = $this->userSession->getLocales();

		static::assertSame('en', $result);
	}

	#[Group('units')]
	public function testGetLocalesThrowsExceptionForIncompleteUserDataInSession(): void
	{
		$sessionData = ['UID' => 42, 'username' => 'testuser'];
		$this->sessionMock->expects($this->once())->method('get')
			->with('user')
			->willReturn($sessionData);

		$this->expectException(UserException::class);
		$this->expectExceptionMessage('User not found in session.');

		$this->userSession->getLocales();
	}

	/**
	 * @throws UserException
	 */
	#[Group('units')]
	public function testCheckUserSuccessfully(): void
	{
		$sessionData = ['UID' => 42, 'username' => 'testuser', 'locale' => 'en'];
		$this->sessionMock->expects($this->once())->method('get')
			->with('user')
			->willReturn($sessionData);

		$this->userSession->getUID();

	}

	#[Group('units')]
	public function testCheckUserThrowsExceptionWhenMissingKey(): void
	{
		$sessionData = ['UID' => 42, 'locale' => 'en']; // Missing `username`
		$this->sessionMock->expects($this->once())->method('get')
			->with('user')
			->willReturn($sessionData);

		$this->expectException(UserException::class);
		$this->expectExceptionMessage('User not found in session.');

		$this->userSession->getUID();
	}

	#[Group('units')]
	public function testCheckUserThrowsExceptionWhenNullSessionData(): void
	{
		$this->sessionMock->expects($this->once())->method('get')
			->with('user')
			->willReturn(null);

		$this->expectException(UserException::class);
		$this->expectExceptionMessage('User not found in session.');

		$this->userSession->getUID();
	}
}
