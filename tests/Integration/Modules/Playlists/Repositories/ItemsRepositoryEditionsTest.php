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

namespace Tests\Integration\Modules\Playlists\Repositories;

use App\Framework\Config\Config;
use App\Modules\Playlists\Repositories\ItemsRepository;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\RepositoryTestCase;

/**
 * The Enterprise edition joins the channels. The migrations of the core edition do not
 * create them, so the test creates the little it needs and runs the real query.
 */
#[Group('db')]
class ItemsRepositoryEditionsTest extends RepositoryTestCase
{
	private ItemsRepository $repository;

	protected function setUp(): void
	{
		parent::setUp();
		$this->repository = new ItemsRepository($this->connection);
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('playlists/playlists.sql');
		$this->loadFixture('playlists/mediapool_files.sql');
		$this->loadFixture('playlists/items.sql');
		$this->connection->executeStatement('CREATE TABLE channels (channel_id INTEGER PRIMARY KEY, channel_type TEXT, view_mode TEXT, vendor TEXT)');
	}

	/**
	 * @throws Exception
	 */
	public function testCoreHasNoColumnsOfEnterprise(): void
	{
		$items = $this->repository->findAllByPlaylistIdWithJoins(1, Config::PLATFORM_EDITION_CORE);

		static::assertArrayNotHasKey('channel_type', $items[0]);
	}

	/**
	 * @throws Exception
	 */
	public function testAnUnknownEditionGetsTheColumnsOfCore(): void
	{
		$items = $this->repository->findAllByPlaylistIdWithJoins(1, 'undefined_edition');

		static::assertCount(5, $items);
		static::assertArrayNotHasKey('channel_type', $items[0]);
	}

	/**
	 * @throws Exception
	 */
	public function testEnterpriseAddsTheChannels(): void
	{
		$items = $this->repository->findAllByPlaylistIdWithJoins(1, Config::PLATFORM_EDITION_ENTERPRISE);

		static::assertCount(5, $items);
		static::assertArrayHasKey('channel_type', $items[0]);
		static::assertArrayHasKey('vendor', $items[0]);
		static::assertNull($items[0]['channel_type']);
	}
}
