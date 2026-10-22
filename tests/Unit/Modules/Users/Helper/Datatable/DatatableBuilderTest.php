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

namespace Tests\Unit\Modules\Users\Helper\Datatable;

use App\Framework\Config\Config;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Datatable\BuildService;
use App\Framework\Utils\Datatable\Results\Builder;
use App\Framework\Utils\FormParameters\BaseFilterParametersInterface;
use App\Framework\Utils\Html\DropdownField;
use App\Framework\Utils\Html\EmailField;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\TextField;
use App\Modules\Users\Helper\Datatable\DatatableBuilder;
use App\Modules\Users\Helper\Datatable\Parameters;
use App\Modules\Users\Services\AclValidator;
use App\Modules\Users\UserStatus;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class DatatableBuilderTest extends TestCase
{
	private BuildService&Stub $buildServiceStub;
	private Parameters&MockObject $parametersMock;
	private AclValidator&MockObject $aclValidatorMock;
	private Translator&Stub $translatorStub;
	private DatatableBuilder $builder;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->buildServiceStub = $this->createStub(BuildService::class);
		$this->parametersMock = $this->createMock(Parameters::class);
		$this->aclValidatorMock = $this->createMock(AclValidator::class);

		$this->translatorStub   = $this->createStub(Translator::class);

		$this->builder = new DatatableBuilder($this->buildServiceStub, $this->parametersMock, $this->aclValidatorMock, $this->translatorStub);

	}

	/**
	 * @throws Exception
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testConfigureParametersDoesNothingForEdgeEdition(): void
	{
		$this->aclValidatorMock
			->expects($this->atLeastOnce())->method('getConfig')
			->willReturn($this->createConfiguredStub(Config::class, ['getEdition' => Config::PLATFORM_EDITION_EDGE]));

		$this->parametersMock->expects($this->never())->method('addOwner');
		$this->parametersMock->expects($this->never())->method('addCompany');

		$this->builder->configureParameters(123);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testConfigureParametersCallsAddOwnerAndCompanyForModuleAdmin(): void
	{
		$this->aclValidatorMock
			->method('getConfig')
			->willReturn($this->createConfiguredStub(Config::class, ['getEdition' => 'some_other_edition']));

		$this->aclValidatorMock
			->method('isModuleAdmin')
			->with(123)
			->willReturn(true);

		$this->aclValidatorMock
			->method('isSubAdmin')
			->willReturn(false);

		$this->parametersMock->expects($this->once())->method('addOwner');
		$this->parametersMock->expects($this->once())->method('addCompany');

		$this->builder->configureParameters(123);
	}

	/**
	 * @throws Exception
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testConfigureParametersCallsAddOwnerAndCompanyForSubAdmin(): void
	{
		$this->aclValidatorMock
			->method('getConfig')
			->willReturn($this->createConfiguredStub(Config::class, ['getEdition' => 'some_other_edition']));

		$this->aclValidatorMock
			->method('isModuleAdmin')
			->willReturn(false);

		$this->aclValidatorMock
			->method('isSubAdmin')
			->with(456)
			->willReturn(true);

		$this->parametersMock->expects($this->once())->method('addOwner');
		$this->parametersMock->expects($this->once())->method('addCompany');

		$this->builder->configureParameters(456);
	}

    /**
     * @throws ModuleException
     */
    #[Group('units')]
	public function testDetermineParametersSetsUserInputsAndParsesFilterAllUsers(): void
	{
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
		$queryParams = ['test_key' => 'test_value'];

		$this->parametersMock->expects($this->once())
			->method('setUserInputs')
			->with($queryParams);

		$this->parametersMock->expects($this->once())
			->method('parseInputFilterAllUsers');

		$this->builder->determineParameters($queryParams);
	}

	/**
	 * @throws Exception
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildTitleSetsCorrectTitle(): void
	{
		$this->parametersMock->expects($this->never())->method($this->anything());
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
		$expectedTitle = 'Translated Title';

		$this->translatorStub->method('translate')
			->willReturnMap([['management', 'users', [], $expectedTitle]]);

		$this->builder->buildTitle();

		static::assertSame($expectedTitle, $this->builder->getDatatableStructure()['title']);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testCollectFormElements(): void
	{
		$this->aclValidatorMock->expects($this->never())->method($this->anything());

		$this->parametersMock->expects($this->atLeastOnce())->method('hasParameter')
			->willReturnMap([
				[Parameters::PARAMETER_FIRSTNAME, true],
				[Parameters::PARAMETER_SURNAME, true],
				[Parameters::PARAMETER_COMPANY_NAME, true],
				[BaseFilterParametersInterface::PARAMETER_COMPANY_ID, true],
				[Parameters::PARAMETER_STATUS, true]
			]);


		$this->parametersMock->expects($this->atLeastOnce())->method('getValueOfParameter')
			->willReturnMap([
				[Parameters::PARAMETER_USERNAME, 'test_username'],
				[Parameters::PARAMETER_EMAIL, 'test_email@example.com'],
				[Parameters::PARAMETER_FIRSTNAME, 'John'],
				[Parameters::PARAMETER_SURNAME, 'Doe'],
				[Parameters::PARAMETER_COMPANY_NAME, 'Acme'],
				[BaseFilterParametersInterface::PARAMETER_COMPANY_ID, 56],
				[Parameters::PARAMETER_STATUS, UserStatus::REGISTERED->value],
			]);

		$status = ['deleted' => 'Deleted', 'banned' => 'Banned', 'inactive' => 'Inactive', 'active' => 'Active'];
		$this->translatorStub->method('translateArrayForOptions')
			->willReturn($status);

		$this->translatorStub->method('translate')->willReturnMap([
			[Parameters::PARAMETER_USERNAME, 'main', [], 'Username'],
			[Parameters::PARAMETER_EMAIL, 'users', [], 'Email'],
			[Parameters::PARAMETER_FIRSTNAME, 'users', [], 'First Name'],
			[Parameters::PARAMETER_SURNAME, 'users', [], 'Surname'],
			[Parameters::PARAMETER_COMPANY_NAME, 'users', [], 'Company name'],
			['belongs_company', 'main', [], 'Belongs company'],
			[Parameters::PARAMETER_STATUS, 'users', [], 'Status'],
		]);


		$userFieldStub        = $this->createStub(TextField::class);
		$emailFieldStub       = $this->createStub(EmailField::class);
		$firstNameFieldStub   = $this->createStub(TextField::class);
		$surNameFieldStub     = $this->createStub(TextField::class);
		$companyNameFieldStub = $this->createStub(TextField::class);
		$companyIdStub        = $this->createStub(DropdownField::class);
		$statusStub           = $this->createStub(DropdownField::class);

		$this->buildServiceStub->method('buildFormField')->willReturnMap([
			[['type' => FieldType::TEXT, 'id' => Parameters::PARAMETER_USERNAME, 'name' => Parameters::PARAMETER_USERNAME, 'title' => 'Username', 'label' => 'Username', 'value' => 'test_username'], $userFieldStub],
			[['type' => FieldType::TEXT, 'id' => Parameters::PARAMETER_EMAIL, 'name' => Parameters::PARAMETER_EMAIL, 'title' => 'Email', 'label' => 'Email', 'value' => 'test_email@example.com'], $emailFieldStub],
			[['type' => FieldType::TEXT, 'id' => Parameters::PARAMETER_FIRSTNAME, 'name' => Parameters::PARAMETER_FIRSTNAME, 'title' => 'First Name', 'label' => 'First Name', 'value' => 'John'], $firstNameFieldStub],
			[['type' => FieldType::TEXT, 'id' => Parameters::PARAMETER_SURNAME, 'name' => Parameters::PARAMETER_SURNAME, 'title' => 'Surname', 'label' => 'Surname', 'value' => 'Doe'], $surNameFieldStub],
			[['type' => FieldType::TEXT, 'id' => Parameters::PARAMETER_COMPANY_NAME, 'name' => Parameters::PARAMETER_COMPANY_NAME, 'title' => 'Company name', 'label' => 'Company name', 'value' => 'Acme'], $companyNameFieldStub],
			[['type' => FieldType::DROPDOWN, 'id' => BaseFilterParametersInterface::PARAMETER_COMPANY_ID, 'name' => BaseFilterParametersInterface::PARAMETER_COMPANY_ID, 'title' => 'Belongs company', 'label' => 'Belongs company', 'value' => 56,'options' => []], $companyIdStub],
			[['type' => FieldType::DROPDOWN, 'id' => Parameters::PARAMETER_STATUS, 'name' => Parameters::PARAMETER_STATUS, 'title' => 'Status', 'label' => 'Status', 'value' => UserStatus::REGISTERED->value, 'options' => $status], $statusStub]
		]);


		$this->builder->collectFormElements();

		$form = $this->builder->getDatatableStructure()['form'];

		static::assertArrayHasKey(Parameters::PARAMETER_USERNAME, $form);
		static::assertArrayHasKey(Parameters::PARAMETER_EMAIL, $form);
		static::assertArrayHasKey(Parameters::PARAMETER_FIRSTNAME, $form);
		static::assertArrayHasKey(Parameters::PARAMETER_SURNAME, $form);
		static::assertArrayHasKey(Parameters::PARAMETER_COMPANY_NAME, $form);
		static::assertArrayHasKey(BaseFilterParametersInterface::PARAMETER_COMPANY_ID, $form);
		static::assertArrayHasKey(Parameters::PARAMETER_STATUS, $form);

		static::assertEquals($userFieldStub, $form[Parameters::PARAMETER_USERNAME]);
		static::assertEquals($emailFieldStub, $form[Parameters::PARAMETER_EMAIL]);
		static::assertEquals($firstNameFieldStub, $form[Parameters::PARAMETER_FIRSTNAME]);
		static::assertEquals($surNameFieldStub, $form[Parameters::PARAMETER_SURNAME]);
		static::assertEquals($companyNameFieldStub, $form[Parameters::PARAMETER_COMPANY_NAME]);
		static::assertEquals($companyIdStub, $form[BaseFilterParametersInterface::PARAMETER_COMPANY_ID]);
		static::assertEquals($statusStub, $form[Parameters::PARAMETER_STATUS]);

	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testCollectFormElementsMinimum(): void
	{
		$this->aclValidatorMock->expects($this->never())->method($this->anything());

		$this->parametersMock->expects($this->atLeastOnce())->method('hasParameter')->willReturn(false);

		$this->parametersMock->expects($this->atLeastOnce())->method('getValueOfParameter')
			->willReturnMap([
				[Parameters::PARAMETER_USERNAME, 'test_username'],
				[Parameters::PARAMETER_EMAIL, 'test_email@example.com']
			]);

		$status = ['deleted' => 'Deleted', 'banned' => 'Banned', 'inactive' => 'Inactive', 'active' => 'Active'];
		$this->translatorStub->method('translateArrayForOptions')
			->willReturn($status);

		$this->translatorStub->method('translate')->willReturnMap([
			[Parameters::PARAMETER_USERNAME, 'main', [], 'Username'],
			[Parameters::PARAMETER_EMAIL, 'users', [], 'Email']
		]);

		$userFieldStub        = $this->createStub(TextField::class);
		$emailFieldStub       = $this->createStub(EmailField::class);

		$this->buildServiceStub->method('buildFormField')->willReturnMap([
			[['type' => FieldType::TEXT, 'id' => Parameters::PARAMETER_USERNAME, 'name' => Parameters::PARAMETER_USERNAME, 'title' => 'Username', 'label' => 'Username', 'value' => 'test_username'], $userFieldStub],
			[['type' => FieldType::TEXT, 'id' => Parameters::PARAMETER_EMAIL, 'name' => Parameters::PARAMETER_EMAIL, 'title' => 'Email', 'label' => 'Email', 'value' => 'test_email@example.com'], $emailFieldStub],
		]);


		$this->builder->collectFormElements();

		$form = $this->builder->getDatatableStructure()['form'];

		static::assertArrayHasKey(Parameters::PARAMETER_USERNAME, $form);
		static::assertArrayHasKey(Parameters::PARAMETER_EMAIL, $form);

		static::assertEquals($userFieldStub, $form[Parameters::PARAMETER_USERNAME]);
		static::assertEquals($emailFieldStub, $form[Parameters::PARAMETER_EMAIL]);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testCreateTableFieldsAddsCorrectFieldsForCoreAndEnterpriseEditions(): void
	{
		$this->parametersMock->expects($this->never())->method($this->anything());
		$this->aclValidatorMock
			->expects($this->atLeastOnce())->method('getConfig')
			->willReturn($this->createConfiguredStub(Config::class, ['getEdition' => Config::PLATFORM_EDITION_CORE]));

		$resultsBuilderMock = $this->createMock(Builder::class);
		$this->buildServiceStub->method('getResultsBuilder')->willReturn($resultsBuilderMock);

		$resultsBuilderMock->expects($this->exactly(6))
			->method('createField')
			->willReturnMap([
				['username', true],
				['created_at', true],
				['status', false],
				['firstname', false],
				['surname', false],
				['company_name', false]
			]);

		$this->builder->createTableFields();
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testCreateTableFieldsAddsLimitedFieldsForEdgeEdition(): void
	{
		$this->parametersMock->expects($this->never())->method($this->anything());
		$this->aclValidatorMock
			->expects($this->atLeastOnce())->method('getConfig')
			->willReturn($this->createConfiguredStub(Config::class, ['getEdition' => Config::PLATFORM_EDITION_EDGE]));

		$resultsBuilderMock = $this->createMock(Builder::class);
		$this->buildServiceStub->method('getResultsBuilder')->willReturn($resultsBuilderMock);

		$resultsBuilderMock->expects($this->exactly(3))
			->method('createField')
			->willReturnMap([
				['username', true],
				['created_at', true],
				['status', false]
			]);

		$this->builder->createTableFields();
	}


}
