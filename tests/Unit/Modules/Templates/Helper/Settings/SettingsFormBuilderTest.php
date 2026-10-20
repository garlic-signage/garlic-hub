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

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\FormParameters\BaseParameters;
use App\Framework\Utils\Html\FieldInterface;
use App\Modules\Auth\UserSession;
use App\Modules\Templates\Helper\Settings\FormElementsCreator;
use App\Modules\Templates\Helper\Settings\Parameters;
use App\Modules\Templates\Helper\Settings\SettingsFormBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class SettingsFormBuilderTest extends TestCase
{
	private Parameters&Stub $parametersStub;
	private FormElementsCreator&MockObject $creatorMock;
	private FieldInterface $field;
	private SettingsFormBuilder $builder;

	/** @var array{hidden: list<array<string,string>>, visible: list<array<string,string>>} */
	private array $prepared = ['hidden' => [], 'visible' => [['name' => 'x']]];

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->parametersStub = $this->createStub(Parameters::class);
		$this->creatorMock    = $this->createMock(FormElementsCreator::class);
		$this->field          = $this->createStub(FieldInterface::class);
		$userSessionStub      = $this->createStub(UserSession::class);
		$userSessionStub->method('getUID')->willReturn(5);
		$userSessionStub->method('getUsername')->willReturn('admin');

		$this->builder = new SettingsFormBuilder($this->parametersStub, $userSessionStub, $this->creatorMock);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildCreateForm(): void
	{
		$this->mockParameters(true);
		$this->creatorMock->expects($this->once())->method('createNameField')->with('')->willReturn($this->field);
		$this->creatorMock->expects($this->once())->method('createUIDField')->with(5, 'admin', 5)->willReturn($this->field);
		$this->creatorMock->expects($this->once())->method('createVisibilityField')->with('')->willReturn($this->field);
		$this->creatorMock->expects($this->once())->method('createHiddenTypeField')->with('canvas')->willReturn($this->field);
		$this->creatorMock->expects($this->never())->method('createHiddenTemplateIdField');
		$this->expectPreparedForm(['name', 'UID', 'visibility', 'type', 'csrf_token']);

		static::assertSame($this->prepared, $this->builder->buildForm([]));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildEditForm(): void
	{
		$template = ['template_id' => '12', 'name' => 'Tpl', 'UID' => 8, 'username' => 'other', 'visibility' => 'public'];

		$this->mockParameters(true);
		$this->creatorMock->expects($this->once())->method('createNameField')->with('Tpl')->willReturn($this->field);
		$this->creatorMock->expects($this->once())->method('createUIDField')->with(8, 'other', 5)->willReturn($this->field);
		$this->creatorMock->expects($this->once())->method('createVisibilityField')->with('public')->willReturn($this->field);
		$this->creatorMock->expects($this->once())->method('createHiddenTemplateIdField')->with(12)->willReturn($this->field);
		$this->creatorMock->expects($this->never())->method('createHiddenTypeField');
		$this->expectPreparedForm(['name', 'UID', 'visibility', 'template_id', 'csrf_token']);

		static::assertSame($this->prepared, $this->builder->buildForm($template));
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildFormWithoutOwnerAndVisibility(): void
	{
		$this->mockParameters(false);
		$this->creatorMock->expects($this->once())->method('createNameField')->willReturn($this->field);
		$this->creatorMock->expects($this->never())->method('createUIDField');
		$this->creatorMock->expects($this->never())->method('createVisibilityField');
		$this->creatorMock->expects($this->once())->method('createHiddenTypeField')->willReturn($this->field);
		$this->expectPreparedForm(['name', 'type', 'csrf_token']);

		static::assertSame($this->prepared, $this->builder->buildForm([]));
	}

	private function mockParameters(bool $hasOptionalFields): void
	{
		$this->parametersStub->method('hasParameter')->willReturnMap([
			[BaseParameters::PARAMETER_UID, $hasOptionalFields],
			[Parameters::PARAMETER_VISIBILITY, $hasOptionalFields],
		]);
	}

	/**
	 * @param list<string> $expectedKeys
	 */
	private function expectPreparedForm(array $expectedKeys): void
	{
		$this->creatorMock->expects($this->once())->method('createCSRFTokenField')->willReturn($this->field);
		$this->creatorMock->expects($this->once())->method('prepareForm')
			->with($this->callback(fn(array $form): bool => array_keys($form) === $expectedKeys))
			->willReturn($this->prepared);
	}
}
