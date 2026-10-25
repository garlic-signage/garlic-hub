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

namespace Tests\Integration\Modules\Playlists\Collector;

use App\Modules\Playlists\Collector\Builder\BuildHelper;
use App\Modules\Playlists\Collector\ContentReader;
use App\Modules\Playlists\Collector\ExternalContentReader;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Support\MediaWorkspace;
use Tests\Support\RecordingLogger;

/**
 * A playlist contains other playlists by placeholders ({ITEMS_2}, {PREFETCH_2}) in the exported SMIL files.
 * The BuildHelper reads the files of the playlists and puts the content of the nested playlists in.
 */
#[Group('io')]
class BuildHelperTest extends TestCase
{
	private MediaWorkspace $workspace;
	private RecordingLogger $logger;
	private MockHandler $http;
	private BuildHelper $buildHelper;
	private string $previousDirectory;

	protected function setUp(): void
	{
		parent::setUp();
		$this->workspace = new MediaWorkspace();
		$this->logger    = new RecordingLogger();
		$this->http      = new MockHandler();
		// the cache of the external playlists is written to a path relative to the working directory
		$this->previousDirectory = (string) getcwd();
		chdir($this->workspace->dir);

		$this->buildHelper = new BuildHelper(
			new ContentReader($this->workspace->config, $this->workspace->filesystem),
			new ExternalContentReader($this->workspace->filesystem, new Client(['handler' => HandlerStack::create($this->http)]), 'var/smil/playlists'),
			$this->logger
		);
	}

	protected function tearDown(): void
	{
		chdir($this->previousDirectory);
		$this->workspace->remove();
		parent::tearDown();
	}

	public function testItemsWithoutPlaceholdersStayAsTheyAre(): void
	{
		$this->write(1, 'items', 'Static content.');

		static::assertSame('Static content.', $this->buildHelper->collectItems(1));
	}

	public function testThePlaylistsAreNestedOnEveryLevel(): void
	{
		$this->write(1, 'items', 'A {ITEMS_2} B');
		$this->write(2, 'items', 'C {ITEMS_3} D');
		$this->write(3, 'items', 'E');

		static::assertSame("A \nC \nE\n D\n B", $this->buildHelper->collectItems(1));
	}

	public function testAnEmptyNestedPlaylistLeavesNothing(): void
	{
		$this->write(1, 'items', 'A {ITEMS_2} B');
		$this->write(2, 'items', '');

		static::assertSame('A  B', $this->buildHelper->collectItems(1));
	}

	public function testTheSamePlaylistCanBeNestedTwice(): void
	{
		$this->write(1, 'items', '{ITEMS_2}|{ITEMS_2}');
		$this->write(2, 'items', 'X');

		static::assertSame("\nX\n|\nX\n", $this->buildHelper->collectItems(1));
	}

	public function testAnExternalPlaylistIsDownloadedAndNested(): void
	{
		$this->write(1, 'items', 'A {ITEMS_0#https://example.com/list.smil} B');
		$this->http->append(new Response(200), new Response(200, [], 'external'));

		static::assertSame('A external B', $this->buildHelper->collectItems(1));
	}

	public function testAPlaylistWhichCanNotBeReadGivesNothingAndIsLogged(): void
	{
		static::assertSame('', $this->buildHelper->collectItems(9));

		static::assertCount(1, $this->logger->messages['error']);
		static::assertStringStartsWith('Error recurse items: ', $this->logger->messages['error'][0]);
	}

	/**
	 * A nested playlist which is missing leaves its place empty, the rest stays.
	 */
	public function testANestedPlaylistWhichCanNotBeReadLeavesItsPlaceEmpty(): void
	{
		$this->write(1, 'items', 'A {ITEMS_2} B');

		static::assertSame('A  B', $this->buildHelper->collectItems(1));
		static::assertCount(1, $this->logger->messages['error']);
	}

	public function testPrefetchesAreNestedOnEveryLevel(): void
	{
		$this->write(1, 'prefetch', 'P1 {PREFETCH_2}');
		$this->write(2, 'prefetch', 'P2 {PREFETCH_3}');
		$this->write(3, 'prefetch', 'P3');

		static::assertSame('P1 P2 P3', $this->buildHelper->collectPrefetches(1));
	}

	public function testPrefetchesWithoutPlaceholdersStayAsTheyAre(): void
	{
		$this->write(1, 'prefetch', 'Static prefetch.');

		static::assertSame('Static prefetch.', $this->buildHelper->collectPrefetches(1));
	}

	public function testPrefetchesOfAPlaylistWhichCanNotBeReadGiveNothingAndAreLogged(): void
	{
		static::assertSame('', $this->buildHelper->collectPrefetches(9));

		static::assertCount(1, $this->logger->messages['error']);
		static::assertStringStartsWith('Error recurse prefetches: ', $this->logger->messages['error'][0]);
	}

	public function testExclusivesWithoutPlaceholdersStayAsTheyAre(): void
	{
		$this->write(1, 'exclusive', 'Static exclusive.');

		static::assertSame('Static exclusive.', $this->buildHelper->collectExclusives(1));
	}

	/**
	 * The exclusive content of the nested playlist is added behind, the items of it stand in place.
	 */
	public function testExclusivesGetTheItemsAndTheExclusivesOfTheNestedPlaylists(): void
	{
		$this->write(1, 'exclusive', 'X1 {ITEMS_2}.');
		$this->write(2, 'items', 'I2');
		$this->write(2, 'exclusive', ' X2 {ITEMS_3}');
		$this->write(3, 'items', 'I3');
		$this->write(3, 'exclusive', ' X3');

		static::assertSame('X1 I2. X2 I3 X3', $this->buildHelper->collectExclusives(1));
	}

	public function testTwoPlaceholdersInOneLineOfTheExclusivesAreBothReplaced(): void
	{
		$this->write(1, 'exclusive', 'X1 {ITEMS_2} and {ITEMS_3}.');
		$this->write(2, 'items', 'I2');
		$this->write(2, 'exclusive', '');
		$this->write(3, 'items', 'I3');
		$this->write(3, 'exclusive', '');

		static::assertSame('X1 I2 and I3.', $this->buildHelper->collectExclusives(1));
	}

	public function testExclusivesOfAPlaylistWhichCanNotBeReadGiveNothingAndAreLogged(): void
	{
		static::assertSame('', $this->buildHelper->collectExclusives(9));

		static::assertStringStartsWith('Error recurse exclusive: ', $this->logger->messages['error'][0]);
	}

	private function write(int $playlistId, string $file, string $content): void
	{
		$this->workspace->filesystem->write('var/smil/playlists/' . $playlistId . '/' . $file . '.smil', $content);
	}
}
