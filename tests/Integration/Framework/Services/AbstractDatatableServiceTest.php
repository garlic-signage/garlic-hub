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

namespace Tests\Integration\Framework\Services;

use App\Framework\Database\Datatable\DatatableQuery;
use App\Framework\Database\Datatable\FilterConditions;
use App\Framework\Services\AbstractDatatableService;
use App\Framework\Utils\FormParameters\BaseParameters;
use App\Modules\Templates\Helper\Datatable\TemplatesFilterDefinition;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use Psr\Log\NullLogger;
use Tests\Integration\Support\RepositoryTestCase;

/**
 * The datatable service with the real query on the real schema and the filter definition of the templates.
 * Fixtures: users 1 admin, 2 anna, 3 bernd. Templates: 1 Admin Vorlage (UID 1), 2 Annas Vorlage (UID 2),
 * 3 Bernds Vorlage (UID 3), 4 Annas zweite (UID 2).
 */
#[Group('db')]
class AbstractDatatableServiceTest extends RepositoryTestCase
{
	private TemplatesFilterDefinition $definition;
	private DatatableTestService $service;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('templates/templates.sql');
		$this->connection->executeStatement("INSERT INTO templates (template_id, UID, visibility, name) VALUES (4, 2, 'private', 'Annas zweite')");

		$conditions       = new FilterConditions();
		$this->definition = new TemplatesFilterDefinition($conditions);
		$this->service    = new DatatableTestService(new NullLogger(), new DatatableQuery($this->connection));
	}

	/**
	 * @param array<string,mixed> $values
	 */
	private function parameters(array $values = []): BaseParameters&Stub
	{
		$fields = [];
		foreach ($values as $key => $value)
			$fields[$key] = ['value' => $value];

		$stub = $this->createStub(BaseParameters::class);
		$stub->method('getInputParametersArray')->willReturn($fields);

		return $stub;
	}

	/**
	 * @return list<int>
	 */
	private function ids(): array
	{
		return array_map('intval', array_column($this->service->getCurrentFilterResults(), 'template_id'));
	}

	/**
	 * @throws Exception
	 */
	public function testTheModuleAdminSeesEveryRow(): void
	{
		$this->service->fetchForModuleAdmin($this->definition, $this->parameters());

		static::assertSame(4, $this->service->getCurrentTotalResult());
		static::assertEqualsCanonicalizing([1, 2, 3, 4], $this->ids());
	}

	/**
	 * @throws Exception
	 */
	public function testAUserSeesOnlyTheOwnRows(): void
	{
		$this->service->fetchForUser($this->definition, $this->parameters(), 2);

		static::assertSame(2, $this->service->getCurrentTotalResult());
		static::assertEqualsCanonicalizing([2, 4], $this->ids());
	}

	/**
	 * @throws Exception
	 */
	public function testTheFiltersOfTheParametersReachTheQuery(): void
	{
		$this->service->fetchForModuleAdmin($this->definition, $this->parameters(['name' => 'zweite']));

		static::assertSame(1, $this->service->getCurrentTotalResult());
		static::assertSame([4], $this->ids());
	}

	/**
	 * @throws Exception
	 */
	public function testAUserWithoutRowsGetsAnEmptyResult(): void
	{
		$this->service->fetchForUser($this->definition, $this->parameters(), 99);

		static::assertSame(0, $this->service->getCurrentTotalResult());
		static::assertSame([], $this->service->getCurrentFilterResults());
	}

	/**
	 * @throws Exception
	 */
	public function testTheNextFetchReplacesTheResult(): void
	{
		$this->service->fetchForModuleAdmin($this->definition, $this->parameters());
		$this->service->fetchForUser($this->definition, $this->parameters(), 3);

		static::assertSame(1, $this->service->getCurrentTotalResult());
		static::assertSame([3], $this->ids());
	}
}

class DatatableTestService extends AbstractDatatableService
{
	public function loadDatatable(): void {}
}
