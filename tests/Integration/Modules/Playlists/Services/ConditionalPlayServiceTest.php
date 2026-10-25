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
use App\Modules\Playlists\Services\ConditionalPlayService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * The conditional play settings of an item: stored serialized in the item, only for users who may edit its playlist.
 */
#[Group('acl')]
class ConditionalPlayServiceTest extends ContainerTestCase
{
	private ConditionalPlayService $conditionalPlayService;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('playlists/playlists.sql');
		$this->loadFixture('playlists/mediapool_files.sql');
		$this->loadFixture('playlists/items.sql');
		$this->conditionalPlayService = $this->container->get(ConditionalPlayService::class);
	}

	public function testSavedSettingsComeBackWithTheItem(): void
	{
		$this->actingAs(2);
		$settings = ['weekdays' => ['mon', 'tue'], 'begin' => '08:00'];

		$this->conditionalPlayService->saveConditionalPlay(1, $settings);
		$item = $this->conditionalPlayService->fetchConditionalByItemId(1);

		static::assertSame($settings, $item['conditional']);
		static::assertSame('bild.jpg', $item['item_name']);
		static::assertSame(15, $item['default_duration']);
	}

	public function testAnItemWithoutSettingsHasEmptyOnes(): void
	{
		$this->actingAs(2);

		static::assertSame([], $this->conditionalPlayService->fetchConditionalByItemId(1)['conditional']);
	}

	public function testSavingTheSameSettingsAgainIsNoError(): void
	{
		$this->actingAs(2);
		$this->conditionalPlayService->saveConditionalPlay(1, ['begin' => '08:00']);

		$this->conditionalPlayService->saveConditionalPlay(1, ['begin' => '08:00']);

		static::assertSame(['begin' => '08:00'], $this->conditionalPlayService->fetchConditionalByItemId(1)['conditional']);
	}

	public function testAUserMayNotReadOrChangeTheSettingsOfAForeignItem(): void
	{
		$this->actingAs(2);

		try
		{
			$this->conditionalPlayService->saveConditionalPlay(6, ['begin' => '08:00']);
			static::fail('Expected AccessDeniedException');
		}
		catch (AccessDeniedException)
		{
			static::assertSame('', $this->connection->fetchOne('SELECT conditional FROM playlists_items WHERE item_id = 6'));
		}

		$this->expectException(AccessDeniedException::class);
		$this->conditionalPlayService->fetchConditionalByItemId(6);
	}

	public function testAnUnknownItemIsNotFound(): void
	{
		$this->actingAs(2);

		$this->expectException(NotFoundException::class);
		$this->conditionalPlayService->fetchConditionalByItemId(999);
	}
}
