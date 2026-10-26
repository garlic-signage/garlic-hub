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
use App\Framework\Media\MimeTypeExtensionMapper;
use App\Modules\Playlists\Helper\Compose\ItemThumbnailResolver;
use App\Modules\Playlists\Helper\ItemType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class ItemThumbnailResolverTest extends TestCase
{
	private Config&Stub $configStub;
	private MimeTypeExtensionMapper&Stub $mimeTypeExtensionMapperStub;
	private ItemThumbnailResolver $resolver;
	private string $thumbnailPath;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->thumbnailPath = sys_get_temp_dir() . '/item_thumbnail_resolver_test';
		if (!is_dir($this->thumbnailPath))
			mkdir($this->thumbnailPath);

		$this->configStub = $this->createStub(Config::class);
		$this->configStub->method('getConfigValue')->willReturnMap([
			['thumbnails', 'mediapool', 'directories', $this->thumbnailPath],
			['thumbnails', 'playlists', 'directories', '/path/to/item/thumbs'],
		]);
		$this->mimeTypeExtensionMapperStub = $this->createStub(MimeTypeExtensionMapper::class);

		$this->resolver = new ItemThumbnailResolver(
			$this->configStub,
			$this->mimeTypeExtensionMapperStub,
			new MediaUrlSigner('unit-test-secret')
		);
	}

	protected function tearDown(): void
	{
		array_map('unlink', glob($this->thumbnailPath . '/*') ?: []);
		rmdir($this->thumbnailPath);
		parent::tearDown();
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testVideoUsesJpgThumbnail(): void
	{

		$result = $this->resolver->resolve(['item_type' => ItemType::MEDIAPOOL->value, 'mimetype' => 'video/webm', 'file_resource' => 'video_checksum']);

		static::assertNotNull($result);
		static::assertSame($this->thumbnailPath . '/video_checksum.jpg', $result['path']);
		static::assertStringStartsWith('/media.php/thumb/video_checksum.jpg?exp=', $result['url']);
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testImageUsesExtensionOfMimetype(): void
	{
		$this->mimeTypeExtensionMapperStub->method('determineExtension')
			->willReturnMap([['image/png', 'png']]);

		$result = $this->resolver->resolve(['item_type' => ItemType::MEDIAPOOL->value, 'mimetype' => 'image/png', 'file_resource' => 'image_checksum']);

		static::assertNotNull($result);
		static::assertSame($this->thumbnailPath . '/image_checksum.png', $result['path']);
		static::assertStringStartsWith('/media.php/thumb/image_checksum.png?exp=', $result['url']);
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testSvgWidgetWithExistingSvgThumbnail(): void
	{
		touch($this->thumbnailPath . '/widget_checksum.svg');

		$result = $this->resolver->resolve(['item_type' => ItemType::MEDIAPOOL->value, 'mimetype' => 'application/widget', 'file_resource' => 'widget_checksum', 'content_data' => '']);

		static::assertNotNull($result);
		static::assertSame($this->thumbnailPath . '/widget_checksum.svg', $result['path']);
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testWidgetFallsBackToPngWithoutThumbnailFile(): void
	{

		$result = $this->resolver->resolve(['item_type' => ItemType::MEDIAPOOL->value, 'mimetype' => 'application/widget', 'file_resource' => 'widget_checksum', 'content_data' => '']);

		static::assertNotNull($result);
		static::assertSame($this->thumbnailPath . '/widget_checksum.png', $result['path']);
		static::assertStringStartsWith('/media.php/thumb/widget_checksum.png?exp=', $result['url']);
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
	 */
	#[Group('units')]
	public function testTemplateFindsTheThumbnailInTheFormatWhichWasSaved(): void
	{
		$dir = sys_get_temp_dir() . '/item_thumbs_resolver_test';
		mkdir($dir);
		touch($dir . '/17.png');
		touch($dir . '/18.webp');
		$config = $this->createStub(Config::class);
		$config->method('getConfigValue')->willReturnMap([['thumbnails', 'playlists', 'directories', $dir]]);
		$resolver = new ItemThumbnailResolver($config, $this->mimeTypeExtensionMapperStub, new MediaUrlSigner('unit-test-secret'));

		try
		{
			$png  = $resolver->resolve(['item_type' => ItemType::TEMPLATE->value, 'item_id' => 17]);
			$webp = $resolver->resolve(['item_type' => ItemType::TEMPLATE->value, 'item_id' => 18]);
			$none = $resolver->resolve(['item_type' => ItemType::TEMPLATE->value, 'item_id' => 19]);
		}
		finally
		{
			array_map('unlink', glob($dir . '/*') ?: []);
			rmdir($dir);
		}

		static::assertSame([$dir . '/17.png', $dir . '/18.webp', $dir . '/19.jpg'], [$png['path'] ?? '', $webp['path'] ?? '', $none['path'] ?? '']);
		static::assertStringStartsWith('/media.php/item-thumb/17.png?exp=', $png['url'] ?? '');
	}

	#[Group('units')]
	public function testTemplateUsesJpgNamedByItemId(): void
	{

		$result = $this->resolver->resolve(['item_type' => ItemType::TEMPLATE->value, 'item_id' => '17']);

		static::assertNotNull($result);
		static::assertSame('/path/to/item/thumbs/17.jpg', $result['path']);
		static::assertStringStartsWith('/media.php/item-thumb/17.jpg?exp=', $result['url']);
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
