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

namespace Tests\Integration\Shared\Acl;

use App\Framework\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Shared\Acl\AclHelper;
use App\Shared\Acl\EditionAclModules;
use App\Modules\Users\Services\AclValidator;
use App\Shared\User\Repositories\Edge\UserAclRepository;
use Phpfastcache\Helper\Psr16Adapter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * The rights of the users with the real configuration (the bits of the modules) and the table of the rights.
 * Users: 1 admin (module admin of all modules), 2 anna and 3 bernd without rights.
 */
#[Group('acl')]
class AclRightsTest extends ContainerTestCase
{
	private AclHelper $acl;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->acl = $this->container->get(AclHelper::class);
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function modules(): array
	{
		return ['users' => ['users'], 'mediapool' => ['mediapool'], 'player' => ['player'], 'playlists' => ['playlists'], 'templates' => ['templates']];
	}

	#[DataProvider('modules')]
	public function testTheAdminIsModuleAdminOfEveryModuleButNothingElse(string $module): void
	{
		static::assertTrue($this->acl->isModuleAdmin(1, $module));
		// the bits of the module admin are no bits of the others
		static::assertFalse($this->acl->isSubAdmin(1, $module));
		static::assertFalse($this->acl->isEditor(1, $module));
		static::assertFalse($this->acl->isViewer(1, $module));
	}

	#[DataProvider('modules')]
	public function testUsersWithoutRightsHaveNoRole(string $module): void
	{
		foreach ([2, 3, 99] as $UID)
		{
			static::assertFalse($this->acl->isModuleAdmin($UID, $module), (string) $UID);
			static::assertFalse($this->acl->isSubAdmin($UID, $module), (string) $UID);
			static::assertFalse($this->acl->isEditor($UID, $module), (string) $UID);
			static::assertFalse($this->acl->isViewer($UID, $module), (string) $UID);
		}
	}

	public function testAModuleWithoutConfigurationIsAnErrorAndNoRight(): void
	{
		$this->expectException(CoreException::class);

		$this->acl->isModuleAdmin(1, 'unknown');
	}

	public function testTheBitsOfAModuleAreTheOnesOfItsConfiguration(): void
	{
		// player: viewer 1, editor 2, sub admin 4, module admin 8; users: sub admin 1, module admin 2
		$this->connection->executeStatement("INSERT INTO user_acl (UID, acl, module) VALUES (2, 1, 'player'), (2, 1, 'users'), (3, 6, 'player'), (3, 2, 'users')");
		$this->container->get(Psr16Adapter::class)->clear();

		static::assertSame([false, false, false, true], [$this->acl->isModuleAdmin(2, 'player'), $this->acl->isSubAdmin(2, 'player'), $this->acl->isEditor(2, 'player'), $this->acl->isViewer(2, 'player')]);
		static::assertSame([false, true], [$this->acl->isModuleAdmin(2, 'users'), $this->acl->isSubAdmin(2, 'users')]);
		static::assertSame([false, true, true, false], [$this->acl->isModuleAdmin(3, 'player'), $this->acl->isSubAdmin(3, 'player'), $this->acl->isEditor(3, 'player'), $this->acl->isViewer(3, 'player')]);
		static::assertSame([true, false], [$this->acl->isModuleAdmin(3, 'users'), $this->acl->isSubAdmin(3, 'users')]);
	}

	public function testTheRightsOfTheFirstAdminAreThoseOfAModuleAdminInEveryModule(): void
	{
		$this->connection->executeStatement('DELETE FROM user_acl');
		$this->container->get(Psr16Adapter::class)->clear();
		$config = $this->container->get(Config::class);

		(new UserAclRepository($this->connection))->addAdminRights($config);

		foreach (EditionAclModules::getModules($config->getEdition()) as $module)
			static::assertTrue($this->acl->isModuleAdmin(1, $module), $module);
		static::assertSame(count(EditionAclModules::getModules($config->getEdition())), (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_acl WHERE UID = 1'));
	}

	public function testInTheEdgeEditionOnlyTheModuleAdminMayAdministrateAUnit(): void
	{
		$this->connection->executeStatement("INSERT INTO user_acl (UID, acl, module) VALUES (2, 1, 'users')");
		$this->container->get(Psr16Adapter::class)->clear();
		$validator = $this->container->get(AclValidator::class);

		static::assertTrue($validator->isAdmin(1, ['UID' => 3, 'company_id' => 1]));
		static::assertFalse($validator->isAdmin(2, ['UID' => 3, 'company_id' => 1]));
		static::assertTrue($validator->isSimpleAdmin(2));
		static::assertFalse($validator->isSimpleAdmin(3));
	}
}
