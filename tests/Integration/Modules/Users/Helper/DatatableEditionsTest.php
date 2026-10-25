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

namespace Tests\Integration\Modules\Users\Helper;

use App\Framework\Config\Config;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Utils\Datatable\BuildService;
use App\Framework\Utils\Datatable\PrepareService;
use App\Framework\Utils\FormParameters\BaseFilterParametersInterface;
use App\Framework\Utils\FormParameters\ScalarType;
use App\Framework\Utils\Html\FormBuilder;
use App\Modules\Users\Helper\Datatable\DatatableBuilder;
use App\Modules\Users\Helper\Datatable\DatatablePreparer;
use App\Modules\Users\Helper\Datatable\Parameters;
use App\Modules\Users\Services\AclValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;
use Tests\Support\HtmlFields;

/**
 * The list of the users in the editions Core and Enterprise: a company, more columns and a link on the name. The
 * database of the tests is the one of the edge edition, so the helpers are built with an ACL validator of the edition
 * and only the helpers are tested, not the list.
 */
#[Group('acl')]
class DatatableEditionsTest extends ContainerTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function editions(): array
	{
		return ['core' => [Config::PLATFORM_EDITION_CORE], 'enterprise' => [Config::PLATFORM_EDITION_ENTERPRISE]];
	}

	private function aclOf(string $edition): AclValidator
	{
		$config = $this->createStub(Config::class);
		$config->method('getEdition')->willReturn($edition);
		$acl = $this->createStub(AclValidator::class);
		$acl->method('getConfig')->willReturn($config);
		$acl->method('isModuleAdmin')->willReturn(true);
		$acl->method('isSimpleAdmin')->willReturn(true);

		return $acl;
	}

	private function builder(string $edition): DatatableBuilder
	{
		return new DatatableBuilder($this->container->get(BuildService::class), $this->container->get(Parameters::class), $this->aclOf($edition), $this->container->get(Translator::class));
	}

	private function preparer(string $edition): DatatablePreparer
	{
		return new DatatablePreparer($this->container->get(PrepareService::class), $this->aclOf($edition), $this->container->get(Parameters::class), $this->container->get(Translator::class));
	}

	#[DataProvider('editions')]
	public function testTheListHasTheNameOfTheUserAndHisCompany(string $edition): void
	{
		$this->builder($edition)->createTableFields();

		$headers = array_map(static fn($field): string => $field->getName(), $this->container->get(BuildService::class)->getDatatableFields());
		static::assertSame(['username', 'created_at', 'status', 'firstname', 'surname', 'company_name'], $headers);
		static::assertSame(['username', 'created_at'], array_map(
			static fn($field): string => $field->getName(),
			array_values(array_filter($this->container->get(BuildService::class)->getDatatableFields(), static fn($field): bool => $field->isSortable()))
		));
	}

	#[DataProvider('editions')]
	public function testTheFilterOffersTheCompanyForTheAdmin(string $edition): void
	{
		$builder = $this->builder($edition);
		$builder->configureParameters(1);
		$builder->determineParameters([]);
		$builder->collectFormElements();

		$form = $builder->getDatatableStructure()['form'];
		static::assertSame(['username', 'email', BaseFilterParametersInterface::PARAMETER_COMPANY_ID, 'status'], array_keys($form));
		static::assertArrayHasKey(BaseFilterParametersInterface::PARAMETER_COMPANY_ID, HtmlFields::attributesOf($this->container->get(FormBuilder::class), $form));
	}

	/**
	 * @return list<array<string,mixed>> the rows of the list as the service gives them (the columns of the filter definition)
	 */
	private function rows(bool $withColumnsOfTheEdition = false): array
	{
		return [($withColumnsOfTheEdition ? ['firstname' => '', 'surname' => '', 'company_name' => ''] : []) + ['UID' => 3, 'company_id' => 1, 'username' => 'bernd', 'email' => 'bernd@example.com', 'status' => 3, 'created_at' => '2026-01-01 10:00:00', 'last_access' => null]];
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	private function body(string $edition, bool $withColumnsOfTheEdition = true): array
	{
		$this->builder($edition)->createTableFields();

		return $this->preparer($edition)->prepareTableBody($this->rows($withColumnsOfTheEdition), $this->container->get(BuildService::class)->getDatatableFields(), 1);
	}

	#[DataProvider('editions')]
	public function testTheNameIsALinkInTheEditions(string $edition): void
	{
		$link = $this->body($edition)[0]['elements_result_element'][0]['is_link'];

		static::assertSame(['bernd', 'username_3'], [$link['CONTROL_ELEMENT_VALUE_NAME'], $link['CONTROL_ELEMENT_VALUE_ID']]);
	}

	#[DataProvider('editions')]
	public function testTheNameLinksToTheFormOfTheUser(string $edition): void
	{
		$link = $this->body($edition)[0]['elements_result_element'][0]['is_link'];

		static::assertStringEndsWith('users/edit/3', $link['CONTROL_ELEMENT_VALUE_LINK']);
	}

	#[DataProvider('editions')]
	public function testTheColumnsOfTheEditionAreInTheRows(string $edition): void
	{
		$cells = $this->body($edition, false)[0]['elements_result_element'];

		static::assertSame(['username', 'created_at', 'status', 'firstname', 'surname', 'company_name'], array_column($cells, 'CONTROL_NAME_BODY'));
		// the query does not deliver them yet: empty cells
		static::assertSame(['', '', ''], array_map(static fn(array $cell): string => $cell['is_text']['CONTROL_ELEMENT_VALUE_TEXT'], array_slice($cells, 3)));
	}

	public function testTheFilterOffersTheNamesWhenTheParametersExist(): void
	{
		$parameters = $this->container->get(Parameters::class);
		foreach ([Parameters::PARAMETER_FIRSTNAME, Parameters::PARAMETER_SURNAME, Parameters::PARAMETER_COMPANY_NAME] as $name)
			$parameters->addParameter($name, ScalarType::STRING, '');
		$builder = $this->builder(Config::PLATFORM_EDITION_CORE);
		$builder->determineParameters(['firstname' => 'Anna']);
		$builder->collectFormElements();

		$form = $builder->getDatatableStructure()['form'];

		static::assertSame(['username', 'email', 'firstname', 'surname', 'company_name', 'status'], array_keys($form));
		static::assertSame('Anna', HtmlFields::attributesOf($this->container->get(FormBuilder::class), $form)['firstname']['value']);
	}
}
