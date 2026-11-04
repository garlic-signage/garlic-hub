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

namespace Tests\Integration\Modules\Playlists\Services;

use App\Framework\Exceptions\AccessDeniedException;
use App\Modules\Playlists\Services\PlaylistsService;
use App\Modules\Templates\Services\TemplatesService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * A viewer of a unit may use it: a playlist in another playlist, a zone or on a player, a template in a playlist.
 * Anna (2) is viewer of the playlist 2 and the template 3 of bernd (3).
 */
#[Group('acl')]
class UsableUnitsTest extends ContainerTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('templates/templates.sql');
		$this->loadFixture('playlists/playlists.sql');
		$this->connection->executeStatement("INSERT INTO user_acl (UID, acl, module) VALUES (2, 1, 'playlists'), (2, 1, 'templates')");
		$this->connection->executeStatement("INSERT INTO user_vip (UID, vip_name, unit_id) VALUES (2, 'playlists_viewer', '2'), (2, 'templates_viewer', '3')");
		$this->actingAs(2);
	}

	public function testAViewerUsesASharedPlaylist(): void
	{
		static::assertSame('Bernds Kantine', $this->container->get(PlaylistsService::class)->loadUsableById(2)['playlist_name']);
	}

	public function testAViewerSeesTheNameOfASharedPlaylistInAZone(): void
	{
		static::assertSame(['playlist_id' => 2, 'name' => 'Bernds Kantine'], $this->container->get(PlaylistsService::class)->loadNameById(2));
	}

	public function testAViewerDoesNotEditASharedPlaylist(): void
	{
		$this->expectException(AccessDeniedException::class);
		$this->container->get(PlaylistsService::class)->loadPureById(2);
	}

	public function testAViewerUsesASharedTemplate(): void
	{
		static::assertSame('Bernds Vorlage', $this->container->get(TemplatesService::class)->loadUsableById(3)['name']);
	}

	public function testATemplateWhichIsNotSharedIsNotUsable(): void
	{
		$this->expectException(AccessDeniedException::class);
		$this->container->get(TemplatesService::class)->loadUsableById(1);
	}
}
