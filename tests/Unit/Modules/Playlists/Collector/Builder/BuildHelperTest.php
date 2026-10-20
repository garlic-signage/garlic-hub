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

use App\Modules\Playlists\Collector\Builder\BuildHelper;
use App\Modules\Playlists\Collector\Contracts\ContentReaderInterface;
use App\Modules\Playlists\Collector\Contracts\ExternalContentReaderInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class BuildHelperTest extends TestCase
{
	private ContentReaderInterface&MockObject $contentReaderMock;
	private ExternalContentReaderInterface&MockObject $externalContentReaderMock;
	private LoggerInterface&MockObject $loggerMock;
	private BuildHelper $buildHelper;


	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->contentReaderMock = $this->createMock(ContentReaderInterface::class);
		$this->externalContentReaderMock = $this->createMock(ExternalContentReaderInterface::class);
		$this->loggerMock = $this->createMock(LoggerInterface::class);

		$this->buildHelper = new BuildHelper($this->contentReaderMock,	$this->externalContentReaderMock, $this->loggerMock);
	}

	#[Group('units')]
	public function testCollectItemsWithNoPlaceholders(): void
	{
		$this->externalContentReaderMock->expects($this->never())->method($this->anything());
		$this->loggerMock->expects($this->never())->method($this->anything());
		$playlistId = 1;
		$playlistContent = 'Static content with no placeholders.';

		$this->contentReaderMock->method('init')
			->with($playlistId)
			->willReturn($this->contentReaderMock);

		$this->contentReaderMock->method('loadPlaylistItems')
			->willReturn($playlistContent);

		$result = $this->buildHelper->collectItems($playlistId);

		static::assertSame($playlistContent, $result);
	}

	#[Group('units')]
	public function testCollectItemsWithInternalPlaceholders(): void
	{
		$this->externalContentReaderMock->expects($this->never())->method($this->anything());
		$this->loggerMock->expects($this->never())->method($this->anything());
		$playlistId = 1;
		$playlistContent = 'Content before placeholder {ITEMS_2} content after.';
		$subPlaylistContent = 'SubPlaylist content.';

		$this->contentReaderMock->expects($this->atLeastOnce())->method('init')
			->willReturnOnConsecutiveCalls(
				$this->contentReaderMock,
				$this->contentReaderMock
			);

		$this->contentReaderMock
			->expects($this->atLeastOnce())->method('loadPlaylistItems')
			->willReturnOnConsecutiveCalls($playlistContent, $subPlaylistContent);

		$result = $this->buildHelper->collectItems($playlistId);

		$expected = "Content before placeholder \n$subPlaylistContent\n content after.";
		static::assertSame($expected, $result);
	}


	#[Group('units')]
	public function testCollectItemsWithRecursivePlaceholders(): void
	{
		$playlistId = 1;
		$playlistContent = 'Playlist content {ITEMS_2}.';
		$subPlaylistContent1 = 'SubPlaylist 2 content {ITEMS_3}.';
		$subPlaylistContent2 = 'Final resolved content.{ITEMS_4}';
		$subPlaylistContent3 = '';

		// only internal playlists are resolved, nothing goes wrong
		$this->externalContentReaderMock->expects($this->never())->method($this->anything());
		$this->loggerMock->expects($this->never())->method('error');

		// one level per playlist: 1 -> 2 -> 3 -> 4
		$this->contentReaderMock->expects($this->exactly(4))->method('init')
			->willReturn($this->contentReaderMock);

		$this->contentReaderMock->expects($this->exactly(4))->method('loadPlaylistItems')
			->willReturnOnConsecutiveCalls(
				$playlistContent,
				$subPlaylistContent1,
				$subPlaylistContent2,
				$subPlaylistContent3
			);

		$result = $this->buildHelper->collectItems($playlistId);

		$expected = "Playlist content \nSubPlaylist 2 content \nFinal resolved content.\n.\n.";
		static::assertSame($expected, $result);
	}

	
	#[Group('units')]
	public function testCollectItemsWithExternalPlaceholders(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$playlistId = 1;
		$playlistContent = 'Some content {ITEMS_0#https://example.com} more content.';
		$externalContent = 'External playlist content.';

		$this->contentReaderMock->method('init')
			->with($playlistId)
			->willReturn($this->contentReaderMock);

		$this->contentReaderMock->method('loadPlaylistItems')
			->willReturn($playlistContent);

		$this->externalContentReaderMock->method('init')
			->with('https://example.com')
			->willReturn($this->externalContentReaderMock);

		$this->externalContentReaderMock->method('loadPlaylistItems')
			->willReturn($externalContent);

		$result = $this->buildHelper->collectItems($playlistId);

		$expected = "Some content $externalContent more content.";
		static::assertSame($expected, $result);
	}

	#[Group('units')]
	public function testCollectItemsOnException(): void
	{
		$this->externalContentReaderMock->expects($this->never())->method($this->anything());
		$playlistId = 1;

		$this->contentReaderMock->expects($this->atLeastOnce())->method('init')
			->willThrowException(new RuntimeException("Error during collection."));

		$this->loggerMock->expects($this->once())->method('error')
			->with(static::stringContains("Error recurse items"));

		$result = $this->buildHelper->collectItems($playlistId);

		static::assertSame('', $result);
	}

	#[Group('units')]
	public function testCollectPrefetchesWithNoPlaceholders(): void
	{
		$this->externalContentReaderMock->expects($this->never())->method($this->anything());
		$this->loggerMock->expects($this->never())->method($this->anything());
		$playlistId = 1;
		$prefetchContent = "Static prefetch with no placeholders.";

		$this->contentReaderMock->method('init')
			->with($playlistId)
			->willReturn($this->contentReaderMock);

		$this->contentReaderMock->method('loadPlaylistPrefetch')
			->willReturn($prefetchContent);

		$result = $this->buildHelper->collectPrefetches($playlistId);

		static::assertSame($prefetchContent, $result);
	}


	#[Group('units')]
	public function testCollectPrefetchesWithInternalPlaceholders(): void
	{
		$this->externalContentReaderMock->expects($this->never())->method($this->anything());
		$this->loggerMock->expects($this->never())->method($this->anything());
		$playlistId = 1;
		$prefetchContent = "Prefetch content {PREFETCH_2}.";
		$subPrefetchContent = "Sub-prefetch content.";

		$this->contentReaderMock->expects($this->atLeastOnce())->method('init')
			->willReturnOnConsecutiveCalls(
				$this->contentReaderMock,
				$this->contentReaderMock
			);

		$this->contentReaderMock->expects($this->atLeastOnce())->method('loadPlaylistPrefetch')
			->willReturnOnConsecutiveCalls($prefetchContent, $subPrefetchContent);

		$result = $this->buildHelper->collectPrefetches($playlistId);

		$expected = "Prefetch content $subPrefetchContent.";
		static::assertSame($expected, $result);
	}

	#[Group('units')]
	public function testCollectPrefetchesOnException(): void
	{
		$this->externalContentReaderMock->expects($this->never())->method($this->anything());
		$playlistId = 1;

		$this->contentReaderMock->expects($this->atLeastOnce())->method('init')
			->willThrowException(new RuntimeException("Error during prefetch collection."));

		$this->loggerMock->expects($this->once())
			->method('error')
			->with(
				static::stringContains("Error recurse prefetches")
			);

		$result = $this->buildHelper->collectPrefetches($playlistId);

		static::assertSame('', $result);
	}

	#[Group('units')]
	public function testCollectExclusivesWithNoPlaceholders(): void
	{
		$this->externalContentReaderMock->expects($this->never())->method($this->anything());
		$this->loggerMock->expects($this->never())->method($this->anything());
		$playlistId = 1;
		$exclusiveContent = "Exclusive content without placeholders.";

		$this->contentReaderMock->method('init')
			->with($playlistId)
			->willReturn($this->contentReaderMock);

		$this->contentReaderMock->method('loadPlaylistExclusive')
			->willReturn($exclusiveContent);

		$result = $this->buildHelper->collectExclusives($playlistId);

		static::assertSame($exclusiveContent, $result);
	}

	#[Group('units')]
	public function testCollectExclusivesWithInternalPlaceholders(): void
	{
		$this->externalContentReaderMock->expects($this->never())->method($this->anything());
		$this->loggerMock->expects($this->never())->method($this->anything());
		$playlistId = 1;
		$exclusiveContent = "Exclusive content with {ITEMS_2}.";
		$subPlaylistContent = "Sub exclusive content.";

		$this->contentReaderMock->expects($this->exactly(3))
			->method('init')
			->willReturn($this->contentReaderMock);

		$this->contentReaderMock->method('loadPlaylistExclusive')
			->willReturnOnConsecutiveCalls(
				$exclusiveContent,
				''
			);

		$this->contentReaderMock->method('loadPlaylistItems')
			->willReturn($subPlaylistContent);

		$result = $this->buildHelper->collectExclusives($playlistId);

		$expected = "Exclusive content with $subPlaylistContent.";
		static::assertSame($expected, $result);
	}

	#[Group('units')]
	public function testCollectExclusivesWithRecursivePlaceholders(): void
	{
		$this->externalContentReaderMock->expects($this->never())->method($this->anything());
		$this->loggerMock->expects($this->never())->method($this->anything());
		$playlistId = 1;
		$exclusiveContent = "Exclusive content {ITEMS_2}.";
		$subPlaylistContent1 = "SubContent 2 {ITEMS_3}.";
		$subPlaylistContent2 = "Resolved content.";

		$this->contentReaderMock->expects($this->exactly(5))
			->method('init')
			->willReturn($this->contentReaderMock);

		$this->contentReaderMock->method('loadPlaylistExclusive')
			->willReturnOnConsecutiveCalls(
				$exclusiveContent,
				'',
				''
			);

		$this->contentReaderMock
			->method('loadPlaylistItems')
			->willReturnOnConsecutiveCalls($subPlaylistContent1, $subPlaylistContent2);

		$result = $this->buildHelper->collectExclusives($playlistId);

		$expected = "Exclusive content SubContent 2 Resolved content...";
		static::assertSame($expected, $result);
	}

	#[Group('units')]
	public function testCollectExclusivesOnException(): void
	{
		$this->externalContentReaderMock->expects($this->never())->method($this->anything());
		$playlistId = 1;

		$this->contentReaderMock
			->expects($this->atLeastOnce())->method('init')
			->willThrowException(new RuntimeException("Error during exclusive collection."));

		$this->loggerMock
			->expects($this->once())
			->method('error')
			->with(
				static::stringContains("Error recurse exclusive")
			);

		$result = $this->buildHelper->collectExclusives($playlistId);

		static::assertSame('', $result);
	}

}




