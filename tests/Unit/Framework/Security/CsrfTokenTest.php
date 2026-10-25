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
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Defuse\Crypto\Key;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\MemorySession;

/**
 * CsrfToken with the real Crypt and a session in memory: what counts is which token the visitor gets
 * and which one is accepted.
 */
class CsrfTokenTest extends TestCase
{
	private MemorySession $session;
	private Crypt $crypt;
	private CsrfToken $csrfToken;

	/**
	 * @throws EnvironmentIsBrokenException
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->session   = new MemorySession();
		$this->crypt     = new Crypt(Key::createNewRandomKey());
		$this->csrfToken = new CsrfToken($this->crypt, $this->session);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testGetTokenGeneratesAnUnguessableTokenAndKeepsItInTheSession(): void
	{
		$token = $this->csrfToken->getToken();

		static::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
		static::assertSame($token, $this->session->get(CsrfToken::CSRF_TOKEN_SESSION_KEY));
	}

	#[Group('units')]
	public function testGetTokenReturnsTheSameTokenEveryTime(): void
	{
		static::assertSame($this->csrfToken->getToken(), $this->csrfToken->getToken());
	}

	/**
	 * A token which is already in the session is not replaced: a form opened in another tab stays valid.
	 */
	#[Group('units')]
	public function testAnExistingSessionTokenIsUsed(): void
	{
		$this->session->set(CsrfToken::CSRF_TOKEN_SESSION_KEY, 'existing_token');

		$csrfToken = new CsrfToken($this->crypt, $this->session);

		static::assertSame('existing_token', $csrfToken->getToken());
		static::assertSame('existing_token', $this->session->get(CsrfToken::CSRF_TOKEN_SESSION_KEY));
	}

	#[Group('units')]
	public function testTheTokenIsTheSameInTheNextRequestOfTheSession(): void
	{
		$token = $this->csrfToken->getToken();

		static::assertSame($token, new CsrfToken($this->crypt, $this->session)->getToken());
	}

	/**
	 * @param string|list<string>|int $invalid
	 */
	#[Group('units')]
	#[DataProvider('invalidSessionValueProvider')]
	public function testAnInvalidSessionValueIsReplaced(string|array|int $invalid): void
	{
		$this->session->data[CsrfToken::CSRF_TOKEN_SESSION_KEY] = $invalid;

		$token = new CsrfToken($this->crypt, $this->session)->getToken();

		static::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
	}

	/**
	 * @return array<string, array{string|list<string>|int}>
	 */
	public static function invalidSessionValueProvider(): array
	{
		return ['array' => [['invalidType']], 'integer' => [5], 'empty string' => ['']];
	}

	#[Group('units')]
	public function testValidateTokenAcceptsTheTokenOfTheSession(): void
	{
		$token = $this->csrfToken->getToken();

		static::assertTrue($this->csrfToken->validateToken($token));
	}

	#[Group('units')]
	#[DataProvider('wrongTokenProvider')]
	public function testValidateTokenRejectsEveryOtherToken(string $received): void
	{
		$this->session->set(CsrfToken::CSRF_TOKEN_SESSION_KEY, 'abcdef0123456789');

		static::assertFalse($this->csrfToken->validateToken($received));
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function wrongTokenProvider(): array
	{
		return [
			'other token'   => ['0123456789abcdef'],
			'prefix'        => ['abcdef'],
			'with suffix'   => ['abcdef0123456789x'],
			'upper case'    => ['ABCDEF0123456789'],
			'with space'    => [' abcdef0123456789'],
			'empty'         => [''],
		];
	}

	/**
	 * Without a token in the session nothing is valid, not even an empty one.
	 */
	#[Group('units')]
	#[DataProvider('noTokenProvider')]
	public function testValidateTokenRejectsEverythingWithoutASessionToken(string $received): void
	{
		static::assertFalse($this->csrfToken->validateToken($received));
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function noTokenProvider(): array
	{
		return ['empty' => [''], 'some token' => ['someToken']];
	}

	/**
	 * An empty token in the session must not be matched by an empty token of the request.
	 */
	#[Group('units')]
	public function testValidateTokenRejectsAnEmptySessionToken(): void
	{
		$this->session->data[CsrfToken::CSRF_TOKEN_SESSION_KEY] = '';

		static::assertFalse($this->csrfToken->validateToken(''));
		static::assertFalse($this->csrfToken->validateToken('anything'));
	}

	#[Group('units')]
	public function testValidateTokenRejectsAnInvalidSessionValue(): void
	{
		$this->session->data[CsrfToken::CSRF_TOKEN_SESSION_KEY] = ['array'];

		static::assertFalse($this->csrfToken->validateToken('array'));
		static::assertFalse($this->csrfToken->validateToken(''));
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testGenerateTokenReplacesTheExistingTokenAndMakesTheOldOneInvalid(): void
	{
		$old = $this->csrfToken->getToken();

		$this->csrfToken->generateToken();
		$new = $this->csrfToken->getToken();

		static::assertNotSame($old, $new);
		static::assertSame($new, $this->session->get(CsrfToken::CSRF_TOKEN_SESSION_KEY));
		static::assertTrue($this->csrfToken->validateToken($new));
		static::assertFalse($this->csrfToken->validateToken($old));
	}

	#[Group('units')]
	public function testDestroyTokenRemovesTheTokenAndMakesItInvalid(): void
	{
		$token = $this->csrfToken->getToken();

		$this->csrfToken->destroyToken();

		static::assertNull($this->session->get(CsrfToken::CSRF_TOKEN_SESSION_KEY));
		static::assertFalse($this->csrfToken->validateToken($token));
	}

	/**
	 * After destroying (e.g. at logout) the next page gets a new token.
	 */
	#[Group('units')]
	public function testAfterDestroyingTheNextTokenIsANewOne(): void
	{
		$old = $this->csrfToken->getToken();
		$this->csrfToken->destroyToken();

		$new = $this->csrfToken->getToken();

		static::assertNotSame($old, $new);
		static::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $new);
		static::assertTrue($this->csrfToken->validateToken($new));
	}

	#[Group('units')]
	public function testDestroyTokenWithoutATokenIsHarmless(): void
	{
		$this->csrfToken->destroyToken();

		static::assertNull($this->session->get(CsrfToken::CSRF_TOKEN_SESSION_KEY));
	}
}
