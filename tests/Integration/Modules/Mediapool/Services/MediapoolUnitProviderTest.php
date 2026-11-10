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

namespace Tests\Integration\Modules\Mediapool\Services;

use App\Modules\Mediapool\Repositories\NodesRepository;
use App\Modules\Mediapool\Services\MediapoolUnitProvider;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\RepositoryTestCase;

/**
 * A directory is shown with its path in the share dialog: "anna / Fotos". Fixtures: anna 7 with 8 Fotos and
 * 9 Archiv, bernd 10 with 11 Privat and 12 Geteilt.
 */
#[Group('db')]
class MediapoolUnitProviderTest extends RepositoryTestCase
{
	private MediapoolUnitProvider $provider;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('mediapool/nodes.sql');
		$this->provider = new MediapoolUnitProvider($this->connection, new NodesRepository($this->connection));
	}

	/**
	 * @throws Exception
	 */
	public function testEveryFoundDirectoryIsNamedByItsPath(): void
	{
		static::assertSame(
			['anna / Archiv', 'bernd / Privat'],
			array_column($this->provider->searchUnits('iv', 10), 'name')
		);
	}

	/**
	 * @throws Exception
	 */
	public function testDirectoriesByIdAreNamedByTheirPath(): void
	{
		static::assertSame(
			['8' => 'anna / Fotos', '12' => 'bernd / Geteilt'],
			array_map(static fn(array $unit): string => $unit['name'], $this->provider->findUnitsByIds(['8', '12']))
		);
	}
}
