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

namespace Tests\Integration\Shared\Versioning;

use App\Shared\Versioning\UnitVersions;
use App\Shared\Versioning\UnitVersionsRepository;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * The record of a version while another save records one at the same time.
 */
#[Group('acl')]
class UnitVersionsTest extends ContainerTestCase
{
	public function testAVersionRecordedAtTheSameTimeIsKept(): void
	{
		$repository = new UnitVersionsRepository($this->connection);
		$repository->insertVersion('templates', '7', 1, 1, '{"content":"first"}');
		// it read the number before the other save stored its version
		$late = new class($this->connection) extends UnitVersionsRepository
		{
			public function findLastVersion(string $module, string $unitId): int
			{
				return 0;
			}
		};

		new UnitVersions($late, true, 10)->record('templates', 7, 2, ['content' => 'second']);

		static::assertSame('{"content":"first"}', $repository->findSnapshot('templates', '7', 1));
		static::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM unit_versions'));
	}

	/**
	 * The save which lost keeps its hands off the versions: the one which won removes the old ones.
	 */
	public function testTheLosingSaveDeletesNoVersion(): void
	{
		$repository = new UnitVersionsRepository($this->connection);
		foreach ([1, 2, 3] as $version)
			$repository->insertVersion('templates', '7', $version, 1, '{"content":"' . $version . '"}');
		$late = new class($this->connection) extends UnitVersionsRepository
		{
			public function findLastVersion(string $module, string $unitId): int
			{
				return 2;
			}
		};

		new UnitVersions($late, true, 1)->record('templates', 7, 2, ['content' => 'second']);

		static::assertSame([1, 2, 3], array_map('intval', $this->connection->fetchFirstColumn('SELECT version FROM unit_versions ORDER BY version')));
	}

	public function testUmlautsAreStoredAsTheyAre(): void
	{
		$repository = new UnitVersionsRepository($this->connection);

		new UnitVersions($repository, true, 10)->record('templates', 7, 1, ['content' => 'Grüße']);

		static::assertSame('{"content":"Grüße"}', $repository->findSnapshot('templates', '7', 1));
	}
}
