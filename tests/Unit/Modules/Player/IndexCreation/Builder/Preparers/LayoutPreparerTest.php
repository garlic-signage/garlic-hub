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


namespace Tests\Unit\Modules\Player\IndexCreation\Builder\Preparers;

use App\Modules\Player\Entities\PlayerEntity;
use App\Modules\Player\IndexCreation\Builder\Preparers\LayoutPreparer;
use App\Modules\Playlists\Helper\PlaylistMode;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LayoutPreparerTest extends TestCase
{
	private PlayerEntity&MockObject $playerEntityMock;
	private LayoutPreparer $preparer;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->playerEntityMock = $this->createMock(PlayerEntity::class);
		$this->preparer   = new LayoutPreparer($this->playerEntityMock);
	}

	#[Group('units')]
	public function testPrepareWithMultizone(): void
	{
		$this->playerEntityMock
			->method('getProperties')
			->willReturn(['width' => '1920', 'height' => '1080']);
		$this->playerEntityMock
			->method('getPlaylistMode')
			->willReturn(PlaylistMode::MULTIZONE->value);
		$this->playerEntityMock
			->method('getLayout')
			->willReturn([
				'export_unit' => 'percent',
				'zones' => [
					'zone1' => [
						'zone_top' => 0,
						'zone_left' => 0,
						'zone_width' => 50,
						'zone_height' => 100,
						'zone_z-index' => 1,
						'zone_bgcolor' => '#FFF',
					],
				],
			]);

		$result = $this->preparer->prepare();

		static::assertArrayHasKey('ROOT_LAYOUT_WIDTH', $result[0]);
		static::assertEquals('1920', $result[0]['ROOT_LAYOUT_WIDTH']);
		static::assertArrayHasKey('ROOT_LAYOUT_HEIGHT', $result[0]);
		static::assertEquals('1080', $result[0]['ROOT_LAYOUT_HEIGHT']);
		static::assertArrayHasKey('regions', $result[0]);
		static::assertIsArray($result[0]['regions']);
		static::assertCount(1, $result[0]['regions']);
		static::assertEquals('0%', $result[0]['regions'][0]['REGION_LEFT']);
	}

	#[Group('units')]
	public function testPrepareWithMultizoneAsPixel(): void
	{
		$this->playerEntityMock->method('getProperties')
			->willReturn(['width' => '1920', 'height' => '1080']);
		$this->playerEntityMock->method('getPlaylistMode')
			->willReturn(PlaylistMode::MULTIZONE->value);
		$this->playerEntityMock->method('getLayout')
			->willReturn([
				'export_unit' => 'pixel',
				'zones' => [
					'zone1' => [
						'zone_top' => 0,
						'zone_left' => 0,
						'zone_width' => 50,
						'zone_height' => 100,
						'zone_z-index' => 1,
						'zone_bgcolor' => '#FFF',
					],
				],
			]);

		$result = $this->preparer->prepare();

		static::assertArrayHasKey('ROOT_LAYOUT_WIDTH', $result[0]);
		static::assertEquals('1920', $result[0]['ROOT_LAYOUT_WIDTH']);
		static::assertArrayHasKey('ROOT_LAYOUT_HEIGHT', $result[0]);
		static::assertEquals('1080', $result[0]['ROOT_LAYOUT_HEIGHT']);
		static::assertArrayHasKey('regions', $result[0]);
		static::assertIsArray($result[0]['regions']);
		static::assertCount(1, $result[0]['regions']);
		static::assertEquals('0', $result[0]['regions'][0]['REGION_LEFT']);
	}


	#[Group('units')]
	public function testPrepareWithoutMultizone(): void
	{
		$this->playerEntityMock
			->method('getProperties')
			->willReturn(['width' => '1920', 'height' => '1080']);
		$this->playerEntityMock
			->method('getPlaylistMode')
			->willReturn(PlaylistMode::MASTER->value);

		$result = $this->preparer->prepare();

		static::assertArrayHasKey('ROOT_LAYOUT_WIDTH', $result[0]);
		static::assertEquals('1920', $result[0]['ROOT_LAYOUT_WIDTH']);
		static::assertArrayHasKey('ROOT_LAYOUT_HEIGHT', $result[0]);
		static::assertEquals('1080', $result[0]['ROOT_LAYOUT_HEIGHT']);
		static::assertArrayHasKey('regions', $result[0]);
		static::assertIsArray($result[0]['regions']);
		static::assertCount(1, $result[0]['regions']);
		static::assertEquals(0, $result[0]['regions'][0]['REGION_LEFT']);
	}
}
