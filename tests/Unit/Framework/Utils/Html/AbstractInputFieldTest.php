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

namespace Tests\Unit\Framework\Utils\Html;

use App\Framework\Utils\Html\AbstractInputField;
use App\Framework\Utils\Html\AutocompleteField;
use App\Framework\Utils\Html\CheckboxField;
use App\Framework\Utils\Html\ClipboardTextField;
use App\Framework\Utils\Html\DropdownField;
use App\Framework\Utils\Html\EmailField;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\HiddenField;
use App\Framework\Utils\Html\NumberField;
use App\Framework\Utils\Html\PasswordField;
use App\Framework\Utils\Html\TextField;
use App\Framework\Utils\Html\UrlField;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The behaviour every field has. The field classes only differ in what they add, so the tests run for all of them.
 */
class AbstractInputFieldTest extends TestCase
{
	/**
	 * @return array<string, array{class-string<AbstractInputField>, array<string,mixed>}> class and the attributes it needs besides id and type
	 */
	public static function fieldProvider(): array
	{
		return [
			'text'           => [TextField::class, []],
			'email'          => [EmailField::class, []],
			'hidden'         => [HiddenField::class, []],
			'password'       => [PasswordField::class, []],
			'number'         => [NumberField::class, []],
			'url'            => [UrlField::class, []],
			'clipboard text' => [ClipboardTextField::class, []],
			'checkbox'       => [CheckboxField::class, []],
			'dropdown'       => [DropdownField::class, ['options' => []]],
			'autocomplete'   => [AutocompleteField::class, ['data-label' => 'label']],
		];
	}

	/**
	 * @param class-string<AbstractInputField> $class
	 * @param array<string,mixed> $needs
	 */
	#[Group('units')]
	#[DataProvider('fieldProvider')]
	public function testConstructorWithAllAttributes(string $class, array $needs): void
	{
		$field = new $class([
			'id'            => 'custom_id',
			'type'          => FieldType::TEXT,
			'name'          => 'custom_name',
			'title'         => 'Custom Title',
			'label'         => 'Custom Label',
			'value'         => 'Custom Value',
			'default_value' => 'Default Value',
			'attributes'    => ['attr1' => 'value1'],
			'rules'         => ['required' => true],
		] + $needs);

		static::assertSame('custom_id', $field->getId());
		static::assertSame(FieldType::TEXT, $field->getType());
		static::assertSame('custom_name', $field->getName());
		static::assertSame('Custom Title', $field->getTitle());
		static::assertSame('Custom Label', $field->getLabel());
		static::assertSame('Custom Value', $field->getValue());
		static::assertSame(['attr1' => 'value1'], $field->getAttributes());
		static::assertSame(['required' => true], $field->getValidationRules());
	}

	/**
	 * @param class-string<AbstractInputField> $class
	 * @param array<string,mixed> $needs
	 */
	#[Group('units')]
	#[DataProvider('fieldProvider')]
	public function testOnlyIdAndTypeAreNeeded(string $class, array $needs): void
	{
		$field = new $class(['id' => 'status', 'type' => FieldType::TEXT] + $needs);

		static::assertSame('status', $field->getName(), 'The name falls back to the id.');
		static::assertSame('', $field->getTitle());
		static::assertSame('', $field->getLabel());
		static::assertSame('', $field->getValue());
		static::assertSame([], $field->getAttributes());
		static::assertSame([], $field->getValidationRules());
	}

	#[Group('units')]
	public function testTheValueIsKeptAsText(): void
	{
		$field = new TextField(['id' => 'a', 'type' => FieldType::TEXT, 'value' => 5]);

		static::assertSame('5', $field->getValue());
	}

	#[Group('units')]
	public function testAnEmptyValueGivesTheDefault(): void
	{
		static::assertSame('guest', new TextField(['id' => 'a', 'type' => FieldType::TEXT, 'default_value' => 'guest'])->getValue());
		static::assertSame('guest', new TextField(['id' => 'a', 'type' => FieldType::TEXT, 'value' => '', 'default_value' => 'guest'])->getValue());
		static::assertSame(7, new TextField(['id' => 'a', 'type' => FieldType::TEXT, 'default_value' => 7])->getValue());
	}

	/**
	 * Zero is a value, not an empty one: a number field with 0 or a dropdown with the option 0 must show it.
	 */
	#[Group('units')]
	#[DataProvider('zeroProvider')]
	public function testZeroIsAValueAndNotReplacedByTheDefault(string|int $zero): void
	{
		$field = new NumberField(['id' => 'a', 'type' => FieldType::NUMBER, 'value' => $zero, 'default_value' => 5]);

		static::assertSame((string) $zero, (string) $field->getValue());
	}

	#[Group('units')]
	#[DataProvider('zeroProvider')]
	public function testZeroSetLaterIsAValueToo(string|int $zero): void
	{
		$field = new TextField(['id' => 'a', 'type' => FieldType::TEXT, 'default_value' => 'x']);

		$field->setValue($zero);

		static::assertSame($zero, $field->getValue());
	}

	/**
	 * @return array<string, array{string|int}>
	 */
	public static function zeroProvider(): array
	{
		return ['text zero' => ['0'], 'integer zero' => [0]];
	}

	#[Group('units')]
	public function testSetValue(): void
	{
		$field = new TextField(['id' => 'password', 'type' => FieldType::TEXT]);

		static::assertSame($field, $field->setValue('secret123'));
		static::assertSame('secret123', $field->getValue());
	}

	#[Group('units')]
	public function testSettersForRulesAndAttributes(): void
	{
		$field = new TextField(['id' => 'phone', 'type' => FieldType::TEXT]);

		$result = $field->setAttribute('class', 'phone-input')
			->setAttribute('size', 12)
			->setValidationRules(['required' => true])
			->addValidationRule('url')
			->addValidationRule('minlength', false);

		static::assertSame($field, $result);
		static::assertSame(['class' => 'phone-input', 'size' => 12], $field->getAttributes());
		static::assertSame(['required' => true, 'url' => true, 'minlength' => false], $field->getValidationRules());
	}

	#[Group('units')]
	public function testSetValidationRulesReplacesTheRules(): void
	{
		$field = new TextField(['id' => 'a', 'type' => FieldType::TEXT, 'rules' => ['required' => true]]);

		$field->setValidationRules(['maxlength' => 5]);

		static::assertSame(['maxlength' => 5], $field->getValidationRules());
	}
}
