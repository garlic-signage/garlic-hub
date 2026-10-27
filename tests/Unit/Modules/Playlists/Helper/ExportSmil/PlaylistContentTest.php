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

use App\Framework\Config\Config;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Media\DeliveryUrlBuilder;
use App\Framework\Media\MimeTypeExtensionMapper;
use App\Modules\Playlists\Helper\ExportSmil\items\Base;
use App\Modules\Playlists\Helper\ExportSmil\items\ItemsFactory;
use App\Modules\Playlists\Helper\ExportSmil\PlaylistContent;
use App\Modules\Playlists\Helper\ItemDatasource;
use App\Modules\Playlists\Helper\ItemFlags;
use App\Modules\Playlists\Helper\ItemType;
use App\Modules\Playlists\Helper\PlaylistMode;
use App\Modules\Playlists\Helper\TemplateItemName;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The SMIL parts of a playlist from the items as the database gives them: the real items write the tags.
 */
class PlaylistContentTest extends TestCase
{
	private PlaylistContent $playlistContent;

	protected function setUp(): void
	{
		parent::setUp();
		$config = $this->createStub(Config::class);
		$config->method('getConfigValue')->willReturnMap([
			['fit', 'playlists', 'Defaults', 'meetBest'],
			['media_align', 'playlists', 'Defaults', 'center'],
			['volume', 'playlists', 'Defaults', '100'],
		]);

		$this->playlistContent = new PlaylistContent(new ItemsFactory($config), new DeliveryUrlBuilder('https://content.server.com'), new MimeTypeExtensionMapper(), new TemplateItemName('unit-test-secret'));
	}

	/**
	 * @param array<string,mixed> $changes
	 * @return array<string,mixed>
	 */
	private static function video(array $changes = []): array
	{
		return $changes + [
			'item_id' => 1, 'item_type' => ItemType::MEDIAPOOL->value, 'item_name' => 'film.mp4', 'item_duration' => 30, 'flags' => 0,
			'datasource' => ItemDatasource::FILE->value, 'mimetype' => 'video/mp4', 'file_resource' => 'checksum', 'extension' => 'mp4',
			'properties' => [], 'conditional' => [], 'begin_trigger' => [], 'end_trigger' => [], 'content_data' => [], 'categories' => [],
		];
	}

	/**
	 * @param array<string,mixed> $playlist
	 * @param list<array<string,mixed>> $items
	 */
	private function build(array $items, array $playlist = []): PlaylistContent
	{
		return $this->playlistContent->init($playlist + ['shuffle' => 0, 'shuffle_picking' => 0, 'playlist_mode' => PlaylistMode::MASTER->value], $items)->build();
	}

	#[Group('units')]
	public function testAMediaIsLinkedToTheDeliveryAndPrefetched(): void
	{
		$content = $this->build([self::video()]);

		static::assertStringContainsString('<video title="film.mp4"', $content->getContentElements());
		static::assertStringContainsString('src="https://content.server.com/delivery.php/checksum.mp4"', $content->getContentElements());
		static::assertStringContainsString('dur="30s"', $content->getContentElements());
		static::assertStringContainsString('src="https://content.server.com/delivery.php/checksum.mp4"', $content->getContentPrefetch());
		static::assertSame('', $content->getContentExclusive());
	}

	#[Group('units')]
	public function testATemplateLinksToTheRenderedImageOfTheItem(): void
	{
		$template = self::video(['item_id' => 13, 'item_type' => ItemType::TEMPLATE->value, 'item_name' => 'Vorlage', 'mimetype' => 'image/jpeg', 'extension' => null, 'file_resource' => '7', 'content_data' => '{"objects":[]}']);
		$name     = (new TemplateItemName('unit-test-secret'))->build(13, '{"objects":[]}');

		$content = $this->build([$template]);

		static::assertStringContainsString('src="https://content.server.com/delivery.php/tpl/' . $name . '.jpg"', $content->getContentElements());
		static::assertStringContainsString('delivery.php/tpl/' . $name . '.jpg', $content->getContentPrefetch());
	}

	#[Group('units')]
	public function testTheItemsAreInTheOrderOfTheList(): void
	{
		$content = $this->build([self::video(['item_name' => 'first']), self::video(['item_id' => 2, 'item_name' => 'second', 'file_resource' => 'other'])]);

		static::assertLessThan(strpos($content->getContentElements(), 'title="second"'), strpos($content->getContentElements(), 'title="first"'));
	}

	#[Group('units')]
	public function testADisabledItemIsPrefetchedButNotPlayed(): void
	{
		$content = $this->build([self::video(['flags' => ItemFlags::disabled->value])]);

		static::assertSame('', $content->getContentElements());
		static::assertStringContainsString('delivery.php/checksum.mp4', $content->getContentPrefetch());
	}

	#[Group('units')]
	public function testAnExternalFileIsLinkedAndPrefetched(): void
	{
		$item = self::video(['item_type' => ItemType::MEDIA_EXTERN->value, 'file_resource' => '', 'content_data' => serialize(['url' => 'https://acme.com/video.mp4'])]);

		$content = $this->build([$item]);

		static::assertStringContainsString('src="https://acme.com/video.mp4"', $content->getContentElements());
		static::assertStringContainsString('acme.com/video.mp4', $content->getContentPrefetch());
	}

