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

namespace Tests\Unit\Framework\Validation;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Security\Crypt;
use App\Framework\Security\CsrfToken;
use App\Framework\Utils\FormParameters\BaseEditParameters;
use App\Framework\Validation\BaseValidator;
use App\Framework\Validation\Sanitizer;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Defuse\Crypto\Key;
use ErrorException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\MemorySession;

class BaseValidatorTest extends TestCase
{
	/** The password pattern of config/settings/config_main.ini */
	private const string PASSWORD_PATTERN = '(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}';

	private CsrfToken $csrfToken;
	private BaseValidator $baseValidator;

	/**
	 * @throws EnvironmentIsBrokenException
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(static fn(string $key, string $module): string => $key . '@' . $module);

		$this->csrfToken     = new CsrfToken(new Crypt(Key::createNewRandomKey()), new MemorySession());
		$this->baseValidator = new BaseValidator($translator, $this->csrfToken);
	}

	/**
	 * @return array<string, array{string, bool}>
	 */
	public static function emailProvider(): array
	{
		return [
			'normal'                   => ['test@example.com', true],
			'with a plus and subdomain' => ['anna+news@mail.example.co.uk', true],
			'one letter domain parts'  => ['a@b.c', true],
			'no at sign'               => ['invalid-email', false],
			'no domain'                => ['anna@', false],
			'no name'                  => ['@example.com', false],
			'no dot in the domain'     => ['anna@localhost', false],
			'space inside'             => ['an na@example.com', false],
			'space before'             => [' anna@example.com', false],
			'line break after'         => ["anna@example.com\n", false],
			'two at signs'             => ['anna@@example.com', false],
			'name too long'            => [str_repeat('a', 70) . '@example.com', false],
			'umlaut in the name'       => ['üser@example.com', false],
			'umlaut domain'            => ['anna@münchen.de', false],
			'empty'                    => ['', false],
		];
	}

	#[Group('units')]
	#[DataProvider('emailProvider')]
	public function testIsEmail(string $value, bool $expected): void
	{
		static::assertSame($expected, $this->baseValidator->isEmail($value));
	}

	/**
	 * @return array<string, array{string, bool}>
	 */
	public static function jsonProvider(): array
	{
		return [
			'object'           => ['{"key": "value"}', true],
			'list'             => ['[1, 2, 3]', true],
			'empty object'     => ['{}', true],
			'surrounding space' => [' {"a": 1} ', true],
			// valid JSON, but no object or list: Sanitizer::jsonArray() makes an empty array of them
			'null'             => ['null', true],
			'number'           => ['5', true],
			'string'           => ['"x"', true],
			'invalid'          => ['invalid-json', false],
			'trailing comma'   => ['{"a": 1,}', false],
			'text behind'      => ['{"a": 1}x', false],
			'single quotes'    => ["{'a': 1}", false],
			'cut off'          => ['{"a":', false],
			'empty'            => ['', false],
		];
	}

	#[Group('units')]
	#[DataProvider('jsonProvider')]
	public function testIsJson(string $value, bool $expected): void
	{
		static::assertSame($expected, $this->baseValidator->isJson($value));
	}

	/**
	 * @return array<string, array{string, bool}>
	 */
	public static function passwordProvider(): array
	{
		return [
			'all kinds, 8 signs'      => ['Abcdefg1', true],
			'longer with specials'    => ['Abcd!efg1-hij#', true],
			'umlaut counts as a sign' => ['Abcdefü1', true],
			'too short'               => ['Abcdef1', false],
			'no digit'                => ['Abcdefgh', false],
			'no upper case'           => ['abcdefg1', false],
			'no lower case'           => ['ABCDEFG1', false],
			'empty'                   => ['', false],
			// a line break at the end is no part of a password
			'line break at the end'   => ["Abcdefg1\n", false],
			'line break inside'       => ["Abcd\nefg1", false],
		];
	}

	#[Group('units')]
	#[DataProvider('passwordProvider')]
	public function testValidatePassword(string $password, bool $expected): void
	{
		static::assertSame($expected, $this->baseValidator->validatePassword($password, self::PASSWORD_PATTERN));
	}

