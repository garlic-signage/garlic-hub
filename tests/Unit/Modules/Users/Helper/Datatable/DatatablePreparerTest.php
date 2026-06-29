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

use App\Framework\Core\Config\Config;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Datatable\PrepareService;
use App\Framework\Utils\Datatable\Results\BodyPreparer;
use App\Framework\Utils\Datatable\Results\HeaderField;
use App\Modules\Users\Helper\Datatable\DatatablePreparer;
use App\Modules\Users\Helper\Datatable\Parameters;
use App\Modules\Users\Services\AclValidator;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\InvalidArgumentException;

class DatatablePreparerTest extends TestCase
{
	private PrepareService&MockObject $prepareServiceMock;
	private AclValidator&MockObject $aclValidatorMock;
	private Translator&MockObject $translatorMock;
	private DatatablePreparer $datatablePreparer;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->prepareServiceMock = $this->createMock(PrepareService::class);
		$this->aclValidatorMock = $this->createMock(AclValidator::class);
		$parametersMock = $this->createMock(Parameters::class);
		$this->translatorMock = $this->createMock(Translator::class);

		$this->datatablePreparer = new DatatablePreparer(
			$this->prepareServiceMock,
			$this->aclValidatorMock,
			$parametersMock
		);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testPrepareTableBodyWithValidData(): void
	{
		$fields = [$this->createMock(HeaderField::class)];
		$fields[0]->method('getName')->willReturn('username');
		$fields[0]->method('isSortable')->willReturn(true);

		$this->datatablePreparer->setTranslator($this->translatorMock);
		$this->aclValidatorMock->method('isSimpleAdmin')
			->with(123)
			->willReturn(true);

		$this->prepareServiceMock->method('getBodyPreparer')
			->willReturn($this->createMock(BodyPreparer::class));

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
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testPrepareTableBodyWithEmptyData(): void
	{
		$result = $this->datatablePreparer->prepareTableBody([], [], 123);

		static::assertEmpty($result);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testPrepareTableBodyWithAdminPermissions(): void
	{
		$fields = [$this->createMock(HeaderField::class)];
		$fields[0]->method('getName')->willReturn('username');
		$fields[0]->method('isSortable')->willReturn(true);
		$this->datatablePreparer->setTranslator($this->translatorMock);

		$this->aclValidatorMock->method('isModuleAdmin')->willReturn(true);

		$this->aclValidatorMock->method('isSimpleAdmin')->willReturn(true);

		$result = $this->datatablePreparer->prepareTableBody(
			[['UID' => 1, 'username' => 'adminUser', 'status' => 0]],
			$fields,
			123
		);

		static::assertNotEmpty($result[0]['has_action_link']);
		static::assertNotEmpty($result[0]['has_delete']);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testPrepareTableBodyWithPlatformEditionEdge(): void
	{
		$fields = [$this->createMock(HeaderField::class)];
		$fields[0]->method('getName')->willReturn('username');
		$fields[0]->method('isSortable')->willReturn(true);

		$configMock = $this->createMock(Config::class);
		$configMock->method('getEdition')->willReturn(Config::PLATFORM_EDITION_EDGE);

		$this->aclValidatorMock->method('getConfig')->willReturn($configMock);

		$bodyPreparerMock = $this->createMock(BodyPreparer::class);

		$bodyPreparerMock->method('formatText')
			->with('edgeUsername')
			->willReturn(['formattedText' => 'edgeUsername']);

		$this->prepareServiceMock->method('getBodyPreparer')->willReturn($bodyPreparerMock);

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
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testPrepareTableBodyWithStatus(): void
	{
		$fields = [$this->createMock(HeaderField::class)];
		$fields[0]->method('getName')->willReturn('status');
		$fields[0]->method('isSortable')->willReturn(true);

		$configMock = $this->createMock(Config::class);
		$configMock->method('getEdition')->willReturn(Config::PLATFORM_EDITION_EDGE);

		$this->aclValidatorMock->method('getConfig')->willReturn($configMock);
		$this->datatablePreparer->setTranslator($this->translatorMock);

		$status = ['deleted' => 'Deleted', 'banned' => 'Banned', 'inactive' => 'Inactive', 'active' => 'Active'];
		$this->translatorMock->method('translateArrayForOptions')
			->with('status_selects', 'users')
			->willReturn($status);

		$bodyPreparerMock = $this->createMock(BodyPreparer::class);
		$bodyPreparerMock->method('formatText')
			->with('Active')
			->willReturn(['formattedText' => 'Active']);

		$this->prepareServiceMock
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
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testPrepareTableBodyWithUnknown(): void
	{
		$fields = [$this->createMock(HeaderField::class)];
		$fields[0]->method('getName')->willReturn('unknown_param');
		$fields[0]->method('isSortable')->willReturn(false);

		$configMock = $this->createMock(Config::class);
		$configMock->method('getEdition')->willReturn(Config::PLATFORM_EDITION_EDGE);

		$this->aclValidatorMock->method('getConfig')->willReturn($configMock);
		$this->datatablePreparer->setTranslator($this->translatorMock);

		$bodyPreparerMock = $this->createMock(BodyPreparer::class);
		$bodyPreparerMock->method('formatText')
			->with('some_value')
			->willReturn(['formattedText' => 'some_value']);

		$this->prepareServiceMock->method('getBodyPreparer')->willReturn($bodyPreparerMock);

		$result = $this->datatablePreparer->prepareTableBody(
			[['UID' => 1, 'unknown_param' => 'some_value']],
			$fields,
			123
		);

		static::assertEquals('some_value', $result[0]['elements_result_element'][0]['is_text']['formattedText']);
	}


}
