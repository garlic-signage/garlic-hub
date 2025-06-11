<?php

namespace Tests\Unit\Modules\Player\Helper\Datatable;

use App\Framework\Core\Config\Config;
use App\Framework\Core\Translate\Translator;
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
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\InvalidArgumentException;

class DatatableBuilderTest extends TestCase
{
	private readonly BuildService&MockObject $buildServiceMock;
	private readonly Parameters&MockObject $parametersMock;
	private readonly AclValidator&MockObject $aclValidatorMock;
	private readonly DatatableBuilder $builder;

	/**
	 * @throws Exception
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		$this->buildServiceMock = $this->createMock(BuildService::class);
		$this->parametersMock   = $this->createMock(Parameters::class);
		$this->aclValidatorMock = $this->createMock(AclValidator::class);

		$this->builder = new DatatableBuilder($this->buildServiceMock, $this->parametersMock, $this->aclValidatorMock);
	}

	/**
	 * @throws CoreException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testConfigureParametersDoesNothingForEdgeEdition(): void
	{
		$this->aclValidatorMock
			->method('getConfig')
			->willReturn($this->createConfiguredMock(Config::class, ['getEdition' => Config::PLATFORM_EDITION_EDGE]));

		$this->parametersMock->expects($this->never())->method('addOwner');
		$this->parametersMock->expects($this->never())->method('addCompany');

		$this->builder->configureParameters(123);
	}

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testConfigureParametersCallsAddOwnerAndCompanyForModuleAdmin(): void
	{
		$this->aclValidatorMock
			->method('getConfig')
			->willReturn($this->createConfiguredMock(Config::class, ['getEdition' => 'some_other_edition']));

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
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testConfigureParametersCallsAddOwnerAndCompanyForSubAdmin(): void
	{
		$this->aclValidatorMock
			->method('getConfig')
			->willReturn($this->createConfiguredMock(Config::class, ['getEdition' => 'some_other_edition']));

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
		$_GET = ['test_key' => 'test_value'];

		$this->parametersMock->expects($this->once())
			->method('setUserInputs')
			->with($_GET);

		$this->parametersMock->expects($this->once())
			->method('parseInputFilterAllUsers');

		$this->builder->determineParameters();
	}

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildTitleSetsCorrectTitle(): void
	{
		$expectedTitle = 'Translated Title';
		$translator = $this->createMock(Translator::class);
		$this->builder->setTranslator($translator);

		$translator->method('translate')
			->with('overview', 'player')
			->willReturn($expectedTitle);

		$this->builder->buildTitle();

		$this->assertSame($expectedTitle, $this->builder->getDatatableStructure()['title']);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	#[Group('units')]
	public function testCollectFormElements(): void
	{
		$translator = $this->createMock(Translator::class);
		$this->builder->setTranslator($translator);

		$this->parametersMock->method('hasParameter')
			->willReturnMap([
				[BaseParameters::PARAMETER_UID, true]
			]);

		$this->parametersMock->method('getValueOfParameter')
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


		$activityFieldMock   = $this->createMock(DropdownField::class);
		$playerNameFieldMock = $this->createMock(TextField::class);
		$playerModeFieldMock = $this->createMock(DropdownField::class);
		$ownerFieldMock      = $this->createMock(AutocompleteField::class);

		$this->buildServiceMock->method('buildFormField')->willReturnMap([
			[['type' => FieldType::DROPDOWN, 'id' => Parameters::PARAMETER_ACTIVITY, 'name' => Parameters::PARAMETER_ACTIVITY, 'title' => 'Activity', 'label' => 'Activity', 'value' => '','options' => []], $activityFieldMock],
			[['type' => FieldType::TEXT, 'id' => Parameters::PARAMETER_PLAYER_NAME, 'name' => Parameters::PARAMETER_PLAYER_NAME, 'title' => 'Player name', 'label' => 'Player name', 'value' => 'player_name'], $playerNameFieldMock],
			[['type' => FieldType::DROPDOWN, 'id' => Parameters::PARAMETER_MODEL, 'name' => Parameters::PARAMETER_MODEL, 'title' => 'Player model', 'label' => 'Player model', 'value' => '','options' => []], $playerModeFieldMock],
			[['type' => FieldType::AUTOCOMPLETE, 'id' => BaseParameters::PARAMETER_UID, 'name' => BaseParameters::PARAMETER_UID, 'title' => 'Owner', 'label' => 'Owner', 'value' => 56, 'data-label' => ''], $ownerFieldMock],

		]);


		$this->builder->collectFormElements();

		$form = $this->builder->getDatatableStructure()['form'];

		$this->assertArrayHasKey(Parameters::PARAMETER_ACTIVITY, $form);
		$this->assertArrayHasKey(Parameters::PARAMETER_PLAYER_NAME, $form);
		$this->assertArrayHasKey(Parameters::PARAMETER_MODEL, $form);
		$this->assertArrayHasKey(BaseParameters::PARAMETER_UID, $form);

		$this->assertEquals($playerNameFieldMock, $form[Parameters::PARAMETER_PLAYER_NAME]);
		$this->assertEquals($ownerFieldMock, $form[BaseParameters::PARAMETER_UID]);
		$this->assertEquals($playerModeFieldMock, $form[Parameters::PARAMETER_MODEL]);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	#[Group('units')]
	public function testCollectFormElementsMinimum(): void
	{
		$translator = $this->createMock(Translator::class);
		$this->builder->setTranslator($translator);

		$this->parametersMock->method('hasParameter')
			->willReturnMap([
				[BaseParameters::PARAMETER_UID, false]
			]);

		$this->parametersMock->method('getValueOfParameter')
			->willReturnMap([
				[Parameters::PARAMETER_ACTIVITY, ''],
				[Parameters::PARAMETER_PLAYER_NAME, 'player_name'],
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
			[Parameters::PARAMETER_MODEL, 'player', 'Player model']
		]);


		$activityFieldMock   = $this->createMock(DropdownField::class);
		$playerNameFieldMock = $this->createMock(TextField::class);
		$playerModeFieldMock = $this->createMock(DropdownField::class);

		$this->buildServiceMock->method('buildFormField')->willReturnMap([
			[['type' => FieldType::DROPDOWN, 'id' => Parameters::PARAMETER_ACTIVITY, 'name' => Parameters::PARAMETER_ACTIVITY, 'title' => 'Activity', 'label' => 'Activity', 'value' => '','options' => []], $activityFieldMock],
			[['type' => FieldType::TEXT, 'id' => Parameters::PARAMETER_PLAYER_NAME, 'name' => Parameters::PARAMETER_PLAYER_NAME, 'title' => 'Player name', 'label' => 'Player name', 'value' => 'player_name'], $playerNameFieldMock],
			[['type' => FieldType::DROPDOWN, 'id' => Parameters::PARAMETER_MODEL, 'name' => Parameters::PARAMETER_MODEL, 'title' => 'Player model', 'label' => 'Player model', 'value' => '','options' => []], $playerModeFieldMock]
		]);


		$this->builder->collectFormElements();

		$form = $this->builder->getDatatableStructure()['form'];

		$this->assertArrayHasKey(Parameters::PARAMETER_ACTIVITY, $form);
		$this->assertArrayHasKey(Parameters::PARAMETER_PLAYER_NAME, $form);
		$this->assertArrayHasKey(Parameters::PARAMETER_MODEL, $form);

		$this->assertEquals($playerNameFieldMock, $form[Parameters::PARAMETER_PLAYER_NAME]);
		$this->assertEquals($playerModeFieldMock, $form[Parameters::PARAMETER_MODEL]);
	}

	/**
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws CoreException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testCreateTableFields(): void
	{
		$this->parametersMock->method('hasParameter')
			->willReturn(true);

		$this->buildServiceMock->method('getDatatableFields')->willReturn([]);

		$this->buildServiceMock->expects($this->exactly(6))
			->method('createDatatableField')
			->willReturnMap([
				['last_access', true],
				['player_name', true],
				['UID', true],
				['status', true],
				['model', true],
				['playlist_id', false],
			]);

		$this->builder->createTableFields();
	}

}
