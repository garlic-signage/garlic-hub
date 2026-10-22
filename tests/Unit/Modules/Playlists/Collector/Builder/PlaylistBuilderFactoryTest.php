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


namespace Tests\Unit\Modules\Playlists\Collector\Builder;

use App\Modules\Playlists\Collector\Contracts\PlaylistTargetInterface;
use App\Modules\Playlists\Collector\Builder\BuildHelper;
use App\Modules\Playlists\Collector\Builder\MultizonePlaylistBuilder;
use App\Modules\Playlists\Collector\Builder\PlaylistBuilderFactory;
use App\Modules\Playlists\Collector\Builder\StandardPlaylistBuilder;
use App\Modules\Playlists\Collector\SimplePlaylistStructureFactory;
use App\Modules\Playlists\Helper\PlaylistMode;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class PlaylistBuilderFactoryTest extends TestCase
{
	private PlaylistBuilderFactory $factory;
	private PlaylistTargetInterface&Stub $playlistTargetStub;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$buildHelperStub = $this->createStub(BuildHelper::class);
		$simplePlaylistStructureFactoryStub = $this->createStub(SimplePlaylistStructureFactory::class);

		$this->playlistTargetStub = $this->createStub(PlaylistTargetInterface::class);


		$this->factory = new PlaylistBuilderFactory(
			$buildHelperStub,
			$simplePlaylistStructureFactoryStub
		);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testCreateBuilderReturnsMultizonePlaylistBuilder(): void
	{
		$this->playlistTargetStub->method('getPlaylistMode')->willReturn(PlaylistMode::MULTIZONE->value);

		$result = $this->factory->createBuilder($this->playlistTargetStub);

		static::assertInstanceOf(MultiZonePlaylistBuilder::class, $result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testCreateBuilderReturnsStandardPlaylistBuilder(): void
	{
		$this->playlistTargetStub->method('getPlaylistMode')->willReturn(PlaylistMode::MASTER->value);

		$result = $this->factory->createBuilder($this->playlistTargetStub);

		static::assertInstanceOf(StandardPlaylistBuilder::class, $result);
	}
}
