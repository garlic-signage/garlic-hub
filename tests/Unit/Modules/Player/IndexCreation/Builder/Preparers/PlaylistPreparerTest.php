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
use App\Modules\Player\IndexCreation\Builder\Preparers\PlaylistPreparer;
use App\Modules\Playlists\Collector\Contracts\PlaylistStructureInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PlaylistPreparerTest extends TestCase
{
	private PlayerEntity&MockObject $playerEntityMock;
	private PlaylistStructureInterface&MockObject $playlistStructureMock;
	private PlaylistPreparer $preparer;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->playerEntityMock = $this->createMock(PlayerEntity::class);
		$this->playlistStructureMock = $this->createMock(PlaylistStructureInterface::class);
		$this->preparer = new PlaylistPreparer($this->playerEntityMock);
	}

	#[Group('units')]
	public function testPrepareWithSimpleStructure(): void
	{
		$this->playerEntityMock->expects($this->never())->method($this->anything());
		$this->preparer->setIsSimple(true)
			->setPlaylistStructure($this->playlistStructureMock);

		$this->playlistStructureMock
			->expects($this->once())
			->method('getItems')
			->willReturn('item1,item2');

		$result = $this->preparer->prepare();

		static::assertEquals(
			[['INSERT_ELEMENTS' => 'item1,item2']],
			$result
		);
	}

	#[Group('units')]
	public function testPrepareWithComplexStructure(): void
	{
		$this->preparer->setIsSimple(false)
			->setPlaylistStructure($this->playlistStructureMock);

		$this->playlistStructureMock->expects($this->atLeastOnce())->method('getExclusive')
			->willReturn('exclusiveData');
		$this->playlistStructureMock->expects($this->atLeastOnce())->method('getItems')
			->willReturn('item1,item2');
		$this->playlistStructureMock->expects($this->atLeastOnce())->method('getPrefetch')
			->willReturn('prefetchData');

		$this->playerEntityMock->expects($this->once())->method('getDuration')
			->willReturn(500);
		$this->playerEntityMock->expects($this->exactly(2))->method('getRefresh')
			->willReturn(800);

		$result = $this->preparer->prepare();

		static::assertEquals(
			[[
				'INSERT_PRIORITY_CLASSES' => 'exclusiveData',
				'INSERT_ELEMENTS' => 'item1,item2',
				'INSERT_PREFETCH_ELEMENTS' => 'prefetchData',
				'PREFETCH_REFRESH_TIME' => 900,
			]],
			$result
		);
	}

	#[Group('units')]
	public function testCalculateDurationWhenRefreshIsGreater(): void
	{
		$this->playlistStructureMock->expects($this->never())->method($this->anything());
		$this->playerEntityMock
			->expects($this->atLeastOnce())->method('getDuration')
			->willReturn(400);
		$this->playerEntityMock
			->expects($this->atLeastOnce())->method('getRefresh')
			->willReturn(800);

		$result = $this->preparer->calculatePrefetchDuration();
		static::assertEquals(900, $result);
	}

	#[Group('units')]
	public function testCalculateDurationWhenDurationIsGreater(): void
	{
		$this->playlistStructureMock->expects($this->never())->method($this->anything());
		$this->playerEntityMock
			->expects($this->atLeastOnce())->method('getDuration')
			->willReturn(1200);
		$this->playerEntityMock
			->expects($this->atLeastOnce())->method('getRefresh')
			->willReturn(800);

		$result = $this->preparer->calculatePrefetchDuration();
		static::assertEquals(2402, $result);
	}

	#[Group('units')]
	public function testCalculateDurationWhenMinimumValueApplies(): void
	{
		$this->playlistStructureMock->expects($this->never())->method($this->anything());
		$this->playerEntityMock
			->expects($this->atLeastOnce())->method('getDuration')
			->willReturn(400);
		$this->playerEntityMock
			->expects($this->atLeastOnce())->method('getRefresh')
			->willReturn(300);

		$result = $this->preparer->calculatePrefetchDuration();
		static::assertEquals(900, $result);
	}

	#[Group('units')]
	public function testCalculateDurationWhenDoubleValueApplies(): void
	{
		$this->playlistStructureMock->expects($this->never())->method($this->anything());
		$this->playerEntityMock
			->expects($this->atLeastOnce())->method('getDuration')
			->willReturn(950);
		$this->playerEntityMock
			->expects($this->atLeastOnce())->method('getRefresh')
			->willReturn(1000);

		$result = $this->preparer->calculatePrefetchDuration();
		static::assertEquals(2002, $result);
	}

}
