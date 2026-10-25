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

namespace Tests\Unit\Modules\Templates\Helper\Datatable;

use App\Framework\Config\Config;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Security\Crypt;
use App\Framework\Security\CsrfToken;
use App\Framework\Utils\Datatable\BuildService;
use App\Framework\Utils\Datatable\Paginator\Builder as PaginatorBuilder;
use App\Framework\Utils\Datatable\Results\Builder as ResultsBuilder;
use App\Framework\Utils\Html\DropdownField;
use App\Framework\Utils\Html\FieldsFactory;
use App\Framework\Utils\Html\FieldsRenderFactory;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\FormBuilder;
use App\Framework\Validation\Sanitizer;
use App\Modules\Templates\Helper\Datatable\DatatableBuilder;
use App\Modules\Templates\Helper\Datatable\Parameters;
use App\Modules\Templates\Services\AclValidator;
use Defuse\Crypto\Key;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Tests\Support\MemorySession;

/**
 * The builder of the template list with the real parameters and the real services of the datatable.
 */
class DatatableBuilderTest extends TestCase
{
	private Parameters $parameters;
	private AclValidator&Stub $aclValidatorStub;
	private BuildService $buildService;
	private DatatableBuilder $builder;

	/**
	 * @throws \Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(static fn(string $key, string $module): string => $module . '.' . $key);
		$translator->method('translateArrayForOptions')->willReturn(['canvas' => 'Canvas']);
		$session = new MemorySession();

		$this->parameters       = new Parameters(new Sanitizer(), $session);
		$this->aclValidatorStub = $this->createStub(AclValidator::class);
		$this->buildService     = new BuildService(
			new FormBuilder(new FieldsFactory(), new FieldsRenderFactory(), new CsrfToken(new Crypt(Key::createNewRandomKey()), $session)),
			new PaginatorBuilder(),
			new ResultsBuilder()
		);
		$this->builder = new DatatableBuilder($this->buildService, $this->parameters, $this->aclValidatorStub, $translator);
	}

	private function edition(string $edition): void
	{
		$config = $this->createStub(Config::class);
		$config->method('getEdition')->willReturn($edition);
		$this->aclValidatorStub->method('getConfig')->willReturn($config);
	}

	/**
	 * The form shows the values of the request, which are parsed before.
	 */
	private function parseRequest(): void
	{
		$this->parameters->setUserInputs([]);
		$this->parameters->parseInputFilterAllUsers(['name']);
	}

	#[Group('units')]
	public function testTheEdgeEditionAddsNoParameters(): void
	{
		$this->edition(Config::PLATFORM_EDITION_EDGE);
		$this->aclValidatorStub->method('isModuleAdmin')->willReturn(true);

		$this->builder->configureParameters(1);

		static::assertFalse($this->parameters->hasParameter('UID'));
		static::assertFalse($this->parameters->hasParameter('company_id'));
	}

	#[Group('units')]
	public function testTheAdminOfAnotherEditionFiltersByOwnerAndCompany(): void
	{
		$this->edition(Config::PLATFORM_EDITION_CORE);
		$this->aclValidatorStub->method('isModuleAdmin')->willReturnMap([[1, true]]);
		$this->aclValidatorStub->method('isSubAdmin')->willReturn(false);

		$this->builder->configureParameters(1);

		static::assertTrue($this->parameters->hasParameter('UID'));
		static::assertTrue($this->parameters->hasParameter('company_id'));
	}

	#[Group('units')]
	public function testTheSubAdminFiltersByOwnerAndCompany(): void
	{
		$this->edition(Config::PLATFORM_EDITION_CORE);
		$this->aclValidatorStub->method('isModuleAdmin')->willReturn(false);
		$this->aclValidatorStub->method('isSubAdmin')->willReturnMap([[4, true]]);

		$this->builder->configureParameters(4);

		static::assertTrue($this->parameters->hasParameter('UID'));
		static::assertTrue($this->parameters->hasParameter('company_id'));
	}

	#[Group('units')]
	public function testAUserFiltersNotByOwner(): void
	{
		$this->edition(Config::PLATFORM_EDITION_CORE);
		$this->aclValidatorStub->method('isModuleAdmin')->willReturn(false);
		$this->aclValidatorStub->method('isSubAdmin')->willReturn(false);

		$this->builder->configureParameters(9);

		static::assertFalse($this->parameters->hasParameter('UID'));
	}

	#[Group('units')]
	public function testTheQueryIsParsedAndOnlyTheNameCanBeSorted(): void
	{
		$this->builder->determineParameters(['name' => 'Lobby', 'sort_column' => 'type', 'elements_per_page' => '20']);

		static::assertSame('Lobby', $this->parameters->getValueOfParameter('name'));
		static::assertSame(['name'], array_map(static fn($field): string => $field->getName(), array_values(array_filter($this->buildService->getDatatableFields(), static fn($field): bool => $field->isSortable()))));
		static::assertSame('template_id', $this->parameters->getValueOfParameter('sort_column'));
		static::assertSame(20, $this->parameters->getValueOfParameter('elements_per_page'));
	}

	#[Group('units')]
	public function testTheTitle(): void
	{
		$this->builder->buildTitle();

		static::assertSame('templates.overview', $this->builder->getDatatableStructure()['title']);
	}

	#[Group('units')]
	public function testTheFormHasTheNameTheTypeAndForAnAdminTheOwner(): void
	{
		$this->parameters->addOwner();
		$this->parseRequest();

		$this->builder->collectFormElements();

		$form = $this->builder->getDatatableStructure()['form'];
		static::assertSame(['name', 'type', 'UID'], array_keys($form));
		static::assertSame([FieldType::TEXT, FieldType::DROPDOWN, FieldType::AUTOCOMPLETE], array_map(static fn($field): FieldType => $field->getType(), array_values($form)));
		static::assertSame('templates.name', $form['name']->getLabel());
		static::assertInstanceOf(DropdownField::class, $form['type']);
		static::assertSame(['canvas' => 'Canvas'], $form['type']->getOptions());
	}

	#[Group('units')]
	public function testTheFormOfAUserHasNoOwner(): void
	{
		$this->parseRequest();

		$this->builder->collectFormElements();

		static::assertSame(['name', 'type'], array_keys($this->builder->getDatatableStructure()['form']));
	}

	#[Group('units')]
	public function testTheTableHasTheNameTheTypeAndHowOftenItIsUsed(): void
	{
		$this->builder->createTableFields();

		static::assertSame(['name', 'type', 'used'], array_map(static fn($field): string => $field->getName(), $this->buildService->getDatatableFields()));
		static::assertSame([true, false, false], array_map(static fn($field): bool => $field->isSortable(), $this->buildService->getDatatableFields()));
	}

	#[Group('units')]
	public function testTheTableOfAnAdminHasTheOwner(): void
	{
		$this->parameters->addOwner();

		$this->builder->createTableFields();

		static::assertSame(['name', 'type', 'UID', 'used'], array_map(static fn($field): string => $field->getName(), $this->buildService->getDatatableFields()));
		static::assertCount(4, $this->builder->getDatatableStructure()['header']);
	}
}
