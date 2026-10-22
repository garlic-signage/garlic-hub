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

namespace Tests\Unit\Modules\Account\Helper\Password;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\FormParameters\BaseEditParameters;
use App\Framework\Utils\Html\FieldInterface;
use App\Modules\Account\Helper\Password\FormElementsCreator;
use App\Modules\Account\Helper\Password\Parameters;
use App\Modules\Account\Helper\Password\SettingsFormBuilder;
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
	public function testBuildFormWithoutToken(): void
	{
		$form = $this->expectPasswordFields();
		$this->formElementsCreatorMock->expects($this->never())->method('createPasswordTokenField');
		$this->formElementsCreatorMock->expects($this->once())->method('prepareForm')
			->with($form)->willReturn(['hidden' => [], 'visible' => []]);

		static::assertSame(['hidden' => [], 'visible' => []], $this->builder->buildForm('pattern'));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildFormWithToken(): void
	{
		$form       = $this->expectPasswordFields();
		$tokenField = $this->createStub(FieldInterface::class);
		$form[Parameters::PARAMETER_PASSWORD_TOKEN] = $tokenField;
		$this->formElementsCreatorMock->expects($this->once())->method('createPasswordTokenField')
			->with('abcd')->willReturn($tokenField);
		$this->formElementsCreatorMock->expects($this->once())->method('prepareForm')
			->with($form)->willReturn(['hidden' => [], 'visible' => []]);

		$this->builder->buildForm('pattern', 'abcd');
	}

	/**
	 * @return array<string,FieldInterface>
	 * @throws Exception
	 */
	private function expectPasswordFields(): array
	{
		$passwordField        = $this->createStub(FieldInterface::class);
		$passwordConfirmField = $this->createStub(FieldInterface::class);
		$csrfTokenField       = $this->createStub(FieldInterface::class);

		$this->formElementsCreatorMock->expects($this->once())->method('createPasswordField')
			->with('', 'pattern')->willReturn($passwordField);
		$this->formElementsCreatorMock->expects($this->once())->method('createPasswordConfirmField')
			->with('')->willReturn($passwordConfirmField);
		$this->formElementsCreatorMock->expects($this->once())->method('createCSRFTokenField')
			->willReturn($csrfTokenField);

		return [
			Parameters::PARAMETER_PASSWORD           => $passwordField,
			Parameters::PARAMETER_PASSWORD_CONFIRM   => $passwordConfirmField,
			BaseEditParameters::PARAMETER_CSRF_TOKEN => $csrfTokenField,
		];
	}
}
