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

use App\Framework\Localization\Translate\Translator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class ParametersTest extends TestCase
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

	#[Group('units')]
	public function testTheParametersAreTheCsrfTokenAndBothPasswords(): void
	{
		static::assertSame('account', $this->fixture->parameters->getModuleName());
		static::assertEqualsCanonicalizing(['csrf_token', 'password', 'password_confirm'], array_keys($this->fixture->parameters->getCurrentParameters()));
	}

	/**
	 * The link token is no parameter of the form: the controller reads it itself and checks it against the database.
	 */
	#[Group('units')]
	public function testTheLinkTokenIsNoParameter(): void
	{
		static::assertFalse($this->fixture->parameters->hasParameter('token'));
	}

	/**
	 * A password is taken as it is typed: spaces and signs which are markup in other fields belong to it.
	 */
	#[Group('units')]
	public function testAPasswordIsNotChanged(): void
	{
		$password = ' <b>"Pa ss\'&word</b> ';
		$this->fixture->parameters->setUserInputs($this->fixture->post(['password' => $password, 'password_confirm' => $password]));
		$this->fixture->parameters->parseInputAllParameters();

		static::assertSame($password, $this->fixture->parameters->getValueOfParameter('password'));
		static::assertSame($password, $this->fixture->parameters->getValueOfParameter('password_confirm'));
	}

	#[Group('units')]
	public function testMissingInputIsEmpty(): void
	{
		$this->fixture->parameters->setUserInputs([]);
		$this->fixture->parameters->parseInputAllParameters();

		static::assertSame('', $this->fixture->parameters->getValueOfParameter('password'));
		static::assertSame('', $this->fixture->parameters->getValueOfParameter('password_confirm'));
		static::assertSame('', $this->fixture->parameters->getCsrfToken());
	}

	/**
	 * password[]=x in the post must not break the page.
	 */
	#[Group('units')]
	public function testAnArrayInsteadOfAPasswordIsEmpty(): void
	{
		$this->fixture->parameters->setUserInputs($this->fixture->post(['password' => ['x'], 'password_confirm' => ['x']]));
		$this->fixture->parameters->parseInputAllParameters();

		static::assertSame('', $this->fixture->parameters->getValueOfParameter('password'));
		static::assertSame('', $this->fixture->parameters->getValueOfParameter('password_confirm'));
	}
}
