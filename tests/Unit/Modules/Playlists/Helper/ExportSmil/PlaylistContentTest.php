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

namespace Tests\Unit\Modules\Playlists\Helper\ExportSmil;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Media\DeliveryUrlBuilder;
use App\Framework\Media\MimeTypeExtensionMapper;
use App\Modules\Playlists\Helper\ExportSmil\items\Base;
use App\Modules\Playlists\Helper\ExportSmil\items\ItemsFactory;
use App\Modules\Playlists\Helper\ExportSmil\items\SeqContainer;
use App\Modules\Playlists\Helper\ExportSmil\items\Video;
use App\Modules\Playlists\Helper\ExportSmil\PlaylistContent;
use App\Modules\Playlists\Helper\ItemDatasource;
use App\Modules\Playlists\Helper\ItemType;
use App\Modules\Playlists\Helper\PlaylistMode;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PlaylistContentTest extends TestCase
{
	private ItemsFactory&MockObject $itemsFactoryMock;
	private MimeTypeExtensionMapper&MockObject $mimeTypeExtensionMapperMock;
	private PlaylistContent $playlistContent;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->itemsFactoryMock = $this->createMock(ItemsFactory::class);
		$this->mimeTypeExtensionMapperMock = $this->createMock(MimeTypeExtensionMapper::class);

		$this->playlistContent = new PlaylistContent(
			$this->itemsFactoryMock,
			new DeliveryUrlBuilder('https://content.server.com'),
			$this->mimeTypeExtensionMapperMock
		);
	}

	/**
	 * @throws ModuleException
	 * @throws Exception
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testBuildWithValidItem(): void
	{
		$this->mimeTypeExtensionMapperMock->expects($this->never())->method($this->anything());
		$playlist = ['shuffle' => 0, 'playlist_mode' => PlaylistMode::MASTER->value];
		$itemData = [
			[
				'item_type' => ItemType::MEDIAPOOL->value,
				'begin_trigger' => [],
				'flags' => 0,
				'datasource' => ItemDatasource::FILE->value,
				'file_resource' => 'file',
				'extension' => 'mp4'
			]
		];

		$videoMock = $this->createMock(Video::class);
		$this->itemsFactoryMock->expects($this->once())->method('createItem')
			->with($itemData[0])
			->willReturn($videoMock);

		$link = 'https://content.server.com/delivery.php/file.mp4';
		$videoMock->method('setLink')->with($link);

		$videoMock->method('getSmilElementTag')->willReturn('smilTag');
		$videoMock->method('getPrefetchTag')->willReturn('prefetchTag');
		$videoMock->method('getExclusive')->willReturn('exclusiveTag');


		$this->playlistContent->init($playlist, $itemData)->build();

		static::assertSame('smilTag', $this->playlistContent->getContentElements());
		static::assertSame('prefetchTag', $this->playlistContent->getContentPrefetch());
		static::assertSame('exclusiveTag', $this->playlistContent->getContentExclusive());
	}

	#[Group('units')]
	public function testBuildTemplateLinksToDeliveryWithTplSource(): void
	{
		$playlist = ['shuffle' => 0, 'playlist_mode' => PlaylistMode::MASTER->value];
		$itemData = [
			[
				'item_id' => 13,
				'item_type' => ItemType::TEMPLATE->value,
				'begin_trigger' => [],
				'flags' => 0,
				'content_data' => '{"objects":[]}',
				'mimetype' => 'image/jpeg'
			]
		];
		$itemMock = $this->createMock(Video::class);
		$this->itemsFactoryMock->expects($this->atLeastOnce())->method('createItem')->willReturn($itemMock);
		$this->mimeTypeExtensionMapperMock->method('determineExtension')->with('image/jpeg')->willReturn('jpg');

		$itemMock->expects($this->once())->method('setLink')->with('https://content.server.com/delivery.php/tpl/13'.'.jpg');
		$itemMock->method('getSmilElementTag')->willReturn('smilTag');
		$itemMock->method('getPrefetchTag')->willReturn('prefetchTag');
		$itemMock->method('getExclusive')->willReturn('exclusiveTag');

		$this->playlistContent->init($playlist, $itemData)->build();

		static::assertSame('smilTag', $this->playlistContent->getContentElements());
	}

	#[Group('units')]
	public function testBuildWithValidItemsAndShufflePickingZero(): void
	{
		$this->mimeTypeExtensionMapperMock->expects($this->never())->method($this->anything());
		$playlist = ['shuffle' => 1, 'shuffle_picking' => 0, 'playlist_mode' => PlaylistMode::MASTER->value];
		$itemData = [
			[
				'item_type' => ItemType::MEDIAPOOL->value,
				'begin_trigger' => [],
				'flags' => 0,
				'datasource' => ItemDatasource::FILE->value,
				'file_resource' => 'file',
				'extension' => 'mp4'
			]
		];

		$videoMock = $this->createMock(Video::class);
		$this->itemsFactoryMock->expects($this->once())->method('createItem')
			->with($itemData[0])
			->willReturn($videoMock);

		$link = 'https://content.server.com/delivery.php/file.mp4';
		$videoMock->method('setLink')->with($link);

		$videoMock->method('getSmilElementTag')->willReturn('smilTag');
		$videoMock->method('getPrefetchTag')->willReturn('prefetchTag');
		$videoMock->method('getExclusive')->willReturn('exclusiveTag');


		$this->playlistContent->init($playlist, $itemData)->build();
		$shuffle = Base::TABSTOPS_TAG.'<metadata><meta name="adapi:pickingAlgorithm" content="shuffle"/></metadata>'."\n";

		static::assertSame($shuffle.'smilTag', $this->playlistContent->getContentElements());
		static::assertSame('prefetchTag', $this->playlistContent->getContentPrefetch());
		static::assertSame('exclusiveTag', $this->playlistContent->getContentExclusive());
	}

	#[Group('units')]
	public function testBuildWithValidItemsAndShufflePicking(): void
	{
		$this->mimeTypeExtensionMapperMock->expects($this->never())->method($this->anything());
		$playlist = ['shuffle' => 1, 'shuffle_picking' => 4, 'playlist_mode' => PlaylistMode::MASTER->value];
		$itemData = [
			[
				'item_type' => ItemType::MEDIAPOOL->value,
				'begin_trigger' => [],
				'flags' => 0,
				'datasource' => ItemDatasource::FILE->value,
				'file_resource' => 'file',
				'extension' => 'mp4'
			]
		];

		$videoMock = $this->createMock(Video::class);
		$this->itemsFactoryMock->expects($this->once())->method('createItem')
			->with($itemData[0])
			->willReturn($videoMock);

		$link = 'https://content.server.com/delivery.php/file.mp4';
		$videoMock->method('setLink')->with($link);

		$videoMock->method('getSmilElementTag')->willReturn('smilTag');
		$videoMock->method('getPrefetchTag')->willReturn('prefetchTag');
		$videoMock->method('getExclusive')->willReturn('exclusiveTag');

		$this->playlistContent->init($playlist, $itemData)->build();
		// shuffle will be only one cause only one item is there
		$shuffle = $shuffle = Base::TABSTOPS_TAG.'<metadata>'."\n"
			.Base::TABSTOPS_PARAMETER.'<meta name="adapi:pickingAlgorithm" content="shuffle"/>'."\n"
			.Base::TABSTOPS_PARAMETER.'<meta name="adapi:pickingBehavior" content="pickN"/>'."\n"
			.Base::TABSTOPS_PARAMETER.'<meta name="adapi:pickNumber" content="1"/>'."\n"
			.Base::TABSTOPS_TAG.'</metadata>'."\n";

		static::assertSame($shuffle.'smilTag', $this->playlistContent->getContentElements());
		static::assertSame('prefetchTag', $this->playlistContent->getContentPrefetch());
		static::assertSame('exclusiveTag', $this->playlistContent->getContentExclusive());
	}

	#[Group('units')]
	public function testBuildWithExternalStream(): void
	{
		$this->mimeTypeExtensionMapperMock->expects($this->never())->method($this->anything());
		$playlist = ['shuffle' => 0, 'playlist_mode' => PlaylistMode::MASTER->value];
		$itemData = [
			[
				'item_type' => ItemType::MEDIA_EXTERN->value,
				'begin_trigger' => [],
				'flags' => 0,
				'datasource' => ItemDatasource::STREAM->value,
				'content_data' => serialize(['url' => 'https://acme.com/stream.mp4'])
			]
		];

		$videoMock = $this->createMock(Video::class);
		$this->itemsFactoryMock->expects($this->once())->method('createItem')
			->with($itemData[0])
			->willReturn($videoMock);

		$videoMock->method('setLink')->with('https://acme.com/stream.mp4');

		$videoMock->method('getSmilElementTag')->willReturn('smilTag');
		$videoMock->method('getPrefetchTag')->willReturn('prefetchTag');
		$videoMock->method('getExclusive')->willReturn('exclusiveTag');


		$this->playlistContent->init($playlist, $itemData)->build();

		static::assertSame('smilTag', $this->playlistContent->getContentElements());
		static::assertEmpty($this->playlistContent->getContentPrefetch());
		static::assertSame('exclusiveTag', $this->playlistContent->getContentExclusive());
	}

	#[Group('units')]
	public function testBuildWithExternalFile(): void
	{
		$this->mimeTypeExtensionMapperMock->expects($this->never())->method($this->anything());
		$playlist = ['shuffle' => 0, 'playlist_mode' => PlaylistMode::MASTER->value];
		$itemData = [
			[
				'item_type' => ItemType::MEDIA_EXTERN->value,
				'begin_trigger' => [],
				'flags' => 0,
				'datasource' => ItemDatasource::FILE->value,
				'mimetype'   => 'video/mp4',
				'content_data' => serialize(['url' => 'https://acme.com/stream.mp4'])
			]
		];

		$videoMock = $this->createMock(Video::class);
		$this->itemsFactoryMock->expects($this->once())->method('createItem')
			->with($itemData[0])
			->willReturn($videoMock);

		$videoMock->method('setLink')->with('https://acme.com/stream.mp4');

		$videoMock->method('getSmilElementTag')->willReturn('smilTag');
		$videoMock->method('getPrefetchTag')->willReturn('prefetchTag');
		$videoMock->method('getExclusive')->willReturn('exclusiveTag');


		$this->playlistContent->init($playlist, $itemData)->build();

		static::assertSame('smilTag', $this->playlistContent->getContentElements());
		static::assertSame('prefetchTag', $this->playlistContent->getContentPrefetch());
		static::assertSame('exclusiveTag', $this->playlistContent->getContentExclusive());
	}

	/**
	 * @throws ModuleException
	 * @throws Exception
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testBuildPlaylist(): void
	{
		$this->mimeTypeExtensionMapperMock->expects($this->never())->method($this->anything());
		$playlist = ['shuffle' => 0, 'playlist_mode' => PlaylistMode::MASTER->value];
		$itemData = [
			[
				'begin_trigger' => [],
				'flags' => 0,
				'item_type' => ItemType::PLAYLIST->value
			]
		];

		$playlistStub = $this->createStub(SeqContainer::class);
		$this->itemsFactoryMock->expects($this->once())->method('createItem')
			->with($itemData[0])
			->willReturn($playlistStub);

		$playlistStub->method('getSmilElementTag')->willReturn('smilTag');
		$playlistStub->method('getPrefetchTag')->willReturn('prefetchTag');
		$playlistStub->method('getExclusive')->willReturn('exclusiveTag');

		$this->playlistContent->init($playlist, $itemData)->build();

		static::assertSame('smilTag', $this->playlistContent->getContentElements());
		static::assertSame('prefetchTag', $this->playlistContent->getContentPrefetch());
		static::assertSame('exclusiveTag', $this->playlistContent->getContentExclusive());
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testUnknown(): void
	{
		$this->itemsFactoryMock->expects($this->never())->method($this->anything());
		$this->mimeTypeExtensionMapperMock->expects($this->never())->method($this->anything());
		$playlist = ['shuffle' => 0, 'playlist_mode' => PlaylistMode::MASTER->value];
		$itemData = [
			[
				'item_id' => 2,
				'item_type' => 'unknown',
				'begin_trigger' => []
			]
		];

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Unknown item type. Given: unknown with item id: 2.');

		$this->playlistContent->init($playlist, $itemData)->build();

	}

}