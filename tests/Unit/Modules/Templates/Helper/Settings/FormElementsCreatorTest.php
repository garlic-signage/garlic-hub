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

namespace Tests\Unit\Modules\Templates\Helper\Settings;

use App\Framework\Localization\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\Html\FieldInterface;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\FormBuilder;
use App\Modules\Templates\Helper\Settings\FormElementsCreator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FormElementsCreatorTest extends TestCase
{
	private FormBuilder&MockObject $formBuilderMock;
	private FieldInterface $field;
	private FormElementsCreator $creator;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->formBuilderMock = $this->createMock(FormBuilder::class);
		$this->field           = $this->createStub(FieldInterface::class);
		$translatorStub        = $this->createStub(Translator::class);
		$translatorStub->method('translate')
			->willReturnCallback(fn(string $key, string $module): string => $module . '.' . $key);
		$translatorStub->method('translateArrayForOptions')->willReturn(['public' => 'Public', 'private' => 'Private']);

		$this->creator = new FormElementsCreator($this->formBuilderMock, $translatorStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCreateNameField(): void
	{
		$this->expectField([
			'type'  => FieldType::TEXT,
			'id'    => 'name',
			'name'  => 'name',
			'title' => 'templates.name',
			'label' => 'templates.name',
			'value' => 'My Template'
		]);

		static::assertSame($this->field, $this->creator->createNameField('My Template'));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCreateUIDField(): void
	{
		$this->expectField([
			'type'          => FieldType::AUTOCOMPLETE,
			'id'            => 'UID',
			'name'          => 'UID',
			'title'         => 'main.owner',
			'label'         => 'main.owner',
			'value'         => 8,
			'data-label'    => 'other',
			'default_value' => 5
		]);

		static::assertSame($this->field, $this->creator->createUIDField(8, 'other', 5));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCreateVisibilityField(): void
	{
		$this->expectField([
			'type'          => FieldType::DROPDOWN,
			'id'            => 'visibility',
			'name'          => 'visibility',
			'title'         => 'main.visibility',
			'label'         => 'main.visibility',
			'value'         => 'private',
			'options'       => ['public' => 'Public', 'private' => 'Private'],
			'default_value' => 'public'
		]);

		static::assertSame($this->field, $this->creator->createVisibilityField('private'));
	}

	/**
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCreateHiddenTypeField(): void
	{
		$this->expectField(['type' => FieldType::HIDDEN, 'id' => 'type', 'name' => 'type', 'value' => 'canvas']);

		static::assertSame($this->field, $this->creator->createHiddenTypeField('canvas'));
	}

	/**
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCreateHiddenTemplateIdField(): void
	{
		$this->expectField(['type' => FieldType::HIDDEN, 'id' => 'template_id', 'name' => 'template_id', 'value' => 12]);

		static::assertSame($this->field, $this->creator->createHiddenTemplateIdField(12));
	}

	/**
	 * @param array<string,mixed> $attributes
	 */
	private function expectField(array $attributes): void
	{
		$this->formBuilderMock->expects($this->once())->method('createField')->with($attributes)->willReturn($this->field);
	}
}