	/**
	 * bcrypt ignores everything after the 72nd byte, so a longer password is refused (the limit counts bytes).
	 */
	#[Group('units')]
	public function testAPasswordLongerThan72BytesIsRefused(): void
	{
		static::assertTrue($this->baseValidator->validatePassword('Aa1' . str_repeat('x', 69), self::PASSWORD_PATTERN));
		static::assertFalse($this->baseValidator->validatePassword('Aa1' . str_repeat('x', 70), self::PASSWORD_PATTERN));
		static::assertTrue($this->baseValidator->validatePassword('Aa1' . str_repeat('ü', 34), self::PASSWORD_PATTERN));
		static::assertFalse($this->baseValidator->validatePassword('Aa1' . str_repeat('ü', 35), self::PASSWORD_PATTERN));
	}

	#[Group('units')]
	public function testThePatternMatchesTheWholePassword(): void
	{
		static::assertTrue($this->baseValidator->validatePassword('abc', 'abc'));
		static::assertFalse($this->baseValidator->validatePassword('xabc', 'abc'));
		static::assertFalse($this->baseValidator->validatePassword('abcx', 'abc'));
	}

	/**
	 * The pattern is written between slashes: a slash in the pattern has to be escaped, or the pattern is invalid.
	 */
	#[Group('units')]
	public function testAnEscapedSlashInThePatternWorks(): void
	{
		static::assertTrue($this->baseValidator->validatePassword('ab/cd', 'ab\\/cd'));
	}

	#[Group('units')]
	public function testASlashWhichIsNotEscapedMakesThePatternInvalid(): void
	{
		set_error_handler(static function (int $no, string $message): never
		{
			throw new ErrorException($message, 0, $no);
		});

		try
		{
			$this->expectException(ErrorException::class);
			$this->baseValidator->validatePassword('ab/cd', 'ab/cd');
		}
		finally
		{
			restore_error_handler();
		}
	}

	#[Group('units')]
	public function testValidateCsrfToken(): void
	{
		$token = $this->csrfToken->getToken();

		static::assertTrue($this->baseValidator->validateCsrfToken($token));
		static::assertFalse($this->baseValidator->validateCsrfToken($token . 'x'));
		static::assertFalse($this->baseValidator->validateCsrfToken(''));
	}

	#[Group('units')]
	public function testAnotherSessionHasAnotherToken(): void
	{
		$other = new CsrfToken(new Crypt(Key::createNewRandomKey()), new MemorySession());

		static::assertFalse($this->baseValidator->validateCsrfToken($other->getToken()));
	}

	/**
	 * @param array<string,mixed> $input
	 * @throws ModuleException
	 */
	private function formParameters(array $input): BaseEditParameters
	{
		$parameters = new class extends BaseEditParameters
		{
			public function __construct()
			{
				parent::__construct('testmodule', new Sanitizer(), new MemorySession());
				$this->currentParameters = $this->defaultParameters;
				$this->userInputs        = [];
			}
		};

		return $parameters->setUserInputs($input)->parseInputAllParameters();
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testAFormWithTheTokenOfTheSessionHasNoError(): void
	{
		$parameters = $this->formParameters([BaseEditParameters::PARAMETER_CSRF_TOKEN => $this->csrfToken->getToken()]);

		static::assertSame([], $this->baseValidator->validateFormCsrfToken($parameters));
	}

	/**
	 * @return array<string, array{array<string,mixed>}>
	 */
	public static function wrongTokenProvider(): array
	{
		return [
			'no token sent'  => [[]],
			'empty token'    => [['csrf_token' => '']],
			'only spaces'    => [['csrf_token' => '   ']],
			'another token'  => [['csrf_token' => 'another']],
		];
	}

	/**
	 * @param array<string,mixed> $input
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	#[DataProvider('wrongTokenProvider')]
	public function testAFormWithAWrongOrMissingTokenHasTheMismatchError(array $input): void
	{
		$this->csrfToken->getToken();

		static::assertSame(['csrf_token_mismatch@security'], $this->baseValidator->validateFormCsrfToken($this->formParameters($input)));
	}

	/**
	 * With a token in the session but none in the request there is no error to "match" by accident.
	 *
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testAnEmptyTokenIsNeverValidEvenWithoutATokenInTheSession(): void
	{
		static::assertSame(['csrf_token_mismatch@security'], $this->baseValidator->validateFormCsrfToken($this->formParameters(['csrf_token' => ''])));
	}
}
