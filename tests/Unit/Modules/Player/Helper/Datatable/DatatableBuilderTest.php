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

namespace Tests\Unit\Modules\Player\Helper\Datatable;

use App\Framework\Config\Config;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Datatable\BuildService;
use App\Framework\Utils\FormParameters\BaseParameters;
use App\Framework\Utils\Html\AutocompleteField;
use App\Framework\Utils\Html\DropdownField;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Utils\Html\TextField;
use App\Modules\Player\Helper\Datatable\DatatableBuilder;
use App\Modules\Player\Helper\Datatable\Parameters;
use App\Modules\Player\Services\AclValidator;
use Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DatatableBuilderTest extends TestCase
{
	private BuildService&MockObject $buildServiceMock;
	private Parameters&MockObject $parametersMock;
	private AclValidator&MockObject $aclValidatorMock;
	private DatatableBuilder $builder;

	/**
	 * @throws Exception
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->buildServiceMock = $this->createMock(BuildService::class);
		$this->parametersMock   = $this->createMock(Parameters::class);
		$this->aclValidatorMock = $this->createMock(AclValidator::class);

		$this->builder = new DatatableBuilder($this->buildServiceMock, $this->parametersMock, $this->aclValidatorMock);
	}

	/**
	 * @throws CoreException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testConfigureParametersDoesNothingForEdgeEdition(): void
	{
		$this->buildServiceMock->expects($this->never())->method($this->anything());
		$this->aclValidatorMock
			->expects($this->atLeastOnce())->method('getConfig')
			->willReturn($this->createConfiguredStub(Config::class, ['getEdition' => Config::PLATFORM_EDITION_EDGE]));

		$this->parametersMock->expects($this->never())->method('addOwner');
		$this->parametersMock->expects($this->never())->method('addCompany');

		$this->builder->configureParameters(123);
	}

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testConfigureParametersCallsAddOwnerAndCompanyForModuleAdmin(): void
	{
		$this->buildServiceMock->expects($this->never())->method($this->anything());
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
	 * @throws CoreException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testConfigureParametersCallsAddOwnerAndCompanyForSubAdmin(): void
	{
		$this->buildServiceMock->expects($this->never())->method($this->anything());
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

	#[Group('units')]
	public function testDetermineParametersSetsUserInputsAndParsesFilterAllUsers(): void
	{
		$this->buildServiceMock->expects($this->never())->method($this->anything());
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
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildTitleSetsCorrectTitle(): void
	{
		$this->expectUntouched($this->buildServiceMock, $this->parametersMock, $this->aclValidatorMock);
		$expectedTitle = 'Translated Title';
		$translator = $this->createMock(Translator::class);
		$this->builder->setTranslator($translator);

		$translator->method('translate')
			->with('overview', 'player')
			->willReturn($expectedTitle);

		$this->builder->buildTitle();

		static::assertSame($expectedTitle, $this->builder->getDatatableStructure()['title']);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	#[Group('units')]
	public function testCollectFormElements(): void
	{
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
		$translator = $this->createStub(Translator::class);
		$this->builder->setTranslator($translator);

		$this->parametersMock->expects($this->atLeastOnce())->method('hasParameter')
			->willReturnMap([
				[Parameters::PARAMETER_ACTIVITY, true],
				[BaseParameters::PARAMETER_UID, true]
			]);

		$this->parametersMock->expects($this->atLeastOnce())->method('getValueOfParameter')
			->willReturnMap([
				[Parameters::PARAMETER_ACTIVITY, ''],
				[Parameters::PARAMETER_PLAYER_NAME, 'player_name'],
				[BaseParameters::PARAMETER_UID, 56],
				[Parameters::PARAMETER_MODEL, '']
			]);

		$translator->method('translateArrayForOptions')
			->willReturnMap([
				[Parameters::PARAMETER_ACTIVITY.'_selects', 'player', []],
				[Parameters::PARAMETER_MODEL.'_selects', 'player', []]
			]);

		$translator->method('translate')->willReturnMap([
			[Parameters::PARAMETER_ACTIVITY, 'player', [], 'Activity'],
			[Parameters::PARAMETER_PLAYER_NAME, 'player', [], 'Player name'],
			['owner', 'main', [], 'Owner'],
			[Parameters::PARAMETER_MODEL, 'player', 'Player model']
		]);


		$activityFieldStub   = $this->createStub(DropdownField::class);
		$playerNameFieldStub = $this->createStub(TextField::class);
		$playerModeFieldStub = $this->createStub(DropdownField::class);
		$ownerFieldStub      = $this->createStub(AutocompleteField::class);

		$this->buildServiceMock->expects($this->atLeastOnce())->method('buildFormField')->willReturnMap([
			[['type' => FieldType::DROPDOWN, 'id' => Parameters::PARAMETER_ACTIVITY, 'name' => Parameters::PARAMETER_ACTIVITY, 'title' => 'Activity', 'label' => 'Activity', 'value' => '','options' => []], $activityFieldStub],
			[['type' => FieldType::TEXT, 'id' => Parameters::PARAMETER_PLAYER_NAME, 'name' => Parameters::PARAMETER_PLAYER_NAME, 'title' => 'Player name', 'label' => 'Player name', 'value' => 'player_name'], $playerNameFieldStub],
			[['type' => FieldType::DROPDOWN, 'id' => Parameters::PARAMETER_MODEL, 'name' => Parameters::PARAMETER_MODEL, 'title' => 'Player model', 'label' => 'Player model', 'value' => '','options' => []], $playerModeFieldStub],
			[['type' => FieldType::AUTOCOMPLETE, 'id' => BaseParameters::PARAMETER_UID, 'name' => BaseParameters::PARAMETER_UID, 'title' => 'Owner', 'label' => 'Owner', 'value' => 56, 'data-label' => ''], $ownerFieldStub],

		]);

		$this->builder->collectFormElements();

		$form = $this->builder->getDatatableStructure()['form'];

		static::assertArrayHasKey(Parameters::PARAMETER_ACTIVITY, $form);
		static::assertArrayHasKey(Parameters::PARAMETER_PLAYER_NAME, $form);
		static::assertArrayHasKey(Parameters::PARAMETER_MODEL, $form);
		static::assertArrayHasKey(BaseParameters::PARAMETER_UID, $form);

		static::assertEquals($playerNameFieldStub, $form[Parameters::PARAMETER_PLAYER_NAME]);
		static::assertEquals($ownerFieldStub, $form[BaseParameters::PARAMETER_UID]);
		static::assertEquals($playerModeFieldStub, $form[Parameters::PARAMETER_MODEL]);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	#[Group('units')]
	public function testCollectFormElementsMinimum(): void
	{
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
		$translator = $this->createStub(Translator::class);
		$this->builder->setTranslator($translator);

		$this->parametersMock->expects($this->atLeastOnce())->method('hasParameter')
			->willReturnMap([
				[Parameters::PARAMETER_ACTIVITY, false],
				[BaseParameters::PARAMETER_UID, false]
			]);

		$this->parametersMock->expects($this->atLeastOnce())->method('getValueOfParameter')
			->willReturnMap([
				[Parameters::PARAMETER_PLAYER_NAME, 'player_name'],
				[Parameters::PARAMETER_MODEL, '']
			]);

		$translator->method('translateArrayForOptions')
			->willReturnMap([
				[Parameters::PARAMETER_MODEL.'_selects', 'player', []]
			]);

		$translator->method('translate')->willReturnMap([
			[Parameters::PARAMETER_PLAYER_NAME, 'player', [], 'Player name'],
			[Parameters::PARAMETER_MODEL, 'player', 'Player model']
		]);

		$playerNameFieldStub = $this->createStub(TextField::class);
		$playerModeFieldStub = $this->createStub(DropdownField::class);

		$this->buildServiceMock->expects($this->atLeastOnce())->method('buildFormField')->willReturnMap([
			[['type' => FieldType::TEXT, 'id' => Parameters::PARAMETER_PLAYER_NAME, 'name' => Parameters::PARAMETER_PLAYER_NAME, 'title' => 'Player name', 'label' => 'Player name', 'value' => 'player_name'], $playerNameFieldStub],
			[['type' => FieldType::DROPDOWN, 'id' => Parameters::PARAMETER_MODEL, 'name' => Parameters::PARAMETER_MODEL, 'title' => 'Player model', 'label' => 'Player model', 'value' => '','options' => []], $playerModeFieldStub]
		]);


		$this->builder->collectFormElements();

		$form = $this->builder->getDatatableStructure()['form'];

		static::assertArrayHasKey(Parameters::PARAMETER_PLAYER_NAME, $form);
		static::assertArrayHasKey(Parameters::PARAMETER_MODEL, $form);

		static::assertEquals($playerNameFieldStub, $form[Parameters::PARAMETER_PLAYER_NAME]);
		static::assertEquals($playerModeFieldStub, $form[Parameters::PARAMETER_MODEL]);
	}

	/**
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testCreateTableFields(): void
	{
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
		$this->parametersMock->expects($this->atLeastOnce())->method('hasParameter')
			->willReturn(true);

		$this->buildServiceMock->method('getDatatableFields')->willReturn([]);

		$this->buildServiceMock->expects($this->exactly(5))
			->method('createDatatableField')
			->willReturnMap([
				['last_access', true],
				['player_name', true],
				['UID', true],
				['model', true],
				['playlist_id', false],
			]);

		$this->builder->createTableFields();
	}

	private function expectUntouched(MockObject ...$mocks): void
	{
		foreach ($mocks as $mock)
		{
			$mock->expects($this->never())->method($this->anything());
		}
	}
}
