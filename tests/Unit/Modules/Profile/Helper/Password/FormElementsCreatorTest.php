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


namespace Tests\Unit\Modules\Profile\Helper\Password;

use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\Html\FieldInterface;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\FormBuilder;
use App\Framework\Utils\Html\PasswordField;
use App\Modules\Profile\Helper\Password\FormElementsCreator;
use App\Modules\Profile\Helper\Password\Parameters;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FormElementsCreatorTest extends TestCase
{
	private FormBuilder&MockObject $formBuilderMock;
	private Translator&MockObject $translatorMock;
	private FormElementsCreator $creator;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->formBuilderMock = $this->createMock(FormBuilder::class);
		$this->translatorMock  = $this->createMock(Translator::class);

		$this->creator = new FormElementsCreator($this->formBuilderMock, $this->translatorMock);
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws CoreException
	 * @throws InvalidArgumentException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	#[Group('units')]
	public function testCreatePasswordField(): void
	{
		$value = 'securePassword123';
		$pattern = '(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9]).{8,}';
		$expectedField = $this->createMock(PasswordField::class);

		$this->translatorMock->expects($this->exactly(2))->method('translate')
			->willReturnMap([
				['password_explanation', 'profile', [], 'Please provide a secure password'],
				[Parameters::PARAMETER_PASSWORD, 'profile', [], 'Password']
			]);

		$this->formBuilderMock->expects($this->once())->method('createField')
			->with([
				'type' => FieldType::PASSWORD,
				'id' => Parameters::PARAMETER_PASSWORD,
				'name' => Parameters::PARAMETER_PASSWORD,
				'title' => 'Please provide a secure password',
				'label' => 'Password',
				'value' => $value,
				'rules' => ['required' => true, 'minlength' => 8],
				'default_value' => ''
			])
			->willReturn($expectedField);

		$expectedField->expects($this->once())->method('setPattern')
			->with($pattern);

		$result = $this->creator->createPasswordField($value, $pattern);

		static::assertSame($expectedField, $result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	#[Group('units')]
	public function testCreatePasswordConfirmField(): void
	{
		$value = 'securePassword123';
		$expectedField = $this->createMock(PasswordField::class);

		$this->translatorMock->expects($this->exactly(2))->method('translate')
			->willReturnMap([
				['password_explanation', 'profile', [], 'Please provide a secure password'],
				[Parameters::PARAMETER_PASSWORD_CONFIRM, 'profile', [], 'Confirm Password']
			]);

		$this->formBuilderMock->expects($this->once())->method('createField')
			->with([
				'type' => FieldType::PASSWORD,
				'id' => Parameters::PARAMETER_PASSWORD_CONFIRM,
				'name' => Parameters::PARAMETER_PASSWORD_CONFIRM,
				'title' => 'Please provide a secure password',
				'label' => 'Confirm Password',
				'value' => $value,
				'rules' => ['required' => true, 'minlength' => 8],
				'default_value' => ''
			])
			->willReturn($expectedField);

		$result = $this->creator->createPasswordConfirmField($value);

		static::assertSame($expectedField, $result);
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCreatePasswordTokenField(): void
	{
		$value = 'sample_token';
		$expectedField = $this->createMock(FieldInterface::class);

		$this->formBuilderMock->expects($this->once())->method('createField')
			->with([
				'type' => FieldType::HIDDEN,
				'id' => Parameters::PARAMETER_PASSWORD_TOKEN,
				'name' => Parameters::PARAMETER_PASSWORD_TOKEN,
				'value' => $value,
			])
			->willReturn($expectedField);

		$result = $this->creator->createPasswordTokenField($value);

		static::assertSame($expectedField, $result);
	}

}
