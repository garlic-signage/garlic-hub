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

namespace Tests\Unit\Modules\Templates\Helper\Datatable;

use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Datatable\BuildService;
use App\Framework\Utils\Datatable\Results\HeaderField;
use App\Framework\Utils\FormParameters\BaseParameters;
use App\Framework\Utils\Html\FieldInterface;
use App\Framework\Utils\Html\FieldType;
use App\Modules\Templates\Helper\Datatable\DatatableBuilder;
use App\Modules\Templates\Helper\Datatable\Parameters;
use App\Modules\Templates\Services\AclValidator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class DatatableBuilderTest extends TestCase
{
	private BuildService&MockObject $buildServiceMock;
	private Parameters&Stub $parametersStub;
	private DatatableBuilder $builder;

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->buildServiceMock = $this->createMock(BuildService::class);
		$this->parametersStub   = $this->createStub(Parameters::class);
		$translatorStub         = $this->createStub(Translator::class);
		$translatorStub->method('translate')
			->willReturnCallback(fn(string $key, string $module): string => $module . '.' . $key);
		$translatorStub->method('translateArrayForOptions')->willReturn(['canvas' => 'Canvas']);

		$this->builder = new DatatableBuilder($this->buildServiceMock, $this->parametersStub, $this->createStub(AclValidator::class));
		$this->builder->setTranslator($translatorStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildTitle(): void
	{
		$this->buildServiceMock->expects($this->never())->method($this->anything());

		$this->builder->buildTitle();

		static::assertSame('templates.overview', $this->builder->getDatatableStructure()['title']);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testCollectFormElementsWithoutOwner(): void
	{
		$this->mockParameters(false);
		$attributes = $this->captureFormFields(2);

		$this->builder->collectFormElements();

		static::assertSame([Parameters::PARAMETER_TEMPLATE_NAME, Parameters::PARAMETER_TYPE], array_keys($this->builder->getDatatableStructure()['form']));
		static::assertSame([
			'type' => FieldType::TEXT,
			'id' => 'name',
			'name' => 'name',
			'title' => 'templates.name',
			'label' => 'templates.name',
			'value' => 'my'
		], $attributes[0]);
		static::assertSame([
			'type' => FieldType::DROPDOWN,
			'id' => 'type',
			'name' => 'type',
			'title' => 'templates.type',
			'label' => 'templates.type',
			'value' => 'canvas',
			'options' => ['canvas' => 'Canvas']
		], $attributes[1]);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testCollectFormElementsWithOwner(): void
	{
		$this->mockParameters(true);
		$attributes = $this->captureFormFields(3);

		$this->builder->collectFormElements();

		static::assertSame(['name', 'type', 'UID'], array_keys($this->builder->getDatatableStructure()['form']));
		$owner = $attributes->getArrayCopy()[2];
		static::assertSame(FieldType::AUTOCOMPLETE, $owner['type']);
		static::assertSame('main.owner', $owner['label']);
		static::assertSame(8, $owner['value']);
	}

	#[Group('units')]
	public function testCreateTableFieldsWithoutOwner(): void
	{
		$this->mockParameters(false);
		$fields = $this->captureTableFields();

		$this->builder->createTableFields();

		static::assertSame([['name', true], ['type', false], ['used', false]], $fields->getArrayCopy());
	}

	#[Group('units')]
	public function testCreateTableFieldsWithOwner(): void
	{
		$this->mockParameters(true);
		$fields = $this->captureTableFields();

		$result = $this->builder->createTableFields();

		static::assertSame($this->builder, $result);
		static::assertSame([['name', true], ['type', false], ['UID', true], ['used', false]], $fields->getArrayCopy());
		static::assertCount(1, $this->builder->getDatatableStructure()['header']);
	}

	private function mockParameters(bool $hasOwner): void
	{
		$this->parametersStub->method('hasParameter')->willReturnMap([[BaseParameters::PARAMETER_UID, $hasOwner]]);
		$this->parametersStub->method('getValueOfParameter')->willReturnMap([
			[Parameters::PARAMETER_TEMPLATE_NAME, 'my'],
			[Parameters::PARAMETER_TYPE, 'canvas'],
			[BaseParameters::PARAMETER_UID, 8],
		]);
	}

	/**
	 * @return \ArrayObject<int, array<string,mixed>>
	 */
	private function captureFormFields(int $count): \ArrayObject
	{
		$attributes = new \ArrayObject();
		$this->buildServiceMock->expects($this->exactly($count))->method('buildFormField')
			->willReturnCallback(function (array $field) use ($attributes): FieldInterface {
				$attributes->append($field);
				return $this->createStub(FieldInterface::class);
			});

		return $attributes;
	}

	/**
	 * @return \ArrayObject<int, array{string, bool}>
	 */
	private function captureTableFields(): \ArrayObject
	{
		$fields = new \ArrayObject();
		$this->buildServiceMock->method('createDatatableField')
			->willReturnCallback(function (string $name, bool $sortable) use ($fields): void {
				$fields->append([$name, $sortable]);
			});
		$this->buildServiceMock->expects($this->once())->method('getDatatableFields')
			->willReturn([$this->createStub(HeaderField::class)]);

		return $fields;
	}
}
