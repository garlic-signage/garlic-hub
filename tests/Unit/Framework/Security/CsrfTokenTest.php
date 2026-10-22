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

namespace Tests\Unit\Framework\Security;

use App\Framework\Security\Crypt;
use App\Framework\Security\CsrfToken;
use App\Framework\Http\Session;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CsrfTokenTest extends TestCase
{
	private Session&MockObject $sessionMock;
	private Crypt&MockObject $cryptMock;
	private CsrfToken $csrfToken;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->sessionMock = $this->createMock(Session::class);
		$this->cryptMock = $this->createMock(Crypt::class);
		$this->csrfToken = new CsrfToken($this->cryptMock, $this->sessionMock);
	}

	#[Group('units')]
	public function testGetTokenGeneratesNewTokenWhenNoTokenExists(): void
	{
		$this->sessionMock->method('get')
			->with(CsrfToken::CSRF_TOKEN_SESSION_KEY)
			->willReturn(null);

		$this->cryptMock->method('generateRandomString')
			->with(CsrfToken::CSRF_TOKEN_LENGTH)
			->willReturn('newGeneratedToken');

		$this->sessionMock
			->expects($this->once())
			->method('set')
			->with(CsrfToken::CSRF_TOKEN_SESSION_KEY, 'newGeneratedToken');

		$result = $this->csrfToken->getToken();

		static::assertSame('newGeneratedToken', $result);
	}

	#[Group('units')]
	public function testGetTokenGeneratesTokenWhenSessionTokenIsNotAString(): void
	{
		$this->sessionMock->method('get')
			->with(CsrfToken::CSRF_TOKEN_SESSION_KEY)
			->willReturn(['invalidType']);

		$this->cryptMock->method('generateRandomString')
			->with(CsrfToken::CSRF_TOKEN_LENGTH)
			->willReturn('newGeneratedToken');

		$this->sessionMock->expects($this->once())
			->method('set')
			->with(CsrfToken::CSRF_TOKEN_SESSION_KEY, 'newGeneratedToken');

		$result = $this->csrfToken->getToken();

		static::assertSame('newGeneratedToken', $result);
	}

	#[Group('units')]
	public function testValidateTokenReturnsTrueForMatchingToken(): void
	{
		$this->cryptMock->expects($this->never())->method($this->anything());
		$validToken = 'validToken';
		$this->sessionMock->method('get')
			->with(CsrfToken::CSRF_TOKEN_SESSION_KEY)
			->willReturn($validToken);

		$result = $this->csrfToken->validateToken($validToken);

		static::assertTrue($result);
	}

	#[Group('units')]
	public function testValidateTokenReturnsFalseForNonMatchingToken(): void
	{
		$this->cryptMock->expects($this->never())->method($this->anything());
		$sessionToken = 'validToken';
		$receivedToken = 'invalidToken';

		$this->sessionMock->method('get')
			->with(CsrfToken::CSRF_TOKEN_SESSION_KEY)
			->willReturn($sessionToken);

		$result = $this->csrfToken->validateToken($receivedToken);

		static::assertFalse($result);
	}

	#[Group('units')]
	public function testValidateTokenReturnsFalseIfSessionTokenIsInvalid(): void
	{
		$this->cryptMock->expects($this->never())->method($this->anything());
		$this->sessionMock->method('get')
			->with(CsrfToken::CSRF_TOKEN_SESSION_KEY)
			->willReturn(null);

		$result = $this->csrfToken->validateToken('someToken');

		static::assertFalse($result);
	}

	/**
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testGenerateTokenSetsValidToken(): void
	{
		$this->cryptMock->method('generateRandomString')
			->with(CsrfToken::CSRF_TOKEN_LENGTH)
			->willReturn('newGeneratedToken');

		$this->sessionMock->expects($this->once())->method('set')
			->with(CsrfToken::CSRF_TOKEN_SESSION_KEY, 'newGeneratedToken');

		$this->csrfToken->generateToken();

		static::assertSame('newGeneratedToken', $this->csrfToken->getToken());
	}

	/**
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testGenerateTokenReplacesExistingToken(): void
	{
		$this->sessionMock->method('get')
			->with(CsrfToken::CSRF_TOKEN_SESSION_KEY)
			->willReturn('existingToken');

		$this->cryptMock->method('generateRandomString')
			->with(CsrfToken::CSRF_TOKEN_LENGTH)
			->willReturn('replacedToken');

		$this->sessionMock->expects($this->once())->method('set')
			->with(CsrfToken::CSRF_TOKEN_SESSION_KEY, 'replacedToken');

		$this->csrfToken->generateToken();

		static::assertSame('replacedToken', $this->csrfToken->getToken());
	}


	#[Group('units')]
	public function testDestroyTokenRemovesTokenFromSession(): void
	{
		$this->cryptMock->expects($this->never())->method($this->anything());
		$this->sessionMock->expects($this->once())->method('delete')
			->with(CsrfToken::CSRF_TOKEN_SESSION_KEY);

		$this->csrfToken->destroyToken();
	}

}
