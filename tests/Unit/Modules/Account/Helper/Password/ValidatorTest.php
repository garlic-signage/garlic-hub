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

namespace Tests\Unit\Modules\Account\Helper\Password;

use PHPUnit\Framework\Attributes\DataProvider;
use App\Framework\Localization\Translate\Translator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Parameters, CSRF token and validator are the real ones, see PasswordFixture.
 */
class ValidatorTest extends TestCase
{
	private PasswordFixture $fixture;

	private function translator(): Translator
	{
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(PasswordFixture::translation());

		return $translator;
	}

	/**
	 * @throws \Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->fixture = new PasswordFixture($this->translator());
	}

	/**
	 * @param array<string,mixed> $post
	 * @return string[]
	 * @throws \Exception
	 */
	private function validate(array $post): array
	{
		$this->fixture->parameters->setUserInputs($post);
		$this->fixture->parameters->parseInputAllParameters();

		return $this->fixture->validator->validateUserInput(PasswordFixture::PATTERN);
	}

	/**
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testAValidPostHasNoErrors(): void
	{
		static::assertSame([], $this->validate($this->fixture->post()));
	}

	/**
	 * @return array<string,array{array<string,mixed>,list<string>}>
	 */
	public static function invalidProvider(): array
	{
		return [
			'both missing'          => [['password' => '', 'password_confirm' => ''], ['account.no_password', 'account.no_password_confirm', 'account.password_explanation']],
			'both not sent'         => [['password' => null, 'password_confirm' => null], ['account.no_password', 'account.no_password_confirm', 'account.password_explanation']],
			'confirm missing'       => [['password_confirm' => ''], ['account.no_password_confirm', 'account.no_passwords_match']],
			'password missing'      => [['password' => ''], ['account.no_password', 'account.no_passwords_match', 'account.password_explanation']],
			'do not match'          => [['password_confirm' => 'Secret124'], ['account.no_passwords_match']],
			'too short'             => [['password' => 'Se1', 'password_confirm' => 'Se1'], ['account.password_explanation']],
			'no digit'              => [['password' => 'SecretOnly', 'password_confirm' => 'SecretOnly'], ['account.password_explanation']],
			'no capital'            => [['password' => 'secret123', 'password_confirm' => 'secret123'], ['account.password_explanation']],
			'only spaces'           => [['password' => '        ', 'password_confirm' => '        '], ['account.password_explanation']],
			// a line break at the end is no part of a password, the pattern must not accept it
			'line break at the end' => [['password' => "Secret123\n", 'password_confirm' => "Secret123\n"], ['account.password_explanation']],
			'weak and different'    => [['password' => 'short', 'password_confirm' => 'other'], ['account.no_passwords_match', 'account.password_explanation']],
			'array as password'     => [['password' => ['Secret123'], 'password_confirm' => ['Secret123']], ['account.no_password', 'account.no_password_confirm', 'account.password_explanation']],
		];
	}

	/**
	 * @param array<string,mixed> $changes
	 * @param list<string> $expected
	 * @throws \Exception
	 */
	#[Group('units')]
	#[DataProvider('invalidProvider')]
	public function testAnInvalidPasswordIsReported(array $changes, array $expected): void
	{
		static::assertSame($expected, $this->validate($this->fixture->post($changes)));
	}

	/**
	 * @return array<string,array{array<string,mixed>}>
	 */
	public static function wrongCsrfProvider(): array
	{
		return [
			'not sent'  => [['csrf_token' => null]],
			'empty'     => [['csrf_token' => '']],
			'wrong'     => [['csrf_token' => 'not-the-token']],
			'an array'  => [['csrf_token' => ['x']]],
		];
	}

	/**
	 * @param array<string,mixed> $changes
	 * @throws \Exception
	 */
	#[Group('units')]
	#[DataProvider('wrongCsrfProvider')]
	public function testAWrongCsrfTokenIsReported(array $changes): void
	{
		static::assertSame(['security.csrf_token_mismatch'], $this->validate($this->fixture->post($changes)));
	}

	/**
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testWithoutATokenInTheSessionNothingIsAccepted(): void
	{
		$post = $this->fixture->post();
		$this->fixture->session->clear();

		static::assertSame(['security.csrf_token_mismatch'], $this->validate($post));
	}

	/**
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testAllErrorsAreReportedTogether(): void
	{
		$errors = $this->validate($this->fixture->post(['csrf_token' => 'x', 'password' => 'a', 'password_confirm' => 'b']));

		static::assertSame(['security.csrf_token_mismatch', 'account.no_passwords_match', 'account.password_explanation'], $errors);
	}
}
