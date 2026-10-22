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

namespace Tests\Unit\Modules\Playlists\Helper\Datatable;

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
use App\Modules\Playlists\Helper\Datatable\DatatableBuilder;
use App\Modules\Playlists\Helper\Datatable\Parameters;
use App\Modules\Playlists\Helper\PlaylistMode;
use App\Modules\Playlists\Services\AclValidator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
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
	 * @throws Exception
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
	 * @throws CoreException
	 * @throws Exception
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
	 * @throws Exception
	 * @throws CoreException
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
	 * @throws Exception
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
			->with('overview', 'playlists')
			->willReturn($expectedTitle);

		$this->builder->buildTitle();

		static::assertSame($expectedTitle, $this->builder->getDatatableStructure()['title']);
	}

	/**
	 * @throws FrameworkException
	 * @throws CoreException
	 * @throws Exception|ModuleException
	 */
	#[Group('units')]
	public function testCollectFormElements(): void
	{
		// the allowed playlist modes depend on the edition
		$this->aclValidatorMock->expects($this->once())->method('getConfig')->willReturn($this->createStub(\App\Framework\Config\Config::class));
		$translator = $this->createStub(Translator::class);
		$this->builder->setTranslator($translator);

		$this->parametersMock->expects($this->atLeastOnce())->method('hasParameter')
		->willReturnMap([
			[BaseParameters::PARAMETER_UID, true]
		]);


		$this->parametersMock->expects($this->atLeastOnce())->method('getValueOfParameter')
			->willReturnMap([
				[Parameters::PARAMETER_PLAYLIST_NAME, 'playlist_name'],
				[BaseParameters::PARAMETER_UID, 56],
				[Parameters::PARAMETER_PLAYLIST_MODE, '']
			]);

		$mode = [];
		$translator->method('translateArrayForOptions')
			->willReturn($mode);

		$translator->method('translate')->willReturnMap([
			[Parameters::PARAMETER_PLAYLIST_NAME, 'playlists', [], 'Playlist name'],
			['owner', 'main', [], 'Owner'],
			[Parameters::PARAMETER_PLAYLIST_MODE, 'playlists', 'Playlist mode']
		]);


		$playlistNameFieldStub = $this->createStub(TextField::class);
		$ownerFieldStub        = $this->createStub(AutocompleteField::class);
		$playlistModeFieldStub = $this->createStub(DropdownField::class);

		$this->buildServiceMock->expects($this->atLeastOnce())->method('buildFormField')->willReturnMap([
			[['type' => FieldType::TEXT, 'id' => Parameters::PARAMETER_PLAYLIST_NAME, 'name' => Parameters::PARAMETER_PLAYLIST_NAME, 'title' => 'Playlist name', 'label' => 'Playlist name', 'value' => 'playlist_name'], $playlistNameFieldStub],
			[['type' => FieldType::AUTOCOMPLETE, 'id' => BaseParameters::PARAMETER_UID, 'name' => BaseParameters::PARAMETER_UID, 'title' => 'Owner', 'label' => 'Owner', 'value' => 56, 'data-label' => ''], $ownerFieldStub],
			[['type' => FieldType::DROPDOWN, 'id' => Parameters::PARAMETER_PLAYLIST_MODE, 'name' => Parameters::PARAMETER_PLAYLIST_MODE, 'title' => 'Playlist mode', 'label' => 'Playlist mode', 'value' => '','options' => []], $playlistModeFieldStub]
		]);


		$this->builder->collectFormElements();

		$form = $this->builder->getDatatableStructure()['form'];

		static::assertArrayHasKey(Parameters::PARAMETER_PLAYLIST_NAME, $form);
		static::assertArrayHasKey(BaseParameters::PARAMETER_UID, $form);
		static::assertArrayHasKey(Parameters::PARAMETER_PLAYLIST_MODE, $form);

		static::assertEquals($playlistNameFieldStub, $form[Parameters::PARAMETER_PLAYLIST_NAME]);
		static::assertEquals($ownerFieldStub, $form[BaseParameters::PARAMETER_UID]);
		static::assertEquals($playlistModeFieldStub, $form[Parameters::PARAMETER_PLAYLIST_MODE]);
	}

	/**
	 * @throws FrameworkException
	 * @throws CoreException
	 * @throws Exception|ModuleException
	 */
	#[Group('units')]
	public function testCollectFormElementsMinimum(): void
	{
		// the allowed playlist modes depend on the edition
		$this->aclValidatorMock->expects($this->once())->method('getConfig')->willReturn($this->createStub(\App\Framework\Config\Config::class));
		$translator = $this->createStub(Translator::class);
		$this->builder->setTranslator($translator);

		$this->parametersMock->expects($this->atLeastOnce())->method('hasParameter')
			->willReturnMap([
				[BaseParameters::PARAMETER_UID, false]
			]);


		$this->parametersMock->expects($this->atLeastOnce())->method('getValueOfParameter')
			->willReturnMap([
				[Parameters::PARAMETER_PLAYLIST_NAME, 'playlist_name'],
				[Parameters::PARAMETER_PLAYLIST_MODE, '']
			]);

		$mode = [];
		$translator->method('translateArrayForOptions')
			->willReturn($mode);

		$translator->method('translate')->willReturnMap([
			[Parameters::PARAMETER_PLAYLIST_NAME, 'playlists', [], 'Playlist name'],
			[Parameters::PARAMETER_PLAYLIST_MODE, 'playlists', 'Playlist mode']
		]);


		$playlistNameFieldStub = $this->createStub(TextField::class);
		$playlistModeFieldStub = $this->createStub(DropdownField::class);

		$this->buildServiceMock->expects($this->atLeastOnce())->method('buildFormField')->willReturnMap([
			[['type' => FieldType::TEXT, 'id' => Parameters::PARAMETER_PLAYLIST_NAME, 'name' => Parameters::PARAMETER_PLAYLIST_NAME, 'title' => 'Playlist name', 'label' => 'Playlist name', 'value' => 'playlist_name'], $playlistNameFieldStub],
			[['type' => FieldType::DROPDOWN, 'id' => Parameters::PARAMETER_PLAYLIST_MODE, 'name' => Parameters::PARAMETER_PLAYLIST_MODE, 'title' => 'Playlist mode', 'label' => 'Playlist mode', 'value' => '','options' => []], $playlistModeFieldStub]
		]);


		$this->builder->collectFormElements();

		$form = $this->builder->getDatatableStructure()['form'];

		static::assertArrayHasKey(Parameters::PARAMETER_PLAYLIST_NAME, $form);
		static::assertArrayHasKey(Parameters::PARAMETER_PLAYLIST_MODE, $form);

		static::assertEquals($playlistNameFieldStub, $form[Parameters::PARAMETER_PLAYLIST_NAME]);
		static::assertEquals($playlistModeFieldStub, $form[Parameters::PARAMETER_PLAYLIST_MODE]);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testCreateTableFieldsAddsLimitedFieldsForEdgeEdition(): void
	{
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
		$this->parametersMock->expects($this->atLeastOnce())->method('hasParameter')
			->willReturn(true);

		$this->buildServiceMock->method('getDatatableFields')->willReturn([]);

		$this->buildServiceMock->expects($this->exactly(4))
			->method('createDatatableField')
			->willReturnMap([
				['playlist', true],
				['UID', true],
				['playlist_mode', true],
				['duration', false]
			]);

		$this->builder->createTableFields();
	}

	/**
	 * @throws Exception
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testDetermineAllowedPlayerModesForEdgeEdition(): void
	{
		$this->buildServiceMock->expects($this->never())->method($this->anything());
		$this->parametersMock->expects($this->never())->method($this->anything());
		$configStub = $this->createStub(Config::class);
		$this->aclValidatorMock->expects($this->atLeastOnce())->method('getConfig')->willReturn($configStub);
		$configStub->method('getEdition')->willReturn(Config::PLATFORM_EDITION_EDGE);

		$translatorMock = $this->createMock(Translator::class);
		$this->builder->setTranslator($translatorMock);
		$translatorMock->method('translateArrayForOptions')
			->with('playlist_mode_selects', 'playlists')
			->willReturn([PlaylistMode::MASTER->value => 'Master', PlaylistMode::CHANNEL->value => 'Channel', PlaylistMode::EXTERNAL->value => 'External']);

		$result = $this->builder->determineAllowedPlaylistModes();
		static::assertSame([PlaylistMode::MASTER->value => 'Master'], $result);

	}

	private function expectUntouched(MockObject ...$mocks): void
	{
		foreach ($mocks as $mock)
		{
			$mock->expects($this->never())->method($this->anything());
		}
	}
}
