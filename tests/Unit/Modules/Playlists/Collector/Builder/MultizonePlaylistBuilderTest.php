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

use App\Modules\Player\Entities\PlayerEntity;
use App\Modules\Playlists\Collector\Builder\BuildHelper;
use App\Modules\Playlists\Collector\Builder\MultizonePlaylistBuilder;
use App\Modules\Playlists\Collector\Contracts\PlaylistStructureInterface;
use App\Modules\Playlists\Collector\SimplePlaylistStructureFactory;
use App\Modules\Playlists\Helper\ExportSmil\items\Base;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MultizonePlaylistBuilderTest extends TestCase
{
	private PlayerEntity&MockObject $playerEntityMock;
	private BuildHelper&MockObject $buildHelperMock;
	private SimplePlaylistStructureFactory&MockObject $simplePlaylistStructureFactoryMock;
	private MultizonePlaylistBuilder $builder;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->playerEntityMock = $this->createMock(PlayerEntity::class);
		$this->buildHelperMock = $this->createMock(BuildHelper::class);
		$this->simplePlaylistStructureFactoryMock = $this->createMock(SimplePlaylistStructureFactory::class);

		$this->builder = new MultizonePlaylistBuilder(
			$this->playerEntityMock,
			$this->buildHelperMock,
			$this->simplePlaylistStructureFactoryMock
		);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testBuildPlaylistWithValidZones(): void
	{
		$zones = ['zones' => [1 => ['zone_playlist_id' => 101]]];

		$this->playerEntityMock->method('getLayout')->willReturn($zones);

		$this->buildHelperMock->method('collectItems')
			->with(101)
			->willReturn('region="screen" item 101')
		;

		$this->buildHelperMock->method('collectPrefetches')
			->with(101)
			->willReturn('prefetch 101')
		;

		$this->buildHelperMock->method('collectExclusives')
			->with(101)
			->willReturn('region="screen" exclusive 101')
		;

		$playlistStructureMock = $this->createMock(PlaylistStructureInterface::class);

		$items = Base::TABSTOPS_TAG . '<seq id="media1" repeatCount="indefinite">' . "\n" .
			'region="screen1" item 101'.
			Base::TABSTOPS_TAG . '</seq>' . "\n";


		$this->simplePlaylistStructureFactoryMock->method('create')
			->with($items, 'prefetch 101'. "\n", 'region="screen1" exclusive 101')
			->willReturn($playlistStructureMock);

		$result = $this->builder->buildPlaylist();

		static::assertSame($playlistStructureMock, $result);
	}


}
