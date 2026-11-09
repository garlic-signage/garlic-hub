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

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Shared\Acl\AclGrantService;
use App\Shared\Acl\AclHelper;
use App\Shared\Acl\AclSections;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * Granting rights in the Core edition: only the module admin grants, units only to users with the role bit.
 * Fixtures: 1 admin (module admin of all modules), 2 anna and 3 bernd without rights.
 * The units are the playlists of acl/playlists.sql: 1 and 2 of anna, 3 of bernd, 4 of the admin.
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
		$this->loadFixture('acl/playlists.sql');

		$this->aclHelper = $this->container->get(AclHelper::class);
		$this->service   = $this->container->get(AclGrantService::class);
	}

	public function testTheRolesOfAModuleComeFromItsConfigWithoutSubAdminInCore(): void
	{
		static::assertSame([AclSections::MODULEADMIN, AclSections::EDITOR, AclSections::VIEWER], $this->service->getModuleRoles('playlists'));
		// a player is not selected anywhere, so there is nothing to use it for: no viewer
		static::assertSame([AclSections::MODULEADMIN, AclSections::EDITOR], $this->service->getModuleRoles('player'));
		static::assertSame([AclSections::MODULEADMIN], $this->service->getModuleRoles('users'));
	}

	public function testModuleAdminSetsAndRemovesARole(): void
	{
		$this->service->setRole(1, 2, 'playlists', AclSections::EDITOR, true);
		$this->service->setRole(1, 2, 'playlists', AclSections::VIEWER, true);

		static::assertSame(3, $this->aclOf(2, 'playlists'));
		static::assertTrue($this->aclHelper->isEditor(2, 'playlists'));

		$this->service->setRole(1, 2, 'playlists', AclSections::EDITOR, false);

		static::assertSame(1, $this->aclOf(2, 'playlists'));
		static::assertFalse($this->aclHelper->isEditor(2, 'playlists'));

		$this->service->setRole(1, 2, 'playlists', AclSections::VIEWER, false);

		static::assertSame(0, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM user_acl WHERE UID = 2"));
	}

	public function testModuleAdminGrantsModuleAdmin(): void
	{
		$this->service->setRole(1, 2, 'playlists', AclSections::MODULEADMIN, true);

		static::assertTrue($this->aclHelper->isModuleAdmin(2, 'playlists'));
	}

	public function testUserWithoutRightsMayNotGrant(): void
	{
		$this->expectException(AccessDeniedException::class);
		try
		{
			$this->service->setRole(2, 3, 'playlists', AclSections::EDITOR, true);
		}
		finally
		{
			static::assertSame(0, $this->aclOf(3, 'playlists'));
		}
	}

	public function testNobodyChangesHisOwnRights(): void
	{
		$this->expectException(AccessDeniedException::class);
		$this->service->setRole(1, 1, 'playlists', AclSections::MODULEADMIN, false);
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
		$this->service->setRole(1, 99, 'playlists', AclSections::EDITOR, true);
	}

	public function testAUnitNeedsTheRoleBitFirst(): void
	{
		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('user_without_role');
		try
		{
			$this->service->setUnitRole(1, 2, 'playlists', 1, AclSections::EDITOR);
		}
		finally
		{
			static::assertSame(0, $this->countVips(2));
		}
	}

	public function testGrantingAUnitTakesEffectAtOnce(): void
	{
		$this->service->setRole(1, 2, 'playlists', AclSections::EDITOR, true);
		static::assertFalse($this->aclHelper->hasEditorAccessOnUnit(2, 1, 'playlists'));

		$this->service->setUnitRole(1, 2, 'playlists', 1, AclSections::EDITOR);

		static::assertTrue($this->aclHelper->hasEditorAccessOnUnit(2, 1, 'playlists'));
		static::assertFalse($this->aclHelper->hasEditorAccessOnUnit(2, 2, 'playlists'));
	}

	public function testGrantingAUnitTwiceKeepsOneRow(): void
	{
		$this->service->setRole(1, 2, 'playlists', AclSections::EDITOR, true);

		$this->service->setUnitRole(1, 2, 'playlists', 1, AclSections::EDITOR);
		$this->service->setUnitRole(1, 2, 'playlists', '1', AclSections::EDITOR);

		static::assertSame(1, $this->countVips(2));
	}

	public function testAUnitIdWithALeadingZeroIsStoredAsTheTableStoresIt(): void
	{
		$this->service->setRole(1, 2, 'playlists', AclSections::EDITOR, true);

		$this->service->setUnitRole(1, 2, 'playlists', '01', AclSections::EDITOR);

		static::assertTrue($this->aclHelper->hasEditorAccessOnUnit(2, 1, 'playlists'));
		static::assertSame([['UID' => 2, 'role' => AclSections::EDITOR]], $this->service->findUsersOfUnit(1, 'playlists', '01'));

		$this->service->removeUnitShares('playlists', [1]);
		static::assertSame(0, $this->countVips(2));
	}

	public function testAnUnknownUnitIsNotFound(): void
	{
		$this->service->setRole(1, 2, 'playlists', AclSections::EDITOR, true);

		$this->expectException(NotFoundException::class);
		$this->service->setUnitRole(1, 2, 'playlists', 99, AclSections::EDITOR);
	}

	public function testOnlyEditorsAndViewersHaveUnits(): void
	{
		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('role_not_available');
		$this->service->setUnitRole(1, 2, 'playlists', 1, AclSections::MODULEADMIN);
	}

	public function testUserWithoutRightsMayNotGrantAUnit(): void
	{
		$this->service->setRole(1, 3, 'playlists', AclSections::VIEWER, true);

		$this->expectException(AccessDeniedException::class);
		$this->service->setUnitRole(2, 3, 'playlists', 1, AclSections::VIEWER);
	}

	public function testRevokingAUnit(): void
	{
		$this->service->setRole(1, 2, 'playlists', AclSections::VIEWER, true);
		$this->service->setUnitRole(1, 2, 'playlists', 1, AclSections::VIEWER);
		$this->service->setUnitRole(1, 2, 'playlists', 2, AclSections::VIEWER);

		$this->service->setUnitRole(1, 2, 'playlists', 1, null);

		static::assertFalse($this->aclHelper->hasViewerAccessOnUnit(2, 1, 'playlists'));
		static::assertTrue($this->aclHelper->hasViewerAccessOnUnit(2, 2, 'playlists'));
		static::assertTrue($this->aclHelper->isViewer(2, 'playlists'), 'The bit stays when units are revoked.');
	}

	public function testRemovingARoleRemovesOnlyItsUnits(): void
	{
		$this->service->setRole(1, 2, 'playlists', AclSections::EDITOR, true);
		$this->service->setRole(1, 2, 'playlists', AclSections::VIEWER, true);
		$this->service->setUnitRole(1, 2, 'playlists', 1, AclSections::EDITOR);
		$this->service->setUnitRole(1, 2, 'playlists', 2, AclSections::VIEWER);

		$this->service->setRole(1, 2, 'playlists', AclSections::EDITOR, false);

		static::assertSame([['vip_name' => 'playlists_viewer', 'unit_id' => '2']], $this->connection->fetchAllAssociative('SELECT vip_name, unit_id FROM user_vip WHERE UID = 2'));
	}

	public function testTheShareDialogListsOnlyUsersWithTheRoleBit(): void
	{
		$this->service->setRole(1, 2, 'playlists', AclSections::EDITOR, true);
		$this->service->setRole(1, 3, 'playlists', AclSections::VIEWER, true);

		$editors = $this->service->findUsersWithRole(1, 'playlists', AclSections::EDITOR);

		static::assertSame([['UID' => 2, 'username' => 'anna', 'company_id' => 1]], $editors);
	}

	public function testUserWithoutRightsMayNotListUsers(): void
	{
		$this->expectException(AccessDeniedException::class);
		$this->service->findUsersWithRole(2, 'playlists', AclSections::EDITOR);
	}

	public function testTheUsersOfAUnit(): void
	{
		$this->service->setRole(1, 2, 'playlists', AclSections::EDITOR, true);
		$this->service->setRole(1, 3, 'playlists', AclSections::VIEWER, true);
		$this->service->setUnitRole(1, 2, 'playlists', 1, AclSections::EDITOR);
		$this->service->setUnitRole(1, 3, 'playlists', 1, AclSections::VIEWER);
		$this->service->setUnitRole(1, 3, 'playlists', 2, AclSections::VIEWER);

		$users = $this->service->findUsersOfUnit(1, 'playlists', 1);

		static::assertSame([['UID' => 2, 'role' => AclSections::EDITOR], ['UID' => 3, 'role' => AclSections::VIEWER]], $users);
	}

	public function testAUnitOfAModuleWithoutProviderFails(): void
	{
		$this->expectException(ModuleException::class);
		$this->service->findUsersOfUnit(1, 'users', 1);
	}

	public function testANewRoleOnAUnitReplacesTheOtherOne(): void
	{
		$this->service->setRole(1, 2, 'playlists', AclSections::EDITOR, true);
		$this->service->setRole(1, 2, 'playlists', AclSections::VIEWER, true);
		$this->service->setUnitRole(1, 2, 'playlists', 1, AclSections::VIEWER);

		$this->service->setUnitRole(1, 2, 'playlists', 1, AclSections::EDITOR);

		static::assertSame(1, $this->countVips(2));
		static::assertTrue($this->aclHelper->hasEditorAccessOnUnit(2, 1, 'playlists'));
		static::assertFalse($this->aclHelper->hasViewerAccessOnUnit(2, 1, 'playlists'));
	}

	public function testTheShareDialogOfAUnit(): void
	{
		$this->service->setRole(1, 2, 'playlists', AclSections::EDITOR, true);
		$this->service->setRole(1, 2, 'playlists', AclSections::VIEWER, true);
		$this->service->setRole(1, 3, 'playlists', AclSections::VIEWER, true);
		$this->service->setUnitRole(1, 3, 'playlists', 1, AclSections::VIEWER);

		$shares = $this->service->findUnitShares(1, 'playlists', 1);

		static::assertSame([AclSections::VIEWER, AclSections::EDITOR], $shares['roles']);
		static::assertSame([
			['UID' => 2, 'username' => 'anna', 'roles' => [AclSections::VIEWER, AclSections::EDITOR], 'role' => null],
			['UID' => 3, 'username' => 'bernd', 'roles' => [AclSections::VIEWER], 'role' => AclSections::VIEWER],
		], $shares['users']);
	}

	public function testUserWithoutRightsMayNotOpenTheShareDialog(): void
	{
		$this->expectException(AccessDeniedException::class);
		$this->service->findUnitShares(2, 'playlists', 1);
	}

	public function testOnlyTheAdminMayShareAUnit(): void
	{
		static::assertTrue($this->service->canShareUnit(1, 'playlists', 1));
		static::assertFalse($this->service->canShareUnit(2, 'playlists', 1));
		static::assertFalse($this->service->canShareUnit(1, 'playlists', 99), 'An unknown unit can not be shared.');
		static::assertFalse($this->service->canShareUnit(1, 'users', 1), 'The module users has no units.');
	}

	public function testOnlyTheAdminMayShareInACompany(): void
	{
		static::assertTrue($this->service->canShareInCompany(1, 'mediapool', 1));
		static::assertFalse($this->service->canShareInCompany(2, 'mediapool', 1));
		static::assertFalse($this->service->canShareInCompany(1, 'users', 1), 'The module users has no units.');
	}

	public function testTheAdminGetsATabForEveryModuleAndAUserNone(): void
	{
		static::assertSame(['users', 'mediapool', 'player', 'playlists', 'templates', 'system'], $this->service->findGrantableModules(1, 1));
		static::assertSame([], $this->service->findGrantableModules(2, 1));
	}

	public function testTheRightsOfAUserInAModule(): void
	{
		$this->service->setRole(1, 2, 'playlists', AclSections::EDITOR, true);
		$this->service->setRole(1, 2, 'playlists', AclSections::VIEWER, true);
		$this->service->setUnitRole(1, 2, 'playlists', 4, AclSections::EDITOR);
		$this->service->setUnitRole(1, 2, 'playlists', 3, AclSections::VIEWER);

		$rights = $this->service->findUserRights(1, 2, 'playlists');

		static::assertSame([
			['role' => AclSections::MODULEADMIN, 'granted' => false],
			['role' => AclSections::EDITOR, 'granted' => true],
			['role' => AclSections::VIEWER, 'granted' => true],
		], $rights['roles']);
		static::assertSame([
			['id' => '4', 'name' => 'Admin Playlist', 'role' => AclSections::EDITOR],
			['id' => '3', 'name' => 'Bernds Playlist', 'role' => AclSections::VIEWER],
		], $rights['units']);
	}

	public function testTheModuleUsersHasOnlyTheAdminRoleAndNoUnits(): void
	{
		static::assertSame(['roles' => [['role' => AclSections::MODULEADMIN, 'granted' => false]], 'units' => []], $this->service->findUserRights(1, 2, 'users'));
	}

	public function testNobodySeesHisOwnRightsPage(): void
	{
		$this->expectException(AccessDeniedException::class);
		$this->service->findUserRights(1, 1, 'playlists');
	}

	public function testAUserWithoutRightsSeesNoRightsPage(): void
	{
		$this->expectException(AccessDeniedException::class);
		$this->service->findUserRights(2, 3, 'playlists');
	}

	public function testTheAdminSearchesTheUnitsByName(): void
	{
		static::assertSame([['id' => '1', 'name' => 'Annas Playlist 1'], ['id' => '2', 'name' => 'Annas Playlist 2']], $this->service->searchUnits(1, 'playlists', 'annas'));
		static::assertSame([], $this->service->searchUnits(1, 'playlists', '%'), 'A wildcard is searched as text.');
	}

	public function testAUserMayNotSearchUnits(): void
	{
		$this->expectException(AccessDeniedException::class);
		$this->service->searchUnits(2, 'playlists', 'annas');
	}

	public function testADeletedUnitTakesItsSharesWithIt(): void
	{
		$this->service->setRole(1, 2, 'playlists', AclSections::EDITOR, true);
		$this->service->setRole(1, 2, 'playlists', AclSections::VIEWER, true);
		$this->service->setRole(1, 3, 'playlists', AclSections::VIEWER, true);
		$this->service->setRole(1, 2, 'templates', AclSections::VIEWER, true);
		$this->service->setUnitRole(1, 2, 'playlists', 1, AclSections::EDITOR);
		$this->service->setUnitRole(1, 3, 'playlists', 1, AclSections::VIEWER);
		$this->service->setUnitRole(1, 2, 'playlists', 2, AclSections::VIEWER);
		$this->connection->executeStatement("INSERT INTO user_vip (UID, vip_name, unit_id) VALUES (2, 'templates_viewer', '1')");
		static::assertTrue($this->aclHelper->hasEditorAccessOnUnit(2, 1, 'playlists'));

		$this->service->removeUnitShares('playlists', [1]);

		static::assertFalse($this->aclHelper->hasEditorAccessOnUnit(2, 1, 'playlists'), 'The cache of the user is invalidated.');
		static::assertSame(0, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM user_vip WHERE vip_name LIKE 'playlists_%' AND unit_id = '1'"));
		static::assertTrue($this->aclHelper->hasViewerAccessOnUnit(2, 2, 'playlists'), 'Other units keep their shares.');
		static::assertSame(1, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM user_vip WHERE vip_name = 'templates_viewer' AND unit_id = '1'"), 'The same id in another module is another unit.');
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
