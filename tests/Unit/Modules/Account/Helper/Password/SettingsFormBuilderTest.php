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

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Localization\Translate\Translator;
use App\Modules\Account\Helper\Password\FormElementsCreator;
use App\Modules\Account\Helper\Password\SettingsFormBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class SettingsFormBuilderTest extends TestCase
{
	private PasswordFixture $fixture;
	private SettingsFormBuilder $builder;

	/**
	 * @throws \Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(PasswordFixture::translation());
		$this->fixture = new PasswordFixture($translator);
		$this->builder = new SettingsFormBuilder(new FormElementsCreator($this->fixture->formBuilder, $translator));
	}

	/**
	 * @param list<array<string,string>> $fields
	 * @return list<string> the names of the inputs
	 */
	private function namesOf(array $fields, string $htmlKey): array
	{
		$names = [];
		foreach ($fields as $field)
			$names[] = PasswordFixture::element($field[$htmlKey])?->getAttribute('name') ?? '';

		return $names;
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTheFormHasBothPasswordsAndTheCsrfToken(): void
	{
		$form = $this->builder->buildForm('.{8,}');

		static::assertSame(['password', 'password_confirm'], $this->namesOf($form['visible'], 'HTML_ELEMENT'));
		static::assertSame(['csrf_token'], $this->namesOf($form['hidden'], 'HIDDEN_HTML_ELEMENT'));
		static::assertSame(['password', 'password_confirm'], array_column($form['visible'], 'LANG_ELEMENT_NAME'));
	}

	/**
	 * The CSRF token in the form is the one the validator accepts.
	 *
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTheCsrfFieldCarriesTheTokenOfTheSession(): void
	{
		$form = $this->builder->buildForm('.{8,}');

		$value = PasswordFixture::element($form['hidden'][0]['HIDDEN_HTML_ELEMENT'])?->getAttribute('value') ?? '';
		static::assertTrue($this->fixture->csrfToken->validateToken($value));
		static::assertNotSame('', $value);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testTheLinkTokenIsOnlyInTheFormWhenGiven(): void
	{
		$with    = $this->builder->buildForm('.{8,}', 'abcd');
		$without = $this->builder->buildForm('.{8,}');

		static::assertSame(['csrf_token', 'token'], $this->namesOf($with['hidden'], 'HIDDEN_HTML_ELEMENT'));
		static::assertSame('abcd', PasswordFixture::element($with['hidden'][1]['HIDDEN_HTML_ELEMENT'])?->getAttribute('value'));
		static::assertSame(['csrf_token'], $this->namesOf($without['hidden'], 'HIDDEN_HTML_ELEMENT'));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testThePatternIsOnlyForTheFirstPasswordField(): void
	{
		$form = $this->builder->buildForm('(?=.*\d).{8,}');

		static::assertSame('(?=.*\d).{8,}', PasswordFixture::element($form['visible'][0]['HTML_ELEMENT'])?->getAttribute('pattern'));
		static::assertFalse(PasswordFixture::element($form['visible'][1]['HTML_ELEMENT'])?->hasAttribute('pattern'));
	}
}
