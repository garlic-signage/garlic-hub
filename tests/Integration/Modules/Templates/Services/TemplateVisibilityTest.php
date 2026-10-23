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

namespace Tests\Integration\Modules\Templates\Services;

use App\Modules\Templates\Services\TemplatesDatatableService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * Who sees which templates. Fixtures: template 1 belongs to the admin, 2 to anna, 3 to bernd.
 * Only admins may open the template list, but the list is also used by the pickers of other pages.
 */
#[Group('acl')]
class TemplateVisibilityTest extends ContainerTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('templates/templates.sql');
	}

	public function testAdminSeesAllTemplates(): void
	{
		static::assertSame(['Admin Vorlage', 'Annas Vorlage', 'Bernds Vorlage'], $this->namesVisibleFor(1));
	}

	public function testUserSeesOnlyOwnTemplates(): void
	{
		static::assertSame(['Annas Vorlage'], $this->namesVisibleFor(2));
		static::assertSame(['Bernds Vorlage'], $this->namesVisibleFor(3));
	}

	public function testAdminRightOnAnotherModuleDoesNotShowForeignTemplates(): void
	{
		$this->connection->executeStatement("INSERT INTO user_acl (UID, acl, module) VALUES (2, 8, 'player')");

		static::assertSame(['Annas Vorlage'], $this->namesVisibleFor(2));
	}

	public function testOnlyAdminsMayOpenTheTemplateList(): void
	{
		$this->actingAs(1);
		static::assertTrue($this->container->get(TemplatesDatatableService::class)->checkDisplayRights());

		$this->actingAs(2);
		static::assertFalse($this->container->get(TemplatesDatatableService::class)->checkDisplayRights());
	}

	public function testUsageCountsThePlaylistItemsOfATemplate(): void
	{
		$this->loadFixture('playlists/playlists.sql');
		$this->loadFixture('playlists/items.sql');
		$service = $this->container->get(TemplatesDatatableService::class);

		// template 7 is used by two playlist items, the others are not used
		static::assertSame([7 => 2], $service->getTemplatesInUse([1, 2, 3, 7]));
		static::assertSame([], $service->getTemplatesInUse([1, 2, 3]));
		static::assertSame([], $service->getTemplatesInUse([]));
	}

	/**
	 * @return list<string>
	 */
	private function namesVisibleFor(int $UID): array
	{
		$this->actingAs($UID);
		$service = $this->container->get(TemplatesDatatableService::class);
		$service->loadDatatable();

		return array_column($service->getCurrentFilterResults(), 'name');
	}
}
