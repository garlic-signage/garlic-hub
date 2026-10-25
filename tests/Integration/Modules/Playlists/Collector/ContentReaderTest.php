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

use App\Modules\Playlists\Collector\ContentReader;
use League\Flysystem\FilesystemException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Support\MediaWorkspace;

/**
 * The SMIL files of an exported playlist are read from the directory var/smil/playlists/<id>.
 */
#[Group('io')]
class ContentReaderTest extends TestCase
{
	private MediaWorkspace $workspace;
	private ContentReader $reader;

	protected function setUp(): void
	{
		parent::setUp();
		$this->workspace = new MediaWorkspace();
		$this->reader    = new ContentReader($this->workspace->config, $this->workspace->filesystem);
	}

	protected function tearDown(): void
	{
		$this->workspace->remove();
		parent::tearDown();
	}

	public function testTheThreeFilesOfThePlaylistAreRead(): void
	{
		$this->workspace->filesystem->write('var/smil/playlists/123/items.smil', '<items>');
		$this->workspace->filesystem->write('var/smil/playlists/123/prefetch.smil', '<prefetch>');
		$this->workspace->filesystem->write('var/smil/playlists/123/exclusive.smil', '<exclusive>');
		$this->workspace->filesystem->write('var/smil/playlists/124/items.smil', '<other>');

		$reader = $this->reader->init(123);

		static::assertSame('<items>', $reader->loadPlaylistItems());
		static::assertSame('<prefetch>', $reader->loadPlaylistPrefetch());
		static::assertSame('<exclusive>', $reader->loadPlaylistExclusive());
	}

	public function testTheReaderSwitchesToTheNextPlaylist(): void
	{
		$this->workspace->filesystem->write('var/smil/playlists/1/items.smil', 'one');
		$this->workspace->filesystem->write('var/smil/playlists/2/items.smil', 'two');

		static::assertSame('one', $this->reader->init(1)->loadPlaylistItems());
		static::assertSame('two', $this->reader->init(2)->loadPlaylistItems());
	}

	public function testWithoutAPlaylistNothingIsRead(): void
	{
		$reader = $this->reader->init(0);

		static::assertSame('', $reader->loadPlaylistItems());
		static::assertSame('', $reader->loadPlaylistPrefetch());
		static::assertSame('', $reader->loadPlaylistExclusive());
	}

	public function testAPlaylistWhichIsNotExportedYetCanNotBeRead(): void
	{
		$this->expectException(FilesystemException::class);
		$this->reader->init(5)->loadPlaylistItems();
	}
}
