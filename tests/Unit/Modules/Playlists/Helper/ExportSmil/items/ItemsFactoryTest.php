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
namespace Tests\Unit\Modules\Playlists\Helper\ExportSmil\items;

use App\Framework\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Playlists\Helper\ExportSmil\items\Audio;
use App\Modules\Playlists\Helper\ExportSmil\items\Image;
use App\Modules\Playlists\Helper\ExportSmil\items\ItemInterface;
use App\Modules\Playlists\Helper\ExportSmil\items\ItemsFactory;
use App\Modules\Playlists\Helper\ExportSmil\items\Text;
use App\Modules\Playlists\Helper\ExportSmil\items\Video;
use App\Modules\Playlists\Helper\ExportSmil\items\Widget;
use App\Modules\Playlists\Helper\ItemType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

class ItemsFactoryTest extends TestCase
{
	private ItemsFactory $itemsFactory;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		// the config only provides default values for the items
		$configStub = $this->createStub(Config::class);
		$configStub->method('getConfigValue')
			->willReturnMap([
				['fit', 'playlists', 'Defaults', 'meetBest'],
				['media_align', 'playlists', 'Defaults', 'center'],
				['volume', 'playlists', 'Defaults', '100']
			]);
		$this->itemsFactory = new ItemsFactory($configStub);
	}

	/**
	 * @throws CoreException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testCreateItemReturnsImage(): void
	{
		$item = [
			'item_type' => ItemType::MEDIAPOOL->value,
			'mimetype' => 'image/xxx',
			'properties' => [],
			'begin_trigger' => [],
			'end_trigger' => [],
			'conditional' => []
		];

		$result = $this->itemsFactory->createItem($item);
		static::assertInstanceOf(Image::class, $result);
	}

	/**
	 * @throws CoreException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testCreateItemReturnsVideo(): void
	{
		$item = [
			'item_type' => ItemType::MEDIAPOOL->value,
			'mimetype' => 'video/xlsx',
			'properties' => [],
			'begin_trigger' => [],
			'end_trigger' => [],
			'conditional' => []
		];

		$result = $this->itemsFactory->createItem($item);
		static::assertInstanceOf(Video::class, $result);
	}

	/**
	 * @throws CoreException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testCreateItemReturnsAudio(): void
	{
		$item = [
			'item_type' => ItemType::MEDIAPOOL->value,
			'mimetype' => 'audio/xxxx',
			'properties' => [],
			'begin_trigger' => [],
			'end_trigger' => [],
			'conditional' => []
		];

		$result = $this->itemsFactory->createItem($item);
		static::assertInstanceOf(Audio::class, $result);
	}

	/**
	 * @throws CoreException|ModuleException
	 */
	#[Group('units')]
	public function testCreateItemReturnsWidget(): void
	{
		$item = [
			'item_type' => ItemType::MEDIAPOOL->value,
			'mimetype' => 'application/xxxx',
			'properties' => [],
			'begin_trigger' => [],
			'end_trigger' => [],
			'conditional' => []
		];

		$result = $this->itemsFactory->createItem($item);
		static::assertInstanceOf(Widget::class, $result);
	}

	/**
	 * @throws CoreException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testCreateItemReturnsText(): void
	{
		$item = [
			'item_type' => ItemType::MEDIAPOOL->value,
			'mimetype' => 'text/xxx',
			'properties' => [],
			'begin_trigger' => [],
			'end_trigger' => [],
			'conditional' => []
		];

		$result = $this->itemsFactory->createItem($item);
		static::assertInstanceOf(Text::class, $result);
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testCreateItemUnsupportedMedia(): void
	{
		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Unsupported media type: acme.');

		$item = [
			'item_type' => ItemType::MEDIAPOOL->value,
			'mimetype' => 'acme/xxx'
		];

		$this->itemsFactory->createItem($item);
	}


	/**
	 * @throws ModuleException
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testCreateItemReturnsPlaylistType(): void
	{
		$item = [
			'item_type' => ItemType::PLAYLIST->value,
			'properties' => [],
			'begin_trigger' => [],
			'end_trigger' => [],
			'conditional' => []
		];

		$result = $this->itemsFactory->createItem($item);
		// @phpstan-ignore-next-line
		static::assertInstanceOf(ItemInterface::class, $result);
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testCreateItemThrowsExceptionForUnsupportedType(): void
	{
		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Unsupported item type: unsupported_type.');

		$item = [
			'item_type' => 'unsupported_type',
		];

		$this->itemsFactory->createItem($item);
	}
}
