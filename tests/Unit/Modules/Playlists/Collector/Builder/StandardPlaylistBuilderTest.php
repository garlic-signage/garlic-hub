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
use App\Modules\Playlists\Collector\Builder\StandardPlaylistBuilder;
use App\Modules\Playlists\Collector\Contracts\PlaylistStructureInterface;
use App\Modules\Playlists\Collector\SimplePlaylistStructureFactory;
use App\Modules\Playlists\Helper\ExportSmil\items\Base;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StandardPlaylistBuilderTest extends TestCase
{
	private PlayerEntity&MockObject $playerEntityMock;
	private BuildHelper&MockObject $buildHelperMock;
	private SimplePlaylistStructureFactory&MockObject $simplePlaylistStructureFactoryMock;
	private StandardPlaylistBuilder $builder;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->playerEntityMock = $this->createMock(PlayerEntity::class);
		$this->buildHelperMock = $this->createMock(BuildHelper::class);
		$this->simplePlaylistStructureFactoryMock = $this->createMock(SimplePlaylistStructureFactory::class);

		$this->builder = new StandardPlaylistBuilder(
			$this->playerEntityMock,
			$this->buildHelperMock,
			$this->simplePlaylistStructureFactoryMock
		);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testBuildPlaylistWithValidData(): void
	{
		$playlistId = 123;

		$this->playerEntityMock->expects($this->once())
			->method('getPlaylistId')
			->willReturn($playlistId);

		$items = 'valid_items';
		$prefetch = 'valid_prefetch';
		$exclusive = 'valid_exclusive';

		$this->buildHelperMock->expects($this->once())
			->method('collectItems')
			->with($playlistId)
			->willReturn($items);

		$this->buildHelperMock->expects($this->once())
			->method('collectPrefetches')
			->with($playlistId)
			->willReturn($prefetch);

		$this->buildHelperMock->expects($this->once())
			->method('collectExclusives')
			->with($playlistId)
			->willReturn($exclusive);

		$formattedItems = Base::TABSTOPS_TAG . '<seq repeatCount="indefinite">' . "\n" .
				$items .
				Base::TABSTOPS_TAG . '</seq>' . "\n";

		$this->simplePlaylistStructureFactoryMock->expects($this->once())
			->method('create')
			->with($formattedItems, $prefetch, $exclusive)
			->willReturn($this->createStub(PlaylistStructureInterface::class));


			$this->builder->buildPlaylist();
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testBuildPlaylistWithEmptyData(): void
	{
		$playlistId = 123;

		$this->playerEntityMock->expects($this->once())
			->method('getPlaylistId')
			->willReturn($playlistId);

		$this->buildHelperMock->expects($this->once())
			->method('collectItems')
			->with($playlistId)
			->willReturn('');

		$this->buildHelperMock->expects($this->once())
			->method('collectPrefetches')
			->with($playlistId)
			->willReturn('');

		$this->buildHelperMock->expects($this->once())
			->method('collectExclusives')
			->with($playlistId)
			->willReturn('');

		$formattedItems = Base::TABSTOPS_TAG . '<seq repeatCount="indefinite">' . "\n" .
			Base::TABSTOPS_TAG . '</seq>' . "\n";

		$this->simplePlaylistStructureFactoryMock
			->expects($this->once())
			->method('create')
			->with($formattedItems, '', '')
			->willReturn($this->createStub(PlaylistStructureInterface::class));


			$this->builder->buildPlaylist();
	}

}
