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

namespace Tests\Unit\Modules\System\Services;

use App\Framework\Database\Datatable\DatatableQuery;
use App\Framework\Validation\Sanitizer;
use App\Modules\System\Helper\Audit\AuditFilterDefinition;
use App\Modules\System\Helper\Audit\Parameters;
use App\Modules\System\Services\AuditDatatableService;
use App\Shared\Acl\AclUnitProvider;
use App\Shared\Audit\AuditLogRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Tests\Support\MemorySession;

/**
 * Which names the service asks for. The names from the database are tested in tests/Integration/Modules/System/AuditListTest.
 */
class AuditDatatableServiceTest extends TestCase
{
	/** @var array<string,array<mixed>> the ids asked for: users or the module */
	private array $askedFor = [];

	/**
	 * @param list<array<string,mixed>> $entries
	 * @return list<array<string,mixed>>
	 */
	private function load(array $entries): array
	{
		$queryStub = $this->createStub(DatatableQuery::class);
		$queryStub->method('countForModuleAdmin')->willReturn(count($entries));
		$queryStub->method('findForModuleAdmin')->willReturn($entries);

		$repositoryStub = $this->createStub(AuditLogRepository::class);
		$repositoryStub->method('findUsernames')->willReturnCallback(function (array $UIDs): array {
			$this->askedFor['users'] = $UIDs;
			return [3 => 'bernd'];
		});
		$providerStub = $this->createStub(AclUnitProvider::class);
		$providerStub->method('findUnitsByIds')->willReturnCallback(function (array $ids): array {
			$this->askedFor['playlists'] = $ids;
			return ['5' => ['id' => '5', 'name' => 'Lobby']];
		});

		$parameters = new Parameters(new Sanitizer(), new MemorySession());
		$parameters->setUserInputs([]);
		$parameters->parseInputFilterAllUsers([]);
		$service = new AuditDatatableService(new AuditFilterDefinition(), $queryStub, $parameters, $repositoryStub, ['playlists' => $providerStub], new NullLogger());

		$service->loadDatatable();

		return $service->getCurrentFilterResults();
	}

	#[Group('units')]
	public function testEveryNameIsAskedForOnce(): void
	{
		$results = $this->load([
			['target_UID' => 3, 'unit_id' => '5', 'module' => 'playlists'],
			['target_UID' => '4', 'unit_id' => '6', 'module' => 'playlists'],
			['target_UID' => 3, 'unit_id' => '5', 'module' => 'playlists'],
			['target_UID' => null, 'unit_id' => '7', 'module' => 'mediapool'],
		]);

		static::assertSame(['users' => [3, 4], 'playlists' => ['5', '6']], $this->askedFor);
		static::assertSame([
			['bernd', 'Lobby'],
			[null, null],
			['bernd', 'Lobby'],
			[null, null]
		], array_map(static fn(array $entry): array => [$entry['target_username'], $entry['unit_name']], $results));
	}

	#[Group('units')]
	public function testTheIdOfAUserFromTheDatabaseIsANumber(): void
	{
		$results = $this->load([['target_UID' => '3', 'unit_id' => null, 'module' => 'playlists']]);

		static::assertSame([3], $this->askedFor['users']);
		static::assertSame('bernd', $results[0]['target_username']);
	}

	#[Group('units')]
	public function testAUnitNeedsItsModule(): void
	{
		$results = $this->load([['target_UID' => null, 'unit_id' => '5', 'module' => null]]);

		static::assertSame(['users' => []], $this->askedFor);
		static::assertNull($results[0]['unit_name']);
	}
}
