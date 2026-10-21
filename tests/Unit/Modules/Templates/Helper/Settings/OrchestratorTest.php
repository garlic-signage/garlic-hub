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

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Templates\Helper\Settings\FormInputHandler;
use App\Modules\Templates\Helper\Settings\Orchestrator;
use App\Modules\Templates\Helper\Settings\SettingsFormBuilder;
use App\Modules\Templates\Helper\Settings\SettingsParametersPolicy;
use App\Modules\Templates\Services\TemplatesService;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\ConnectionLost;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrchestratorTest extends TestCase
{
	private SettingsFormBuilder&MockObject $formBuilderMock;
	private SettingsParametersPolicy&MockObject $policyMock;
	private TemplatesService&MockObject $serviceMock;
	private FormInputHandler&MockObject $inputHandlerMock;
	private Orchestrator $orchestrator;

	/** @var array{hidden: list<array<string,string>>, visible: list<array<string,string>>} */
	private array $form = ['hidden' => [], 'visible' => [['name' => 'x']]];

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->formBuilderMock  = $this->createMock(SettingsFormBuilder::class);
		$this->policyMock       = $this->createMock(SettingsParametersPolicy::class);
		$this->serviceMock      = $this->createMock(TemplatesService::class);
		$this->inputHandlerMock = $this->createMock(FormInputHandler::class);

		$this->orchestrator = new Orchestrator($this->formBuilderMock, $this->policyMock, $this->serviceMock, $this->inputHandlerMock);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testCheckCreateRights(): void
	{
		$this->serviceMock->expects($this->once())->method('checkCreateRights')->willReturn(true);
		$this->expectNoFormHandling();

		static::assertTrue($this->orchestrator->checkCreateRights());
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCheckEditRightsAndBuildEditForm(): void
	{
		$template = ['UID' => 5, 'company_id' => 1, 'template_id' => 12, 'name' => 'Tpl'];
		$this->serviceMock->expects($this->once())->method('loadWithUserById')->with(12)->willReturn($template);
		$this->policyMock->expects($this->once())->method('addEditFormElements');
		$this->formBuilderMock->expects($this->once())->method('buildForm')->with($template)->willReturn($this->form);
		$this->inputHandlerMock->expects($this->never())->method($this->anything());

		static::assertTrue($this->orchestrator->checkEditRights(12));
		static::assertSame($this->form, $this->orchestrator->buildEditForm());
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCheckEditRightsWithoutRights(): void
	{
		$this->serviceMock->expects($this->once())->method('loadWithUserById')
			->willThrowException(new AccessDeniedException('templates', 'template_not_editable', ['template_id' => 12]));
		$this->expectNoFormHandling();

		static::assertFalse($this->orchestrator->checkEditRights(12));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCheckEditRightsPassesDatabaseErrors(): void
	{
		$this->serviceMock->expects($this->once())->method('loadWithUserById')
			->willThrowException(new ConnectionLost(
				$this->createStub(\Doctrine\DBAL\Driver\Exception::class), null
			));
		$this->expectNoFormHandling();

		$this->expectException(Exception::class);

		$this->orchestrator->checkEditRights(12);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildCreateForm(): void
	{
		$this->policyMock->expects($this->once())->method('addCreateFormElements');
		$this->formBuilderMock->expects($this->once())->method('buildForm')->with([])->willReturn($this->form);
		$this->serviceMock->expects($this->never())->method($this->anything());
		$this->inputHandlerMock->expects($this->never())->method($this->anything());

		static::assertSame($this->form, $this->orchestrator->buildCreateForm());
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testStoreCreateSettingsWithErrors(): void
	{
		$this->policyMock->expects($this->once())->method('addCreateFormElements');
		$this->inputHandlerMock->expects($this->once())->method('validateCreate')->with(['name' => ''])->willReturn(['Name is missing.']);
		$this->inputHandlerMock->expects($this->never())->method('getParsed');
		$this->serviceMock->expects($this->never())->method('insert');
		$this->formBuilderMock->expects($this->never())->method($this->anything());

		static::assertSame(['success' => false, 'errors' => ['Name is missing.']], $this->orchestrator->storeCreateSettings(['name' => '']));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testStoreCreateSettings(): void
	{
		$this->policyMock->expects($this->once())->method('addCreateFormElements');
		$this->inputHandlerMock->expects($this->once())->method('validateCreate')->willReturn([]);
		$this->inputHandlerMock->expects($this->once())->method('getParsed')->willReturn(['name' => 'Tpl']);
		$this->serviceMock->expects($this->once())->method('insert')->with(['name' => 'Tpl'])->willReturn(17);
		$this->formBuilderMock->expects($this->never())->method($this->anything());

		static::assertSame(['success' => true], $this->orchestrator->storeCreateSettings(['name' => 'Tpl']));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testStoreCreateSettingsInsertFails(): void
	{
		$this->policyMock->expects($this->once())->method('addCreateFormElements');
		$this->inputHandlerMock->expects($this->once())->method('validateCreate')->willReturn([]);
		$this->inputHandlerMock->expects($this->once())->method('getParsed')->willReturn(['name' => 'Tpl']);
		$this->serviceMock->expects($this->once())->method('insert')->willReturn(0);
		$this->formBuilderMock->expects($this->never())->method($this->anything());

		static::assertSame(['success' => false, 'errors' => ['No save possible']], $this->orchestrator->storeCreateSettings(['name' => 'Tpl']));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testStoreEditSettingsWithErrors(): void
	{
		$this->policyMock->expects($this->once())->method('addEditFormElements');
		$this->inputHandlerMock->expects($this->once())->method('validateEdit')->willReturn(['CSRF token mismatch.']);
		$this->serviceMock->expects($this->never())->method('update');
		$this->formBuilderMock->expects($this->never())->method($this->anything());

		static::assertSame(['success' => false, 'errors' => ['CSRF token mismatch.']], $this->orchestrator->storeEditSettings(12, []));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testStoreEditSettings(): void
	{
		$this->policyMock->expects($this->once())->method('addEditFormElements');
		$this->inputHandlerMock->expects($this->once())->method('validateEdit')->with(['name' => 'New'])->willReturn([]);
		$this->inputHandlerMock->expects($this->once())->method('getParsed')->willReturn(['name' => 'New']);
		$this->serviceMock->expects($this->once())->method('update')->with(12, ['name' => 'New'])->willReturn(1);
		$this->formBuilderMock->expects($this->never())->method($this->anything());

		static::assertSame(['success' => true], $this->orchestrator->storeEditSettings(12, ['name' => 'New']));
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testStoreEditSettingsUpdateFails(): void
	{
		$this->policyMock->expects($this->once())->method('addEditFormElements');
		$this->inputHandlerMock->expects($this->once())->method('validateEdit')->willReturn([]);
		$this->inputHandlerMock->expects($this->once())->method('getParsed')->willReturn(['name' => 'New']);
		$this->serviceMock->expects($this->once())->method('update')->willReturn(0);
		$this->formBuilderMock->expects($this->never())->method($this->anything());

		static::assertSame(['success' => false, 'errors' => ['No save possible']], $this->orchestrator->storeEditSettings(12, ['name' => 'New']));
	}

	private function expectNoFormHandling(): void
	{
		$this->formBuilderMock->expects($this->never())->method($this->anything());
		$this->policyMock->expects($this->never())->method($this->anything());
		$this->inputHandlerMock->expects($this->never())->method($this->anything());
	}
}
