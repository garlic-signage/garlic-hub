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

use App\Framework\Database\BaseRepositories\Transactions;
use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Shared\Acl\AclGrantService;
use App\Shared\Acl\AclHelper;
use App\Shared\Acl\AclSections;
use App\Shared\Acl\AclUnitProvider;
use App\Shared\User\Repositories\Edge\UserAclRepository;
use App\Shared\User\Repositories\Edge\UserVipRepository;
use App\Shared\User\UsersService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * Granting rights in the Edge edition: only the module admin grants, units only to users with the role bit.
 * Fixtures: 1 admin (module admin of all modules), 2 anna and 3 bernd without rights.
 * The player module has the units 20 and 21 of the admin.
 */
#[Group('acl')]
class AclGrantServiceTest extends ContainerTestCase
{
	private AclGrantService $service;
	private AclHelper $aclHelper;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');

		$units = new class implements AclUnitProvider
		{
			public function findUnit(int|string $unitId): array
			{
				return in_array((string) $unitId, ['20', '21'], true) ? ['UID' => 1, 'company_id' => 1] : [];
			}
		};

		$this->aclHelper = $this->container->get(AclHelper::class);
		$this->service   = new AclGrantService(
			$this->aclHelper,
			new UserAclRepository($this->connection),
			new UserVipRepository($this->connection),
			$this->container->get(UsersService::class),
			$this->container->get(Transactions::class),
			['player' => $units]
		);
	}

	public function testTheRolesOfAModuleComeFromItsConfigWithoutSubAdminInEdge(): void
	{
		static::assertSame([AclSections::MODULEADMIN, AclSections::EDITOR, AclSections::VIEWER], $this->service->getModuleRoles('player'));
		static::assertSame([AclSections::MODULEADMIN], $this->service->getModuleRoles('users'));
	}

	public function testModuleAdminSetsAndRemovesARole(): void
	{
		$this->service->setRole(1, 2, 'player', AclSections::EDITOR, true);
		$this->service->setRole(1, 2, 'player', AclSections::VIEWER, true);

		static::assertSame(3, $this->aclOf(2, 'player'));
		static::assertTrue($this->aclHelper->isEditor(2, 'player'));

		$this->service->setRole(1, 2, 'player', AclSections::EDITOR, false);

		static::assertSame(1, $this->aclOf(2, 'player'));
		static::assertFalse($this->aclHelper->isEditor(2, 'player'));

		$this->service->setRole(1, 2, 'player', AclSections::VIEWER, false);

		static::assertSame(0, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM user_acl WHERE UID = 2"));
	}

	public function testModuleAdminGrantsModuleAdmin(): void
	{
		$this->service->setRole(1, 2, 'player', AclSections::MODULEADMIN, true);

		static::assertTrue($this->aclHelper->isModuleAdmin(2, 'player'));
	}

	public function testUserWithoutRightsMayNotGrant(): void
	{
		$this->expectException(AccessDeniedException::class);
		try
		{
			$this->service->setRole(2, 3, 'player', AclSections::EDITOR, true);
		}
		finally
		{
			static::assertSame(0, $this->aclOf(3, 'player'));
		}
	}

	public function testNobodyChangesHisOwnRights(): void
	{
		$this->expectException(AccessDeniedException::class);
		$this->service->setRole(1, 1, 'player', AclSections::MODULEADMIN, false);
	}

	public function testARoleTheModuleDoesNotKnowIsRefused(): void
	{
		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('role_not_available');
		$this->service->setRole(1, 2, 'users', AclSections::EDITOR, true);
	}

	public function testUnknownUserIsNotFound(): void
	{
		$this->expectException(NotFoundException::class);
		$this->service->setRole(1, 99, 'player', AclSections::EDITOR, true);
	}

	public function testAUnitNeedsTheRoleBitFirst(): void
	{
		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('user_without_role');
		try
		{
			$this->service->grantUnit(1, 2, 'player', AclSections::EDITOR, 20);
		}
		finally
		{
			static::assertSame(0, $this->countVips(2));
		}
	}

	public function testGrantingAUnitTakesEffectAtOnce(): void
	{
		$this->service->setRole(1, 2, 'player', AclSections::EDITOR, true);
		static::assertFalse($this->aclHelper->hasEditorAccessOnUnit(2, 20, 'player'));

		$this->service->grantUnit(1, 2, 'player', AclSections::EDITOR, 20);

		static::assertTrue($this->aclHelper->hasEditorAccessOnUnit(2, 20, 'player'));
		static::assertFalse($this->aclHelper->hasEditorAccessOnUnit(2, 21, 'player'));
	}

	public function testGrantingAUnitTwiceKeepsOneRow(): void
	{
		$this->service->setRole(1, 2, 'player', AclSections::EDITOR, true);

		$this->service->grantUnit(1, 2, 'player', AclSections::EDITOR, 20);
		$this->service->grantUnit(1, 2, 'player', AclSections::EDITOR, '20');

		static::assertSame(1, $this->countVips(2));
	}

	public function testAnUnknownUnitIsNotFound(): void
	{
		$this->service->setRole(1, 2, 'player', AclSections::EDITOR, true);

		$this->expectException(NotFoundException::class);
		$this->service->grantUnit(1, 2, 'player', AclSections::EDITOR, 99);
	}

	public function testOnlyEditorsAndViewersHaveUnits(): void
	{
		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('role_not_available');
		$this->service->grantUnit(1, 2, 'player', AclSections::MODULEADMIN, 20);
	}

	public function testUserWithoutRightsMayNotGrantAUnit(): void
	{
		$this->service->setRole(1, 3, 'player', AclSections::VIEWER, true);

		$this->expectException(AccessDeniedException::class);
		$this->service->grantUnit(2, 3, 'player', AclSections::VIEWER, 20);
	}

	public function testRevokingAUnit(): void
	{
		$this->service->setRole(1, 2, 'player', AclSections::VIEWER, true);
		$this->service->grantUnit(1, 2, 'player', AclSections::VIEWER, 20);
		$this->service->grantUnit(1, 2, 'player', AclSections::VIEWER, 21);

		$this->service->revokeUnit(1, 2, 'player', AclSections::VIEWER, 20);

		static::assertFalse($this->aclHelper->hasViewerAccessOnUnit(2, 20, 'player'));
		static::assertTrue($this->aclHelper->hasViewerAccessOnUnit(2, 21, 'player'));
		static::assertTrue($this->aclHelper->isViewer(2, 'player'), 'The bit stays when units are revoked.');
	}

	public function testRemovingARoleRemovesOnlyItsUnits(): void
	{
		$this->service->setRole(1, 2, 'player', AclSections::EDITOR, true);
		$this->service->setRole(1, 2, 'player', AclSections::VIEWER, true);
		$this->service->grantUnit(1, 2, 'player', AclSections::EDITOR, 20);
		$this->service->grantUnit(1, 2, 'player', AclSections::VIEWER, 21);

		$this->service->setRole(1, 2, 'player', AclSections::EDITOR, false);

		static::assertSame(['21'], $this->service->findUnitsOfUser(1, 2, 'player', AclSections::VIEWER));
		static::assertSame([], $this->service->findUnitsOfUser(1, 2, 'player', AclSections::EDITOR));
	}

	public function testTheShareDialogListsOnlyUsersWithTheRoleBit(): void
	{
		$this->service->setRole(1, 2, 'player', AclSections::EDITOR, true);
		$this->service->setRole(1, 3, 'player', AclSections::VIEWER, true);

		$editors = $this->service->findUsersWithRole(1, 'player', AclSections::EDITOR);

		static::assertSame([['UID' => 2, 'username' => 'anna', 'company_id' => 1]], $editors);
	}

	public function testUserWithoutRightsMayNotListUsers(): void
	{
		$this->expectException(AccessDeniedException::class);
		$this->service->findUsersWithRole(2, 'player', AclSections::EDITOR);
	}

	public function testTheUsersOfAUnit(): void
	{
		$this->service->setRole(1, 2, 'player', AclSections::EDITOR, true);
		$this->service->setRole(1, 3, 'player', AclSections::VIEWER, true);
		$this->service->grantUnit(1, 2, 'player', AclSections::EDITOR, 20);
		$this->service->grantUnit(1, 3, 'player', AclSections::VIEWER, 20);
		$this->service->grantUnit(1, 3, 'player', AclSections::VIEWER, 21);

		$users = $this->service->findUsersOfUnit(1, 'player', 20);

		static::assertSame([['UID' => 2, 'role' => AclSections::EDITOR], ['UID' => 3, 'role' => AclSections::VIEWER]], $users);
	}

	public function testAUnitOfAModuleWithoutProviderFails(): void
	{
		$this->expectException(ModuleException::class);
		$this->service->findUsersOfUnit(1, 'playlists', 20);
	}

	private function aclOf(int $UID, string $module): int
	{
		return (int) $this->connection->fetchOne('SELECT acl FROM user_acl WHERE UID = ? AND module = ?', [$UID, $module]);
	}

	private function countVips(int $UID): int
	{
		return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM user_vip WHERE UID = ?', [$UID]);
	}
}
