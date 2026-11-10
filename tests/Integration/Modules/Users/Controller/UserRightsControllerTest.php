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

namespace Tests\Integration\Modules\Users\Controller;

use App\Framework\Exceptions\AccessDeniedException;
use App\Modules\Users\Controller\UserRightsController;
use App\Shared\Acl\AclGrantService;
use App\Shared\Acl\AclSections;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;

/**
 * The rights page of a user. Users: 1 admin, 2 anna, 3 bernd. Units: the playlists of acl/playlists.sql.
 */
#[Group('acl')]
class UserRightsControllerTest extends UsersControllerTestCase
{
	private UserRightsController $controller;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/playlists.sql');
		$this->controller = $this->container->get(UserRightsController::class);
	}

	private function show(int $UID): ResponseInterface
	{
		return $this->controller->show($this->request('GET', '/users/rights/' . $UID, null), $this->newResponse(), ['UID' => (string) $UID]);
	}

	/**
	 * @param array<string,string> $body
	 */
	private function updateRole(int $UID, array $body): ResponseInterface
	{
		return $this->controller->updateRole($this->request('PUT', '/async/users/rights/' . $UID, $body), $this->newResponse(), ['UID' => (string) $UID]);
	}

	public function testTheAdminSeesATabPerModuleWithTheRolesAndUnits(): void
	{
		$grants = $this->container->get(AclGrantService::class);
		$grants->setRole(1, 2, 'playlists', AclSections::VIEWER, true);
		$grants->setUnitRole(1, 2, 'playlists', 3, AclSections::VIEWER);
		$this->actingAs(1);

		$data = $this->page($this->show(2))->getTemplateData();

		static::assertSame(['users', 'mediapool', 'player', 'playlists', 'templates', 'system'], array_column($data['tabs'], 'MODULE'));
		// a player has no viewer
		static::assertSame(['moduleadmin', 'editor'], array_column($data['tabs'][2]['roles'], 'ROLE'));
		$playlists = $data['tabs'][3];
		static::assertSame(['moduleadmin', 'editor', 'viewer'], array_column($playlists['roles'], 'ROLE'));
		static::assertSame(['', '', 'checked'], array_column($playlists['roles'], 'CHECKED'));
		static::assertTrue($playlists['has_units']);
		static::assertSame(['3'], array_column($playlists['units'], 'UNIT_ID'));
		// anna is only viewer: a unit can not be made editable for her
		static::assertSame(['viewer'], array_column($playlists['unit_roles'], 'VALUE'));
		static::assertFalse($data['tabs'][0]['has_units'], 'The module users has no units.');
	}

	public function testNobodyOpensHisOwnRightsPage(): void
	{
		$this->actingAs(1);

		$this->assertRedirect($this->show(1), '/users', $this->text('rights_not_grantable'));
	}

	public function testAUserWithoutRightsIsSentBack(): void
	{
		$this->actingAs(2);

		$this->assertRedirect($this->show(3), '/users', $this->text('rights_not_grantable'));
	}

	public function testAnUnknownUserIsSentBack(): void
	{
		$this->actingAs(1);

		$this->assertRedirect($this->show(99), '/users', $this->text('rights_not_grantable'));
	}

	public function testTheAdminSetsAndRemovesARole(): void
	{
		$this->actingAs(1);

		$this->assertSuccess($this->updateRole(2, ['module' => 'playlists', 'role' => 'editor', 'granted' => '1']));
		static::assertSame(2, (int) $this->databaseValue("SELECT acl FROM user_acl WHERE UID = 2 AND module = 'playlists'"));

		$this->assertSuccess($this->updateRole(2, ['module' => 'playlists', 'role' => 'editor', 'granted' => '0']));
		static::assertFalse($this->databaseValue("SELECT acl FROM user_acl WHERE UID = 2 AND module = 'playlists'"));
	}

	public function testAUserMayNotSetRoles(): void
	{
		$this->actingAs(2);

		$this->expectException(AccessDeniedException::class);
		$this->updateRole(3, ['module' => 'playlists', 'role' => 'viewer', 'granted' => '1']);
	}
	/**
	 * The texts of the page, its title and the files of the page, and a tab with all its parts: the roles with their
	 * texts, the units with the roles a unit can get and the role of the unit selected.
	 */
	public function testThePageHasItsTextsAndATabWithAllParts(): void
	{
		$grants = $this->container->get(AclGrantService::class);
		$grants->setRole(1, 2, 'playlists', AclSections::EDITOR, true);
		$grants->setRole(1, 2, 'playlists', AclSections::VIEWER, true);
		$grants->setUnitRole(1, 2, 'playlists', 3, AclSections::VIEWER);
		$grants->setUnitRole(1, 2, 'playlists', 4, AclSections::EDITOR);
		$this->actingAs(1);

		$page  = $this->page($this->show(2));
		$data  = $page->getTemplateData();
		$title = $this->text('rights') . ': anna';

		static::assertSame('users/rights', $page->getTemplate());
		static::assertSame(['LANG_PAGE_TITLE' => $title, 'additional_css' => ['/css/users/rights.css'], 'footer_modules' => ['/js/users/rights/init.js']], $page->getMainLayoutData());
		$tabs = $data['tabs'];
		unset($data['tabs']);
		static::assertSame([
			'LANG_PAGE_HEADER'         => $title,
			'UID'                      => 2,
			'LANG_ADD_UNIT'            => $this->text('add_unit'),
			'LANG_UNITS'               => $this->text('units'),
			'LANG_NO_UNITS'            => $this->text('no_units'),
			'LANG_CONFIRM_REMOVE_ROLE' => $this->text('confirm_remove_role'),
			'LANG_ROLE_NONE'           => $this->text('role_none'),
		], $data);

		$editor = fn(string $selected): array => ['VALUE' => 'editor', 'LANG_ROLE' => $this->text('role_editor'), 'SELECTED' => $selected];
		$viewer = fn(string $selected): array => ['VALUE' => 'viewer', 'LANG_ROLE' => $this->text('role_viewer'), 'SELECTED' => $selected];
		static::assertIsArray($tabs);
		static::assertSame([
			'MODULE'      => 'playlists',
			'LANG_MODULE' => $this->text('playlists', 'menu'),
			'roles'       => [
				['ROLE' => 'moduleadmin', 'LANG_ROLE' => $this->text('role_moduleadmin'), 'CHECKED' => ''],
				['ROLE' => 'editor', 'LANG_ROLE' => $this->text('role_editor'), 'CHECKED' => 'checked'],
				['ROLE' => 'viewer', 'LANG_ROLE' => $this->text('role_viewer'), 'CHECKED' => 'checked'],
			],
			'has_units'   => true,
			'units'       => [
				['UNIT_ID' => '4', 'UNIT_NAME' => 'Admin Playlist', 'unit_roles' => [$viewer(''), $editor('selected')]],
				['UNIT_ID' => '3', 'UNIT_NAME' => 'Bernds Playlist', 'unit_roles' => [$viewer('selected'), $editor('')]],
			],
			'NO_UNITS'    => 'hidden',
			'unit_roles'  => [$viewer(''), $editor('')],
		], $tabs[3]);
	}

	/**
	 * Without units the hint "no unit" is shown.
	 */
	public function testATabWithoutUnitsShowsTheHint(): void
	{
		$this->container->get(AclGrantService::class)->setRole(1, 2, 'playlists', AclSections::VIEWER, true);
		$this->actingAs(1);

		$tab = $this->page($this->show(2))->getTemplateData()['tabs'][3];

		static::assertSame([], $tab['units']);
		static::assertSame('', $tab['NO_UNITS']);
	}

	public function testUserZeroIsSentBack(): void
	{
		$this->actingAs(1);

		$this->assertRedirect($this->show(0), '/users', $this->text('rights_not_grantable'));
	}

	public function testADeletedUserHasNoRightsPage(): void
	{
		$this->connection->update('user_main', ['status' => 0], ['UID' => 2]);
		$this->actingAs(1);

		$this->assertRedirect($this->show(2), '/users', $this->text('rights_not_grantable'));
	}
}
