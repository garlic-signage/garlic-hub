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

namespace Tests\Unit\Modules\System\Helper\Audit;

use App\Framework\Config\Config;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Utils\Datatable\BuildService;
use App\Framework\Utils\Html\FieldInterface;
use App\Framework\Utils\Html\FieldType;
use App\Framework\Validation\Sanitizer;
use App\Modules\System\Helper\Audit\DatatableBuilder;
use App\Modules\System\Helper\Audit\Parameters;
use App\Modules\System\Services\AclValidator;
use App\Shared\Audit\AuditAction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\MemorySession;

/**
 * The build service is a stub which records what the builder asks for. The translator gives module.key back.
 */
class DatatableBuilderTest extends TestCase
{
	private Parameters $parameters;
	private DatatableBuilder $builder;
	/** @var array<string,array<string,mixed>> the attributes of the form fields by id */
	private array $formFields = [];
	/** @var list<array{string,bool}> */
	private array $tableFields = [];

	protected function setUp(): void
	{
		parent::setUp();
		$translator = $this->createStub(Translator::class);
		$translator->method('translate')->willReturnCallback(static fn(string $key, string $module): string => $module . '.' . $key);

		$buildServiceStub = $this->createStub(BuildService::class);
		$buildServiceStub->method('buildFormField')->willReturnCallback(function (array $attributes): FieldInterface {
			$this->formFields[$attributes['id']] = $attributes;
			return $this->createStub(FieldInterface::class);
		});
		$buildServiceStub->method('createDatatableField')->willReturnCallback(function (string $name, bool $sortable): void {
			$this->tableFields[] = [$name, $sortable];
		});
		$buildServiceStub->method('getDatatableFields')->willReturnCallback(fn(): array => $this->tableFields);

		$configStub = $this->createStub(Config::class);
		$configStub->method('getEdition')->willReturn(Config::PLATFORM_EDITION_CORE);
		$aclValidatorStub = $this->createStub(AclValidator::class);
		$aclValidatorStub->method('getConfig')->willReturn($configStub);

		// the form shows the values of the request, which are parsed before
		$this->parameters = new Parameters(new Sanitizer(), new MemorySession());
		$this->parameters->setUserInputs([]);
		$this->parameters->parseInputFilterAllUsers([]);
		$this->builder    = new DatatableBuilder($buildServiceStub, $this->parameters, $aclValidatorStub, $translator);
	}

	#[Group('units')]
	public function testTheTitle(): void
	{
		$this->builder->buildTitle();

		static::assertSame('system.audit_log', $this->builder->getDatatableStructure()['title']);
	}

	#[Group('units')]
	public function testTheTextFieldsHaveTheValuesOfTheFilter(): void
	{
		$this->parameters->setValueOfParameter(Parameters::PARAMETER_DATE_FROM, '2024-02-01');
		$this->parameters->setValueOfParameter(Parameters::PARAMETER_DATE_TO, '2024-02-29');
		$this->parameters->setValueOfParameter(Parameters::PARAMETER_USERNAME, 'anna');

		$this->builder->collectFormElements();

		static::assertSame(['date_from', 'date_to', 'username', 'module', 'action'], array_keys($this->builder->getDatatableStructure()['form']));
		$text = static fn(string $name, string $value, string $placeholder): array => [
			'type' => FieldType::TEXT, 'id' => $name, 'name' => $name, 'title' => 'system.' . $name, 'label' => 'system.' . $name,
			'value' => $value, 'placeholder' => $placeholder
		];
		static::assertSame($text('date_from', '2024-02-01', 'YYYY-MM-DD'), $this->formFields['date_from']);
		static::assertSame($text('date_to', '2024-02-29', 'YYYY-MM-DD'), $this->formFields['date_to']);
		static::assertSame($text('username', 'anna', ''), $this->formFields['username']);
	}

	/**
	 * The modules of the edition and those which write without rights of their own, sorted.
	 */
	#[Group('units')]
	public function testTheModulesAreADropdown(): void
	{
		$this->parameters->setValueOfParameter(Parameters::PARAMETER_MODULE, 'playlists');

		$this->builder->collectFormElements();

		$modules = ['account', 'auth', 'mediapool', 'player', 'playlists', 'system', 'templates', 'users'];
		static::assertSame([
			'type' => FieldType::DROPDOWN, 'id' => 'module', 'name' => 'module', 'title' => 'system.module', 'label' => 'system.module',
			'value' => 'playlists', 'options' => array_combine($modules, $modules)
		], $this->formFields['module']);
	}

	#[Group('units')]
	public function testTheActionsAreADropdownWithTheirTranslations(): void
	{
		$this->parameters->setValueOfParameter(Parameters::PARAMETER_ACTION, 'role_granted');

		$this->builder->collectFormElements();

		$options = [];
		foreach (AuditAction::cases() as $action)
			$options[$action->value] = 'system.action_' . $action->value;
		static::assertGreaterThan(1, count($options));
		static::assertSame([
			'type' => FieldType::DROPDOWN, 'id' => 'action', 'name' => 'action', 'title' => 'system.action', 'label' => 'system.action',
			'value' => 'role_granted', 'options' => $options
		], $this->formFields['action']);
	}

	#[Group('units')]
	public function testNoColumnIsSortable(): void
	{
		$this->builder->createTableFields();

		$expected = [['created_at', false], ['UID', false], ['action', false], ['module', false], ['target_UID', false], ['details', false]];
		static::assertSame($expected, $this->builder->getDatatableStructure()['header']);
	}
}
