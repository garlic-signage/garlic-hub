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

namespace Tests\Integration\Shared\User\Repositories;

use App\Framework\Config\Config;
use App\Framework\Config\IniConfigLoader;
use App\Shared\Acl\EditionAclModules;
use App\Shared\User\Repositories\Edge\UserAclRepository;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\RepositoryTestCase;

#[Group('db')]
class UserAclRepositoryTest extends RepositoryTestCase
{
	private UserAclRepository $repository;

	protected function setUp(): void
	{
		parent::setUp();
		$this->repository = new UserAclRepository($this->connection);
		$this->loadFixture('auth/users.sql');
	}

	/**
	 * @throws Exception
	 */
	public function testFindByIdReturnsAllModulesOfTheUser(): void
	{
		$acls = $this->repository->findById(10);

		static::assertCount(2, $acls);
		static::assertEqualsCanonicalizing(['player' => 2, 'playlists' => 1], array_column($acls, 'acl', 'module'));
	}

	/**
	 * @throws Exception
	 */
	public function testFindByIdOfAUserWithoutRights(): void
	{
		static::assertSame([], $this->repository->findById(14));
		static::assertSame([], $this->repository->findById(999));
	}

	/**
	 * @throws Exception
	 */
	public function testAddAdminRightsGivesModuleAdminOfEveryModuleOfTheEdition(): void
	{
		$config = new Config(
			new IniConfigLoader(__DIR__ . '/../../../../../config/settings'),
			[],
			['APP_PLATFORM_EDITION' => Config::PLATFORM_EDITION_EDGE]
		);

		$this->repository->addAdminRights($config, 14);

		$modules = EditionAclModules::getModules(Config::PLATFORM_EDITION_EDGE);
		$acls    = array_column($this->repository->findById(14), 'acl', 'module');
		static::assertEqualsCanonicalizing($modules, array_keys($acls));
		foreach ($modules as $module)
			static::assertSame((int) $config->getConfigValue('moduleadmin', $module, 'GlobalACLs'), (int) $acls[$module]);
		// the other users keep what they had
		static::assertCount(2, $this->repository->findById(10));
	}
}
