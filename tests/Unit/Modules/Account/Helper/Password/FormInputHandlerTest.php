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

use App\Modules\Account\Helper\Password\FormInputHandler;
use App\Framework\Localization\Translate\Translator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class FormInputHandlerTest extends TestCase
{
	private PasswordFixture $fixture;
	private FormInputHandler $handler;

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
		$this->handler = new FormInputHandler($this->fixture->parameters, $this->fixture->validator);
	}

	/**
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testAValidPostHasNoErrorsAndIsParsed(): void
	{
		$post = $this->fixture->post();

		static::assertSame([], $this->handler->validate($post, PasswordFixture::PATTERN));
		static::assertSame(['password' => 'Secret123', 'password_confirm' => 'Secret123'], $this->handler->getParsed());
	}

	/**
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testAnInvalidPostReturnsTheErrors(): void
	{
		$errors = $this->handler->validate($this->fixture->post(['password' => 'weak', 'password_confirm' => 'weak']), PasswordFixture::PATTERN);

		static::assertSame(['account.password_explanation'], $errors);
	}

	/**
	 * The pattern comes with the call: the same input is valid for a weaker pattern.
	 *
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testThePatternOfTheCallDecides(): void
	{
		$post = $this->fixture->post(['password' => 'weak', 'password_confirm' => 'weak']);

		static::assertSame([], $this->handler->validate($post, '.{4,}'));
	}

	/**
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testTheSecondPostReplacesTheFirst(): void
	{
		$this->handler->validate($this->fixture->post(['password' => 'First1234', 'password_confirm' => 'First1234']), PasswordFixture::PATTERN);
		$this->handler->validate($this->fixture->post(['password' => 'Second1234', 'password_confirm' => 'Second1234']), PasswordFixture::PATTERN);

		static::assertSame('Second1234', $this->handler->getParsed()['password']);
	}
}
