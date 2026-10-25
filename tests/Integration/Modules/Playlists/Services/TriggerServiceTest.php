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

namespace Tests\Integration\Modules\Playlists\Services;

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\NotFoundException;
use App\Modules\Playlists\Services\ItemsService;
use App\Modules\Playlists\Services\TriggerService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * The begin trigger of an item: stored serialized in the item, only for users who may edit its playlist.
 * Fixtures: of the items of anna's playlist 1 the image 1, the video 2 and the template 5 can be touched.
 */
#[Group('acl')]
class TriggerServiceTest extends ContainerTestCase
{
	private TriggerService $triggerService;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('playlists/playlists.sql');
		$this->loadFixture('playlists/mediapool_files.sql');
		$this->loadFixture('playlists/items.sql');
		$this->triggerService = new TriggerService($this->container->get(ItemsService::class), $this->container->get('ModuleLogger')); // the controller builds it the same way
	}

	public function testASavedTriggerComesBackWithTheOtherMediaOfThePlaylist(): void
	{
		$this->actingAs(2);
		$trigger = ['type' => 'touch', 'target' => '1'];
		$this->connection->executeStatement("UPDATE mediapool_files SET metadata = '{\"duration\":30}' WHERE checksum = 'checksum-video'");

		$this->triggerService->saveBeginTrigger(2, $trigger);
		$result = $this->triggerService->fetchBeginTriggerByItemId(2);

		static::assertSame($trigger, $result['item_data']['begin_trigger']);
		// not the item itself, no pdf and no nested playlist: only images and videos, a template with an image as well
		static::assertSame([1, 5], array_map(intval(...), array_column($result['touchable_media'], 'item_id')));
	}

	public function testAnItemWithoutTriggerHasAnEmptyOne(): void
	{
		$this->actingAs(2);

		static::assertSame([], $this->triggerService->fetchBeginTriggerByItemId(1)['item_data']['begin_trigger']);
	}

	public function testSavingTheSameTriggerAgainIsNoError(): void
	{
		$this->actingAs(2);
		$this->triggerService->saveBeginTrigger(1, ['type' => 'touch']);

		$this->triggerService->saveBeginTrigger(1, ['type' => 'touch']);

		static::assertSame(['type' => 'touch'], $this->triggerService->fetchBeginTriggerByItemId(1)['item_data']['begin_trigger']);
	}

	public function testAUserMayNotReadOrChangeTheTriggerOfAForeignItem(): void
	{
		$this->actingAs(2);

		try
		{
			$this->triggerService->saveBeginTrigger(6, ['type' => 'touch']);
			static::fail('Expected AccessDeniedException');
		}
		catch (AccessDeniedException)
		{
			static::assertSame('', $this->connection->fetchOne('SELECT begin_trigger FROM playlists_items WHERE item_id = 6'));
		}

		$this->expectException(AccessDeniedException::class);
		$this->triggerService->fetchBeginTriggerByItemId(6);
	}

	public function testAnUnknownItemIsNotFound(): void
	{
		$this->actingAs(2);

		$this->expectException(NotFoundException::class);
		$this->triggerService->saveBeginTrigger(999, ['type' => 'touch']);
	}
}
