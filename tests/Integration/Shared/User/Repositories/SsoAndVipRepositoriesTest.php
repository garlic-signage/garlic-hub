<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Integration\Shared\User\Repositories;

use App\Shared\User\Repositories\Core\SsoProvidersRepository;
use App\Shared\User\Repositories\Core\UserSsoRepository;
use App\Shared\User\Repositories\Core\UserVipRepository;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * What the repositories of the SSO and of the shared units read and delete: only rows of the asked user or provider.
 * Users: 1 admin, 2 anna, 3 bernd.
 */
#[Group('auth')]
class SsoAndVipRepositoriesTest extends ContainerTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		foreach ([[1, 'Zeta', 'https://zeta.example.test'], [2, 'Alpha', 'https://alpha.example.test'], [3, 'Broken', '']] as [$id, $name, $issuer])
			$this->connection->insert('sso_providers', ['provider_id' => $id, 'company_id' => 1, 'name' => $name, 'issuer' => $issuer, 'client_id' => 'hub', 'client_secret' => '']);
	}

	public function testTheProvidersOfACompanyAreSortedByNameWithoutBrokenRows(): void
	{
		$providers = new SsoProvidersRepository($this->connection)->findAllOfCompany(1);

		static::assertSame(['Alpha', 'Zeta'], array_map(static fn($provider): string => $provider->name, $providers));
	}

	public function testDeletingAProviderRemovesOnlyItsIdentities(): void
	{
		$repository = new UserSsoRepository($this->connection);
		$repository->link(2, 1, 'subject-anna');
		$repository->link(3, 2, 'subject-bernd');

		$repository->removeOfProvider(1);

		static::assertNull($repository->findUID(1, 'subject-anna'));
		static::assertSame(3, $repository->findUID(2, 'subject-bernd'));
	}

	public function testTheUnitsOfAUserAreOnlyHisSortedByTheirId(): void
	{
		foreach ([[2, '3'], [2, '1'], [3, '2']] as [$UID, $unitId])
			$this->connection->insert('user_vip', ['UID' => $UID, 'vip_name' => 'playlists_editor', 'unit_id' => $unitId]);

		static::assertSame(['1', '3'], new UserVipRepository($this->connection)->findUnitIds(2, 'playlists_editor'));
	}
}
