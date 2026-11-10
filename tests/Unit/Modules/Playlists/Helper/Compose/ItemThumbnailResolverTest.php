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

namespace Tests\Unit\Modules\Playlists\Helper\Compose;

use App\Framework\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Media\MediaUrlSigner;
use App\Modules\Playlists\Helper\Compose\ItemThumbnailResolver;
use App\Modules\Playlists\Helper\ItemType;
use App\Modules\Playlists\Helper\TemplateItemName;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class ItemThumbnailResolverTest extends TestCase
{
	private Config&Stub $configStub;
	private ItemThumbnailResolver $resolver;
	private string $thumbnailPath;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->thumbnailPath = sys_get_temp_dir() . '/item_thumbnail_resolver_test' . (string) getenv('TEST_TOKEN');
		if (!is_dir($this->thumbnailPath))
			mkdir($this->thumbnailPath);

		$this->configStub = $this->createStub(Config::class);
		$this->configStub->method('getConfigValue')->willReturnMap([
			['thumbnails', 'mediapool', 'directories', $this->thumbnailPath],
			['thumbnails', 'playlists', 'directories', '/path/to/item/thumbs'],
		]);

		$this->resolver = new ItemThumbnailResolver(
			$this->configStub,
			new MediaUrlSigner('unit-test-secret'),
			new TemplateItemName('unit-test-secret')
		);
	}

	protected function tearDown(): void
	{
		array_map('unlink', glob($this->thumbnailPath . '/*') ?: []);
		rmdir($this->thumbnailPath);
		parent::tearDown();
	}

	/**
	 * The format of the thumbnail is the one the upload stored: a widget icon is not always a png.
	 *
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testMediapoolUsesTheStoredThumbnailExtension(): void
	{
		$result = $this->resolver->resolve(['item_type' => ItemType::MEDIAPOOL->value, 'mimetype' => 'application/widget', 'file_resource' => 'widget_checksum', 'content_data' => 'a:0:{}', 'thumb_extension' => 'jpg']);

		static::assertNotNull($result);
		static::assertSame($this->thumbnailPath . '/widget_checksum.jpg', $result['path']);
		static::assertStringStartsWith('/media.php/thumb/widget_checksum.jpg?exp=', $result['url']);
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testMediapoolWithoutMediapoolFileUsesJpg(): void
	{
		$withNull  = $this->resolver->resolve(['item_type' => ItemType::MEDIAPOOL->value, 'mimetype' => 'image/png', 'file_resource' => 'gone', 'thumb_extension' => null]);
		$withEmpty = $this->resolver->resolve(['item_type' => ItemType::MEDIAPOOL->value, 'mimetype' => 'image/png', 'file_resource' => 'gone', 'thumb_extension' => '']);
		$without   = $this->resolver->resolve(['item_type' => ItemType::MEDIAPOOL->value, 'mimetype' => 'image/png', 'file_resource' => 'gone']);

		static::assertSame(
			[$this->thumbnailPath . '/gone.jpg', $this->thumbnailPath . '/gone.jpg', $this->thumbnailPath . '/gone.jpg'],
			[$withNull['path'] ?? '', $withEmpty['path'] ?? '', $without['path'] ?? '']
		);
	}

	/**
	 * The directories of the config are relative to the system dir, not to the working dir of the request.
	 *
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testRelativeDirectoriesAreBelowTheSystemDir(): void
	{
		$names = new TemplateItemName('unit-test-secret');
		$relativeDir = 'item_thumbs_relative_test' . (string) getenv('TEST_TOKEN');
		$dir         = sys_get_temp_dir() . '/' . $relativeDir;
		mkdir($dir);
		touch($dir . '/18.png');
		$config = $this->createStub(Config::class);
		$config->method('getConfigValue')->willReturnMap([
			['thumbnails', 'mediapool', 'directories', 'mediapool/thumbs'],
			['thumbnails', 'playlists', 'directories', $relativeDir],
		]);
		$config->method('getPaths')->willReturnMap([['systemDir', sys_get_temp_dir()]]);
		$resolver = new ItemThumbnailResolver($config, new MediaUrlSigner('unit-test-secret'), $names);

		try
		{
			$media    = $resolver->resolve(['item_type' => ItemType::MEDIAPOOL->value, 'file_resource' => 'checksum', 'thumb_extension' => 'png']);
			$template = $resolver->resolve(['item_type' => ItemType::TEMPLATE->value, 'item_id' => 18, 'content_data' => 'content']);
		}
		finally
		{
			array_map('unlink', glob($dir . '/*') ?: []);
			rmdir($dir);
		}

		static::assertSame(
			[sys_get_temp_dir() . '/mediapool/thumbs/checksum.png', $dir . '/18.png'],
			[$media['path'] ?? '', $template['path'] ?? '']
		);
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testPlaylistUsesStaticIcon(): void
	{

		$result = $this->resolver->resolve(['item_type' => ItemType::PLAYLIST->value, 'mimetype' => '', 'file_resource' => '']);

		static::assertSame(['path' => 'public/images/icons/playlist.svg', 'url' => '/images/icons/playlist.svg'], $result);
	}

	/**
	 * @throws CoreException
	 */
	/**
	 * The thumbnail of an item keeps the format of the saved image: the preview takes the one which exists.
	 * Before the first export after an update the thumbnail is named by the item id only.
	 */
	#[Group('units')]
	public function testTemplateFindsTheThumbnailInTheFormatWhichWasSaved(): void
	{
		$names = new TemplateItemName('unit-test-secret');
		$dir   = sys_get_temp_dir() . '/item_thumbs_resolver_test' . (string) getenv('TEST_TOKEN');
		mkdir($dir);
		touch($dir . '/' . $names->build(17, 'content') . '.png');
		touch($dir . '/18.webp');
		$config = $this->createStub(Config::class);
		$config->method('getConfigValue')->willReturnMap([['thumbnails', 'playlists', 'directories', $dir]]);
		$resolver = new ItemThumbnailResolver($config, new MediaUrlSigner('unit-test-secret'), $names);

		try
		{
			$png  = $resolver->resolve(['item_type' => ItemType::TEMPLATE->value, 'item_id' => 17, 'content_data' => 'content']);
			$webp = $resolver->resolve(['item_type' => ItemType::TEMPLATE->value, 'item_id' => 18, 'content_data' => 'content']);
			$none = $resolver->resolve(['item_type' => ItemType::TEMPLATE->value, 'item_id' => 19, 'content_data' => 'content']);
		}
		finally
		{
			array_map('unlink', glob($dir . '/*') ?: []);
			rmdir($dir);
		}

		static::assertSame(
			[$dir . '/' . $names->build(17, 'content') . '.png', $dir . '/18.webp', $dir . '/' . $names->build(19, 'content') . '.jpg'],
			[$png['path'] ?? '', $webp['path'] ?? '', $none['path'] ?? '']
		);
		static::assertStringStartsWith('/media.php/item-thumb/' . $names->build(17, 'content') . '.png?exp=', $png['url'] ?? '');
		static::assertStringStartsWith('/media.php/item-thumb/18.webp?exp=', $webp['url'] ?? '');
	}

	/**
	 * The composer saved a new content which is not exported yet: the preview shows the new one.
	 */
	#[Group('units')]
	public function testTemplatePrefersTheThumbnailOfTheSavedContent(): void
	{
		$names = new TemplateItemName('unit-test-secret');
		$dir   = sys_get_temp_dir() . '/item_thumbs_resolver_test' . (string) getenv('TEST_TOKEN');
		mkdir($dir);
		touch($dir . '/17.jpg');
		touch($dir . '/' . $names->build(17, 'old') . '.jpg');
		touch($dir . '/' . $names->build(17, 'new') . '.jpg');
		$config = $this->createStub(Config::class);
		$config->method('getConfigValue')->willReturnMap([['thumbnails', 'playlists', 'directories', $dir]]);
		$resolver = new ItemThumbnailResolver($config, new MediaUrlSigner('unit-test-secret'), $names);

		try
		{
			$result = $resolver->resolve(['item_type' => ItemType::TEMPLATE->value, 'item_id' => 17, 'content_data' => 'new']);
		}
		finally
		{
			array_map('unlink', glob($dir . '/*') ?: []);
			rmdir($dir);
		}

		static::assertSame($dir . '/' . $names->build(17, 'new') . '.jpg', $result['path'] ?? '');
	}

	/**
	 * The composer saves one format; a jpg next to another format is the one, like the export names it.
	 */
	#[Group('units')]
	public function testTemplateLooksForTheJpgFirst(): void
	{
		$names = new TemplateItemName('unit-test-secret');
		$dir   = sys_get_temp_dir() . '/item_thumbs_resolver_test' . (string) getenv('TEST_TOKEN');
		mkdir($dir);
		touch($dir . '/' . $names->build(17, 'new') . '.jpg');
		touch($dir . '/' . $names->build(17, 'new') . '.png');
		$config = $this->createStub(Config::class);
		$config->method('getConfigValue')->willReturnMap([['thumbnails', 'playlists', 'directories', $dir]]);
		$resolver = new ItemThumbnailResolver($config, new MediaUrlSigner('unit-test-secret'), $names);

		try
		{
			$result = $resolver->resolve(['item_type' => ItemType::TEMPLATE->value, 'item_id' => 17, 'content_data' => 'new']);
		}
		finally
		{
			array_map('unlink', glob($dir . '/*') ?: []);
			rmdir($dir);
		}

		static::assertSame($dir . '/' . $names->build(17, 'new') . '.jpg', $result['path'] ?? '');
	}

	#[Group('units')]
	public function testTemplateUsesJpgNamedByItemIdAndContent(): void
	{
		$name = (new TemplateItemName('unit-test-secret'))->build(17, '');

		$result = $this->resolver->resolve(['item_type' => ItemType::TEMPLATE->value, 'item_id' => '17']);

		static::assertNotNull($result);
		static::assertSame('/path/to/item/thumbs/' . $name . '.jpg', $result['path']);
		static::assertStringStartsWith('/media.php/item-thumb/' . $name . '.jpg?exp=', $result['url']);
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testUnknownItemTypeHasNoThumbnail(): void
	{

		static::assertNull($this->resolver->resolve(['item_type' => 'unknown']));
	}
}
