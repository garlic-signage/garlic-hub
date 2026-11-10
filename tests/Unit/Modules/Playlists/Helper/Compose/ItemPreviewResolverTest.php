<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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
use App\Modules\Playlists\Helper\Compose\ItemPreviewResolver;
use App\Modules\Playlists\Helper\ItemType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

class ItemPreviewResolverTest extends TestCase
{
	private const string CHECKSUM = 'd41d8cd98f00b204e9800998ecf8427e';

	private MediaUrlSigner $mediaUrlSigner;
	private ItemPreviewResolver $resolver;
	private string $originalsPath;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->originalsPath = sys_get_temp_dir() . '/item_preview_resolver_test' . (string) getenv('TEST_TOKEN');
		if (!is_dir($this->originalsPath))
			mkdir($this->originalsPath);

		$configStub = $this->createStub(Config::class);
		$configStub->method('getConfigValue')->willReturnMap([
			['originals', 'playlists', 'directories', $this->originalsPath],
		]);

		$this->mediaUrlSigner = new MediaUrlSigner('unit-test-secret');
		$this->resolver       = new ItemPreviewResolver($configStub, $this->mediaUrlSigner);
	}

	protected function tearDown(): void
	{
		array_map('unlink', glob($this->originalsPath . '/*') ?: []);
		rmdir($this->originalsPath);
		parent::tearDown();
	}

	/**
	 * @param array<string,mixed> $fields
	 * @return array<string,mixed>
	 */
	private function mediaItem(string $mimetype, string $extension, array $fields = []): array
	{
		return $fields + [
			'item_type'     => ItemType::MEDIAPOOL->value,
			'file_resource' => self::CHECKSUM,
			'mimetype'      => $mimetype,
			'extension'     => $extension,
			'content_data'  => '',
			'urls'          => ['thumbnail' => '/thumbnail.jpg'],
		];
	}

	/**
	 * @return array<string,array{string,string,string}>
	 */
	public static function media(): array
	{
		return [
			'an image' => ['image/png', 'png', ItemPreviewResolver::TYPE_IMAGE],
			'a video'  => ['video/mp4', 'mp4', ItemPreviewResolver::TYPE_VIDEO],
			'an audio' => ['audio/mpeg', 'mp3', ItemPreviewResolver::TYPE_AUDIO],
			'a pdf'    => ['application/pdf', 'pdf', ItemPreviewResolver::TYPE_PDF],
		];
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	#[DataProvider('media')]
	public function testMediaShowTheirOriginal(string $mimetype, string $extension, string $type): void
	{
		$preview = $this->resolver->resolve($this->mediaItem($mimetype, $extension));

		static::assertSame(['type' => $type, 'url' => $this->mediaUrlSigner->originalUrl(self::CHECKSUM, $extension)], $preview);
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testOtherMediaShowTheirThumbnail(): void
	{
		$preview = $this->resolver->resolve($this->mediaItem('application/zip', 'zip'));

		static::assertSame(['type' => ItemPreviewResolver::TYPE_IMAGE, 'url' => '/thumbnail.jpg'], $preview);
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testAMediaWithoutFileShowsItsThumbnail(): void
	{
		$preview = $this->resolver->resolve($this->mediaItem('image/png', ''));

		static::assertSame(['type' => ItemPreviewResolver::TYPE_IMAGE, 'url' => '/thumbnail.jpg'], $preview);
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testAWidgetGetsTheValuesOfTheItem(): void
	{
		$item = $this->mediaItem('application/widget', 'wgt', ['content_data' => serialize(['feedUrl' => 'https://example.com/rss', 'font_size' => 30])]);

		$preview = $this->resolver->resolve($item);

		static::assertSame(ItemPreviewResolver::TYPE_WIDGET, $preview['type']);
		static::assertSame($this->mediaUrlSigner->widgetUrl(self::CHECKSUM, ['feedUrl' => 'https://example.com/rss', 'font_size' => 30]), $preview['url']);
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testAWidgetWithoutValuesHasNoQuery(): void
	{
		$preview = $this->resolver->resolve($this->mediaItem('application/widget', 'wgt'));

		static::assertSame($this->mediaUrlSigner->widgetUrl(self::CHECKSUM), $preview['url']);
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testAWidgetLeavesOutValuesWhichAreNoText(): void
	{
		$item = $this->mediaItem('application/widget', 'wgt', ['content_data' => serialize(['title' => 'News', 'list' => ['a', 'b']])]);

		$preview = $this->resolver->resolve($item);

		static::assertSame($this->mediaUrlSigner->widgetUrl(self::CHECKSUM, ['title' => 'News']), $preview['url']);
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testAWidgetWithBrokenValuesHasNoQuery(): void
	{
		$preview = $this->resolver->resolve($this->mediaItem('application/widget', 'wgt', ['content_data' => 'O:8:"stdClass":0:{}']));

		static::assertSame($this->mediaUrlSigner->widgetUrl(self::CHECKSUM), $preview['url']);
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testAChannelShowsItsThumbnail(): void
	{
		$preview = $this->resolver->resolve(['item_type' => ItemType::CHANNEL->value, 'urls' => ['thumbnail' => '/images/icons/channel.svg']]);

		static::assertSame(['type' => ItemPreviewResolver::TYPE_IMAGE, 'url' => '/images/icons/channel.svg'], $preview);
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testANestedPlaylistIsAList(): void
	{
		$preview = $this->resolver->resolve(['item_type' => ItemType::PLAYLIST->value, 'urls' => ['thumbnail' => '/images/icons/playlist.svg']]);

		static::assertSame(['type' => ItemPreviewResolver::TYPE_PLAYLIST, 'url' => ''], $preview);
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testATemplateShowsItsOriginalImage(): void
	{
		touch($this->originalsPath . '/34-0123456789abcdef0123456789abcdef.png');
		$item = [
			'item_type' => ItemType::TEMPLATE->value,
			'paths'     => ['thumbnail' => '/path/to/item/thumbs/34-0123456789abcdef0123456789abcdef.png'],
			'urls'      => ['thumbnail' => '/thumbnail.png'],
		];

		$preview = $this->resolver->resolve($item);

		static::assertSame(['type' => ItemPreviewResolver::TYPE_IMAGE, 'url' => $this->mediaUrlSigner->itemOriginalUrl('34-0123456789abcdef0123456789abcdef', 'png')], $preview);
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testATemplateWithoutOriginalShowsItsThumbnail(): void
	{
		$item = [
			'item_type' => ItemType::TEMPLATE->value,
			'paths'     => ['thumbnail' => '/path/to/item/thumbs/34.jpg'],
			'urls'      => ['thumbnail' => '/thumbnail.jpg'],
		];

		$preview = $this->resolver->resolve($item);

		static::assertSame(['type' => ItemPreviewResolver::TYPE_IMAGE, 'url' => '/thumbnail.jpg'], $preview);
	}
}
