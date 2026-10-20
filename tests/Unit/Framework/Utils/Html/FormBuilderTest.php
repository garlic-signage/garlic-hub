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

namespace Tests\Unit\Framework\Utils\Html;

use App\Framework\Core\CsrfToken;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\Html\CsrfTokenField;
use App\Framework\Utils\Html\EmailField;
use App\Framework\Utils\Html\FieldInterface;
use App\Framework\Utils\Html\FieldsFactory;
use App\Framework\Utils\Html\FieldsRenderFactory;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\FormBuilder;
use App\Framework\Utils\Html\HiddenField;
use App\Framework\Utils\Html\PasswordField;
use App\Framework\Utils\Html\TextField;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FormBuilderTest extends TestCase
{
	private FieldsFactory&MockObject       $fieldsFactoryMock;
	private FieldsRenderFactory&MockObject $fieldsRenderFactoryMock;
	private FormBuilder         $formBuilder;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->fieldsFactoryMock       = $this->createMock(FieldsFactory::class);
		$this->fieldsRenderFactoryMock = $this->createMock(FieldsRenderFactory::class);
		$csrfTokenStub = $this->createStub(CsrfToken::class);
		$this->formBuilder             = new FormBuilder(
			$this->fieldsFactoryMock,
			$this->fieldsRenderFactoryMock,
			$csrfTokenStub
		);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testPrepareFormWithMixedFields(): void
	{
		$this->fieldsFactoryMock->expects($this->never())->method($this->anything());
		$textFieldStub = $this->createStub(TextField::class);
		$textFieldStub->method('getType')->willReturn(FieldType::TEXT);
		$textFieldStub->method('getId')->willReturn('text_field_1');
		$textFieldStub->method('getLabel')->willReturn('Text Field');

		$csrfFieldStub = $this->createStub(CsrfTokenField::class);
		$csrfFieldStub->method('getType')->willReturn(FieldType::CSRF);

		$this->fieldsRenderFactoryMock
			->expects($this->atLeastOnce())->method('getRenderer')
			->willReturnMap([
				[$textFieldStub, '<input type="text" id="text_field_1" />'],
				[$csrfFieldStub, '<input type="hidden" name="csrf_token" />']
			]);

		// @phpstan-ignore-next-line
		$result = $this->formBuilder->prepareForm([$textFieldStub, $csrfFieldStub]);

		static::assertCount(1, $result['visible']);
		static::assertSame(
			[
				'HTML_ELEMENT_ID' => 'text_field_1',
				'LANG_ELEMENT_LABEL' => 'Text Field',
				'LANG_ELEMENT_NAME' => '',
				'ELEMENT_MUST_FIELD' => '',
				'HTML_ELEMENT' => '<input type="text" id="text_field_1" />',
			],
			$result['visible'][0]
		);

		static::assertCount(1, $result['hidden']);
		static::assertSame(
			[
				'HIDDEN_HTML_ELEMENT' => '<input type="hidden" name="csrf_token" />',
			],
			$result['hidden'][0]
		);
	}

	#[Group('units')]
	public function testPrepareFormWithEmptyFields(): void
	{
		$this->fieldsFactoryMock->expects($this->never())->method($this->anything());
		$this->fieldsRenderFactoryMock->expects($this->never())->method($this->anything());
		$result = $this->formBuilder->prepareForm([]);

		static::assertEmpty($result['visible']);
		static::assertEmpty($result['hidden']);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testPrepareFormWithOnlyHiddenFields(): void
	{
		$this->fieldsFactoryMock->expects($this->never())->method($this->anything());
		$hiddenFieldStub = $this->createStub(HiddenField::class);
		$hiddenFieldStub->method('getType')->willReturn(FieldType::HIDDEN);
		$this->fieldsRenderFactoryMock
			->method('getRenderer')
			->with($hiddenFieldStub)
			->willReturn('<input type="hidden" id="hidden_field_1" />');

		// @phpstan-ignore-next-line
		$result = $this->formBuilder->prepareForm([$hiddenFieldStub]);

		static::assertEmpty($result['visible']);
		static::assertCount(1, $result['hidden']);
		static::assertSame(
			[
				'HIDDEN_HTML_ELEMENT' => '<input type="hidden" id="hidden_field_1" />',
			],
			$result['hidden'][0]
		);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testPrepareFormWithOnlyVisibleFields(): void
	{
		$this->fieldsFactoryMock->expects($this->never())->method($this->anything());
		$visibleFieldStub = $this->createStub(TextField::class);
		$visibleFieldStub->method('getType')->willReturn(FieldType::TEXT);
		$visibleFieldStub->method('getId')->willReturn('visible_field_1');
		$visibleFieldStub->method('getLabel')->willReturn('Visible Field');
		$this->fieldsRenderFactoryMock
			->method('getRenderer')
			->with($visibleFieldStub)
			->willReturn('<input type="text" id="visible_field_1" />');

		// @phpstan-ignore-next-line
		$result = $this->formBuilder->prepareForm([$visibleFieldStub]);

		static::assertEmpty($result['hidden']);
		static::assertCount(1, $result['visible']);
		static::assertSame(
			[
				'HTML_ELEMENT_ID' => 'visible_field_1',
				'LANG_ELEMENT_LABEL' => 'Visible Field',
				'LANG_ELEMENT_NAME' => '',
				'ELEMENT_MUST_FIELD' => '',
				'HTML_ELEMENT' => '<input type="text" id="visible_field_1" />',
			],
			$result['visible'][0]
		);
	}


	/**
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCreateTextField(): void
	{
		$this->fieldsRenderFactoryMock->expects($this->never())->method($this->anything());
		$fieldStub = $this->createStub(TextField::class);

		$this->fieldsFactoryMock
			->expects($this->once())
			->method('createTextField')
			->with(['type' => FieldType::TEXT])
			->willReturn($fieldStub);

		$field = $this->formBuilder->createField(['type' => FieldType::TEXT]);

		static::assertSame($fieldStub, $field);
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCreatePasswordField(): void
	{
		$this->fieldsRenderFactoryMock->expects($this->never())->method($this->anything());
		$fieldStub = $this->createStub(PasswordField::class);

		$this->fieldsFactoryMock
			->expects($this->once())
			->method('createPasswordField')
			->with(['type' => FieldType::PASSWORD])
			->willReturn($fieldStub);

		$field = $this->formBuilder->createField(['type' => FieldType::PASSWORD]);

		static::assertSame($fieldStub, $field);
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCreateEmailField(): void
	{
		$this->fieldsRenderFactoryMock->expects($this->never())->method($this->anything());
		$fieldStub = $this->createStub(EmailField::class);

		$this->fieldsFactoryMock
			->expects($this->once())
			->method('createEmailField')
			->with(['type' => FieldType::EMAIL])
			->willReturn($fieldStub);

		$field = $this->formBuilder->createField(['type' => FieldType::EMAIL]);

		static::assertSame($fieldStub, $field);
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCreateCsrfTokenField(): void
	{
		$this->fieldsRenderFactoryMock->expects($this->never())->method($this->anything());
		$fieldStub = $this->createStub(CsrfTokenField::class);

		$this->fieldsFactoryMock
			->expects($this->once())
			->method('createCsrfTokenField')
			->with(['type' => FieldType::CSRF])
			->willReturn($fieldStub);

		$field = $this->formBuilder->createField(['type' => FieldType::CSRF]);

		static::assertSame($fieldStub, $field);
	}

	#[Group('units')]
	public function testInvalidFieldTypeThrowsException(): void
	{
		$this->fieldsFactoryMock->expects($this->never())->method($this->anything());
		$this->fieldsRenderFactoryMock->expects($this->never())->method($this->anything());
		$this->expectException(FrameworkException::class);

		$this->formBuilder->createField(['type' => 'invalid']);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testRenderField(): void
	{
		$this->fieldsFactoryMock->expects($this->never())->method($this->anything());
		$fieldStub = $this->createStub(FieldInterface::class);

		$this->fieldsRenderFactoryMock
			->expects($this->once())
			->method('getRenderer')
			->with($fieldStub)
			->willReturn('<input type="text" />');

		$output = $this->formBuilder->renderField($fieldStub);

		static::assertSame('<input type="text" />', $output);
	}
}