	#[Group('units')]
	public function testTheAddressOfAnExternalFileKeepsItsQueryString(): void
	{
		$item = self::video(['item_type' => ItemType::MEDIA_EXTERN->value, 'file_resource' => '', 'content_data' => serialize(['url' => 'https://acme.com/video.mp4?a=1&b=2'])]);

		$content = $this->build([$item]);

		static::assertStringContainsString('src="https://acme.com/video.mp4?a=1&amp;b=2"', $content->getContentElements());
		static::assertStringContainsString('<prefetch src="https://acme.com/video.mp4?a=1&amp;b=2" />', $content->getContentPrefetch());
	}

	#[Group('units')]
	public function testTheAddressOfAnExternalFileCanNotLeaveTheAttribute(): void
	{
		$item = self::video(['item_type' => ItemType::MEDIA_EXTERN->value, 'file_resource' => '', 'content_data' => serialize(['url' => 'https://acme.com/v.mp4?a="x" onload="y"&b=<c>'])]);

		$content = $this->build([$item]);

		static::assertStringContainsString('src="https://acme.com/v.mp4?a=&quot;x&quot; onload=&quot;y&quot;&amp;b=&lt;c&gt;"', $content->getContentElements());
		static::assertStringNotContainsString('onload="', $content->getContentElements() . $content->getContentPrefetch());
	}

	#[Group('units')]
	public function testAnExternalStreamIsNotPrefetched(): void
	{
		$item = self::video(['item_type' => ItemType::MEDIA_EXTERN->value, 'datasource' => ItemDatasource::STREAM->value, 'content_data' => serialize(['url' => 'https://acme.com/stream.mp4'])]);

		$content = $this->build([$item]);

		static::assertStringContainsString('src="https://acme.com/stream.mp4"', $content->getContentElements());
		static::assertSame('', $content->getContentPrefetch());
	}

	#[Group('units')]
	public function testANestedPlaylistIsAPlaceholderForItsItems(): void
	{
		$item = self::video(['item_type' => ItemType::PLAYLIST->value, 'item_name' => 'Kantine', 'file_resource' => '2', 'mimetype' => '', 'time_limit' => 0, 'owner_duration' => 0]);

		$content = $this->build([$item]);

		static::assertStringContainsString('<seq title="Kantine"', $content->getContentElements());
		static::assertStringContainsString('{ITEMS_2}', $content->getContentElements());
		static::assertStringContainsString('{PREFETCH_2}', $content->getContentPrefetch());
	}

	#[Group('units')]
	public function testAShuffledPlaylistPicksFromAllItems(): void
	{
		$content = $this->build([self::video()], ['shuffle' => 1, 'shuffle_picking' => 0]);

		static::assertStringStartsWith(Base::TABSTOPS_TAG . '<metadata><meta name="adapi:pickingAlgorithm" content="shuffle"/></metadata>' . "\n", $content->getContentElements());
	}

	#[Group('units')]
	public function testThePickingIsNeverMoreThanTheItemsWhichArePlayed(): void
	{
		$content = $this->build([self::video()], ['shuffle' => 1, 'shuffle_picking' => 4]);

		static::assertStringStartsWith(
			Base::TABSTOPS_TAG . '<metadata>' . "\n"
			. Base::TABSTOPS_PARAMETER . '<meta name="adapi:pickingAlgorithm" content="shuffle"/>' . "\n"
			. Base::TABSTOPS_PARAMETER . '<meta name="adapi:pickingBehavior" content="pickN"/>' . "\n"
			. Base::TABSTOPS_PARAMETER . '<meta name="adapi:pickNumber" content="1"/>' . "\n"
			. Base::TABSTOPS_TAG . '</metadata>' . "\n",
			$content->getContentElements()
		);
	}

	#[Group('units')]
	public function testAPlaylistWithoutPlayedItemsHasNoShuffle(): void
	{
		$content = $this->build([self::video(['flags' => ItemFlags::disabled->value])], ['shuffle' => 1]);

		static::assertSame('', $content->getContentElements());
	}

	#[Group('units')]
	public function testAnItemWithATouchTriggerIsTheTargetOfTheOthers(): void
	{
		$source = self::video(['item_id' => 1, 'item_name' => 'source', 'begin_trigger' => ['touches' => [['touch_item_id' => 2]]]]);
		$target = self::video(['item_id' => 2, 'item_name' => 'target', 'file_resource' => 'other']);

		$content = $this->build([$source, $target]);

		static::assertStringContainsString('xml:id="triggerMediaId-2"', $content->getContentElements() . $content->getContentExclusive());
		static::assertStringContainsString('triggerMediaId-2.activateEvent', $content->getContentElements() . $content->getContentExclusive());
	}

	#[Group('units')]
	public function testAnUnknownItemTypeIsAnError(): void
	{
		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Unknown item type. Given: unknown with item id: 2.');

		$this->build([['item_id' => 2, 'item_type' => 'unknown', 'begin_trigger' => []]]);
	}
}
