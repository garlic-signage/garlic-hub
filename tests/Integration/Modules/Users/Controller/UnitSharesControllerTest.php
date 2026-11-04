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
use App\Framework\Exceptions\TranslatableModuleException;
use App\Modules\Users\Controller\UnitSharesController;
use App\Shared\Acl\AclGrantService;
use App\Shared\Acl\AclSections;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;

/**
 * The share dialog of a unit. Users: 1 admin, 2 anna (editor and viewer of playlists), 3 bernd (viewer of playlists).
 * Units: the playlists of acl/playlists.sql.
 */
#[Group('acl')]
class UnitSharesControllerTest extends UsersControllerTestCase
{
	private UnitSharesController $controller;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/playlists.sql');
		$grants = $this->container->get(AclGrantService::class);
		$grants->setRole(1, 2, 'playlists', AclSections::EDITOR, true);
		$grants->setRole(1, 2, 'playlists', AclSections::VIEWER, true);
		$grants->setRole(1, 3, 'playlists', AclSections::VIEWER, true);
		$grants->setUnitRole(1, 3, 'playlists', 4, AclSections::VIEWER);

		$this->controller = $this->container->get(UnitSharesController::class);
	}

	private function show(string $module, string $unitId): ResponseInterface
	{
		return $this->controller->show($this->request('GET', '/async/acl/' . $module . '/units/' . $unitId, null), $this->newResponse(), ['module' => $module, 'unit_id' => $unitId]);
	}

	/**
	 * @param array<string,mixed> $body
	 */
	private function update(string $unitId, array $body): ResponseInterface
	{
		return $this->controller->update($this->request('PUT', '/async/acl/playlists/units/' . $unitId, $body), $this->newResponse(), ['module' => 'playlists', 'unit_id' => $unitId]);
	}

	public function testTheAdminSeesTheUsersWithTheirRoles(): void
	{
		$this->actingAs(1);

		$data = $this->json($this->show('playlists', '4'));

		static::assertSame(['editor', 'viewer'], $data['roles']);
		static::assertSame([
			['UID' => 2, 'username' => 'anna', 'roles' => ['editor', 'viewer'], 'role' => ''],
			['UID' => 3, 'username' => 'bernd', 'roles' => ['viewer'], 'role' => 'viewer'],
		], $data['users']);
		static::assertArrayHasKey('title', $data['labels']);
	}

	public function testTheAdminSetsAndRemovesARole(): void
	{
		$this->actingAs(1);

		$this->assertSuccess($this->update('4', ['UID' => '2', 'role' => 'editor']));
		static::assertSame('playlists_editor', $this->databaseValue("SELECT vip_name FROM user_vip WHERE UID = 2 AND unit_id = '4'"));

		$this->assertSuccess($this->update('4', ['UID' => '2', 'role' => '']));
		static::assertSame(0, (int) $this->databaseValue('SELECT COUNT(*) FROM user_vip WHERE UID = 2'));
	}

	public function testAnUnknownRoleIsRefused(): void
	{
		$this->actingAs(1);

		$this->expectException(TranslatableModuleException::class);
		$this->update('4', ['UID' => '2', 'role' => 'moduleadmin']);
	}

	public function testAUserWithoutRightsMayNotShare(): void
	{
		$this->actingAs(2);

		$this->expectException(AccessDeniedException::class);
		$this->update('1', ['UID' => '3', 'role' => 'viewer']);
	}
}
