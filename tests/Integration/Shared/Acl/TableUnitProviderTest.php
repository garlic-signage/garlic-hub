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

namespace Tests\Integration\Shared\Acl;

use App\Shared\Acl\AclUnitProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * The unit providers the modules register in the container find the owner and the company of a unit.
 * Fixtures: 1 admin, 2 anna, 3 bernd, all in company 1; anna moves to company 7 in the test.
 */
#[Group('acl')]
class TableUnitProviderTest extends ContainerTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('acl/players.sql');
		$this->loadFixture('playlists/playlists.sql');
		$this->loadFixture('templates/templates.sql');
		$this->loadFixture('mediapool/nodes.sql');
		$this->connection->executeStatement('UPDATE user_main SET company_id = 7 WHERE UID = 2');
	}

	/**
	 * @return array<string,array{string,int|string}> the module and a unit of anna
	 */
	public static function unitsOfAnna(): array
	{
		return [
			'player'    => ['player', 1],
			'playlists' => ['playlists', 1],
			'templates' => ['templates', '2'],
			'mediapool' => ['mediapool', 8],
		];
	}

	#[DataProvider('unitsOfAnna')]
	public function testFindsTheOwnerAndHisCompany(string $module, int|string $unitId): void
	{
		static::assertSame(['id' => (string) $unitId, 'UID' => 2, 'company_id' => 7], $this->provider($module)->findUnit($unitId));
	}

	public function testAnIdWithALeadingZeroGivesTheStoredId(): void
	{
		static::assertSame(['id' => '1', 'UID' => 2, 'company_id' => 7], $this->provider('playlists')->findUnit('01'));
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function modules(): array
	{
		return ['player' => ['player'], 'playlists' => ['playlists'], 'templates' => ['templates'], 'mediapool' => ['mediapool']];
	}

	#[DataProvider('modules')]
	public function testAnUnknownUnitIsEmpty(string $module): void
	{
		static::assertSame([], $this->provider($module)->findUnit(999));
	}

	public function testTheUnitsAreSearchedByTheirName(): void
	{
		static::assertSame([['id' => '1', 'name' => 'Annas Lobby', 'company_id' => 7]], $this->provider('playlists')->searchUnits('lobby', 10));
		static::assertSame([['id' => '2', 'name' => 'Annas Vorlage', 'company_id' => 7]], $this->provider('templates')->searchUnits('annas', 10));
	}

	public function testTheNamesOfUnitsByTheirIds(): void
	{
		static::assertSame(['3' => ['name' => 'Bernds Player', 'company_id' => 1]], $this->provider('player')->findUnitsByIds(['3', '999']));
		static::assertSame(['1', '3'], array_map('strval', array_keys($this->provider('player')->findUnitsByIds(['1', '3']))), 'every unit found');
		static::assertSame([], $this->provider('player')->findUnitsByIds([]));
	}

	/**
	 * Every user has his own "Fotos", so a directory is named by its path.
	 */
	public function testADirectoryIsNamedByItsPath(): void
	{
		static::assertSame([['id' => '8', 'name' => 'anna / Fotos', 'company_id' => 7]], $this->provider('mediapool')->searchUnits('fotos', 10));
		static::assertSame(['12' => ['name' => 'bernd / Geteilt', 'company_id' => 1]], $this->provider('mediapool')->findUnitsByIds(['12']));
	}

	private function provider(string $module): AclUnitProvider
	{
		$provider = $this->container->get(AclUnitProvider::class . '.' . $module);
		static::assertInstanceOf(AclUnitProvider::class, $provider);

		return $provider;
	}
}
