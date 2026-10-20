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

namespace Tests\Unit\Modules\Users\Helper\InitialAdmin;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\FormParameters\BaseEditParameters;
use App\Framework\Utils\Html\FieldInterface;
use App\Modules\Users\Helper\InitialAdmin\FormElementsCreator;
use App\Modules\Users\Helper\InitialAdmin\Parameters;
use App\Modules\Users\Helper\InitialAdmin\SettingsFormBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SettingsFormBuilderTest extends TestCase
{
	private FormElementsCreator&MockObject $formElementsCreatorMock;
	private SettingsFormBuilder $builder;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->formElementsCreatorMock = $this->createMock(FormElementsCreator::class);
		$this->builder = new SettingsFormBuilder($this->formElementsCreatorMock);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildFormWithDefaults(): void
	{
		$this->expectFields('', '', 'en_US');

		static::assertSame(['hidden' => [], 'visible' => []], $this->builder->buildForm([], 'pattern'));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildFormWithUserInput(): void
	{
		$this->expectFields('admin', 'admin@example.com', 'de_DE');

		$this->builder->buildForm([
			Parameters::PARAMETER_ADMIN_NAME   => 'admin',
			Parameters::PARAMETER_ADMIN_EMAIL  => 'admin@example.com',
			Parameters::PARAMETER_ADMIN_LOCALE => 'de_DE'
		], 'pattern');
	}

	/**
	 * @throws Exception
	 */
	private function expectFields(string $username, string $email, string $locale): void
	{
		$usernameField        = $this->createStub(FieldInterface::class);
		$emailField           = $this->createStub(FieldInterface::class);
		$passwordField        = $this->createStub(FieldInterface::class);
		$passwordConfirmField = $this->createStub(FieldInterface::class);
		$localeField          = $this->createStub(FieldInterface::class);
		$csrfTokenField       = $this->createStub(FieldInterface::class);
		$form = [
			Parameters::PARAMETER_ADMIN_NAME             => $usernameField,
			Parameters::PARAMETER_ADMIN_EMAIL            => $emailField,
			Parameters::PARAMETER_ADMIN_PASSWORD         => $passwordField,
			Parameters::PARAMETER_ADMIN_PASSWORD_CONFIRM => $passwordConfirmField,
			Parameters::PARAMETER_ADMIN_LOCALE           => $localeField,
			BaseEditParameters::PARAMETER_CSRF_TOKEN     => $csrfTokenField,
		];

		$this->formElementsCreatorMock->expects($this->once())->method('createUserNameField')
			->with($username)->willReturn($usernameField);
		$this->formElementsCreatorMock->expects($this->once())->method('createEmailField')
			->with($email)->willReturn($emailField);
		$this->formElementsCreatorMock->expects($this->once())->method('createPasswordField')
			->with('', 'pattern')->willReturn($passwordField);
		$this->formElementsCreatorMock->expects($this->once())->method('createPasswordConfirmField')
			->with('')->willReturn($passwordConfirmField);
		$this->formElementsCreatorMock->expects($this->once())->method('createUserLocaleField')
			->with($locale)->willReturn($localeField);
		$this->formElementsCreatorMock->expects($this->once())->method('createCSRFTokenField')
			->willReturn($csrfTokenField);
		$this->formElementsCreatorMock->expects($this->once())->method('prepareForm')
			->with($form)->willReturn(['hidden' => [], 'visible' => []]);
	}
}
