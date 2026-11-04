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

		static::assertSame(['users', 'mediapool', 'player', 'playlists', 'templates'], array_column($data['tabs'], 'MODULE'));
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
}
