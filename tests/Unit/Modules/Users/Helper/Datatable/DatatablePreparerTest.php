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
use App\Framework\Utils\Datatable\PrepareService;
use App\Framework\Utils\Datatable\Results\BodyPreparer;
use App\Framework\Utils\Datatable\Results\HeaderField;
use App\Modules\Users\Helper\Datatable\DatatablePreparer;
use App\Modules\Users\Helper\Datatable\Parameters;
use App\Modules\Users\Services\AclValidator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class DatatablePreparerTest extends TestCase
{
	private PrepareService&Stub $prepareServiceStub;
	private AclValidator&MockObject $aclValidatorMock;
	private Translator&MockObject $translatorMock;
	private DatatablePreparer $datatablePreparer;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->prepareServiceStub = $this->createStub(PrepareService::class);
		$this->aclValidatorMock = $this->createMock(AclValidator::class);
		$parametersStub = $this->createStub(Parameters::class);
		$this->translatorMock = $this->createMock(Translator::class);

		$this->datatablePreparer = new DatatablePreparer(
			$this->prepareServiceStub,
			$this->aclValidatorMock,
			$parametersStub,
			$this->translatorMock
		);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testPrepareTableBodyWithValidData(): void
	{
		$fields = [$this->createStub(HeaderField::class)];
		$fields[0]->method('getName')->willReturn('username');
		$fields[0]->method('isSortable')->willReturn(true);

		// link title of the username and label of the edit action
		$this->translatorMock->expects($this->exactly(2))->method('translate')
			->willReturnMap([['edit', 'main', [], 'Edit'], ['edit_settings', 'users', [], 'Settings']]);
		$this->aclValidatorMock->method('isSimpleAdmin')
			->with(123)
			->willReturn(true);

		$this->prepareServiceStub->method('getBodyPreparer')
			->willReturn($this->createStub(BodyPreparer::class));

		$result = $this->datatablePreparer->prepareTableBody(
			[['UID' => 1, 'username' => 'testUser']],
			$fields,
			123
		);

		static::assertCount(1, $result);
		static::assertArrayHasKey('UNIT_ID', $result[0]);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testPrepareTableBodyWithEmptyData(): void
	{
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
		$this->translatorMock->expects($this->never())->method($this->anything());
		$result = $this->datatablePreparer->prepareTableBody([], [], 123);

		static::assertEmpty($result);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testPrepareTableBodyWithAdminPermissions(): void
	{
		$fields = [$this->createStub(HeaderField::class)];
		$fields[0]->method('getName')->willReturn('username');
		$fields[0]->method('isSortable')->willReturn(true);

		$this->aclValidatorMock->expects($this->atLeastOnce())->method('isModuleAdmin')->willReturn(true);

		$this->aclValidatorMock->expects($this->atLeastOnce())->method('isSimpleAdmin')->willReturn(true);

		$this->translatorMock->expects($this->atLeastOnce())->method('translate')
			->willReturnCallback(fn(string $key, string $module) => $module . '.' . $key);

		$actionLink = ['LINK_ACTION' => 'users/edit/1'];
		$actionDelete = ['DELETE_ID' => '1'];
		$bodyPreparerMock = $this->createMock(BodyPreparer::class);
		$bodyPreparerMock->expects($this->once())->method('formatActionLink')
			->with('users.edit_settings', 'users/edit/1', 'edit', '1', 'pencil')
			->willReturn($actionLink);
		$bodyPreparerMock->expects($this->once())->method('formatActionDelete')
			->with('main.delete', 'users.confirm_delete', '1', 'delete-user')
			->willReturn($actionDelete);
		$this->prepareServiceStub->method('getBodyPreparer')->willReturn($bodyPreparerMock);

		$result = $this->datatablePreparer->prepareTableBody(
			[['UID' => 1, 'username' => 'adminUser', 'status' => 0]],
			$fields,
			123
		);

		static::assertSame([$actionLink], $result[0]['has_action_link']);
		static::assertSame($actionDelete, $result[0]['has_delete']);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testPrepareTableBodyWithPlatformEditionEdge(): void
	{
		$this->translatorMock->expects($this->never())->method($this->anything());
		$fields = [$this->createStub(HeaderField::class)];
		$fields[0]->method('getName')->willReturn('username');
		$fields[0]->method('isSortable')->willReturn(true);

		$configStub = $this->createStub(Config::class);
		$configStub->method('getEdition')->willReturn(Config::PLATFORM_EDITION_EDGE);

		$this->aclValidatorMock->expects($this->atLeastOnce())->method('getConfig')->willReturn($configStub);

		$bodyPreparerMock = $this->createMock(BodyPreparer::class);

		$bodyPreparerMock->method('formatText')
			->with('edgeUsername')
			->willReturn(['formattedText' => 'edgeUsername']);

		$this->prepareServiceStub->method('getBodyPreparer')->willReturn($bodyPreparerMock);

		$result = $this->datatablePreparer->prepareTableBody(
			[['UID' => 1, 'username' => 'edgeUsername']],
			$fields,
			123
		);

		static::assertEquals('edgeUsername', $result[0]['elements_result_element'][0]['is_text']['formattedText']);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testPrepareTableBodyWithStatus(): void
	{
		$fields = [$this->createStub(HeaderField::class)];
		$fields[0]->method('getName')->willReturn('status');
		$fields[0]->method('isSortable')->willReturn(true);

		$configStub = $this->createStub(Config::class);
		$configStub->method('getEdition')->willReturn(Config::PLATFORM_EDITION_EDGE);

		$this->aclValidatorMock->method('getConfig')->willReturn($configStub);
		// not the own user and no admin: no actions for this row
		$this->aclValidatorMock->expects($this->once())->method('isSimpleAdmin')->with(123)->willReturn(false);

		$status = ['deleted' => 'Deleted', 'banned' => 'Banned', 'inactive' => 'Inactive', 'active' => 'Active'];
		$this->translatorMock->method('translateArrayForOptions')
			->with('status_selects', 'users')
			->willReturn($status);

		$bodyPreparerMock = $this->createMock(BodyPreparer::class);
		$bodyPreparerMock->method('formatText')
			->with('Active')
			->willReturn(['formattedText' => 'Active']);

		$this->prepareServiceStub
			->method('getBodyPreparer')
			->willReturn($bodyPreparerMock);

		$result = $this->datatablePreparer->prepareTableBody(
			[['UID' => 1, 'status' => 'active']],
			$fields,
			123
		);

		static::assertEquals('Active', $result[0]['elements_result_element'][0]['is_text']['formattedText']);
	}


	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testPrepareTableBodyWithUnknown(): void
	{
		$fields = [$this->createStub(HeaderField::class)];
		$fields[0]->method('getName')->willReturn('unknown_param');
		$fields[0]->method('isSortable')->willReturn(false);

		$configStub = $this->createStub(Config::class);
		$configStub->method('getEdition')->willReturn(Config::PLATFORM_EDITION_EDGE);

		$this->aclValidatorMock->method('getConfig')->willReturn($configStub);
		// not the own user and no admin: no actions for this row
		$this->aclValidatorMock->expects($this->once())->method('isSimpleAdmin')->with(123)->willReturn(false);
		$this->translatorMock->expects($this->never())->method($this->anything());

		$bodyPreparerMock = $this->createMock(BodyPreparer::class);
		$bodyPreparerMock->method('formatText')
			->with('some_value')
			->willReturn(['formattedText' => 'some_value']);

		$this->prepareServiceStub->method('getBodyPreparer')->willReturn($bodyPreparerMock);

		$result = $this->datatablePreparer->prepareTableBody(
			[['UID' => 1, 'unknown_param' => 'some_value']],
			$fields,
			123
		);

		static::assertEquals('some_value', $result[0]['elements_result_element'][0]['is_text']['formattedText']);
	}


}
