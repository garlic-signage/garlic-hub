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
use App\Modules\Playlists\Collector\Builder\MultizonePlaylistBuilder;
use App\Modules\Playlists\Collector\Builder\PlaylistBuilderFactory;
use App\Modules\Playlists\Collector\Builder\StandardPlaylistBuilder;
use App\Modules\Playlists\Collector\ContentReader;
use App\Modules\Playlists\Collector\Contracts\PlaylistTargetInterface;
use App\Modules\Playlists\Collector\ExternalContentReader;
use App\Modules\Playlists\Collector\SimplePlaylistStructureFactory;
use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Tests\Integration\Support\MediaWorkspace;

/**
 * What a player gets of a playlist: the exported files of the playlist (and of the nested ones), a standard playlist
 * as one sequence, a multizone playlist as one sequence for every screen.
 */
#[Group('io')]
class PlaylistBuildersTest extends TestCase
{
	private MediaWorkspace $workspace;
	private PlaylistBuilderFactory $factory;

	protected function setUp(): void
	{
		parent::setUp();
		$this->workspace = new MediaWorkspace();
		$buildHelper     = new BuildHelper(
			new ContentReader($this->workspace->config, $this->workspace->filesystem),
			new ExternalContentReader($this->workspace->filesystem, new Client(), 'var/smil/playlists'),
			new NullLogger()
		);
		$this->factory = new PlaylistBuilderFactory($buildHelper, new SimplePlaylistStructureFactory());
	}

	protected function tearDown(): void
	{
		$this->workspace->remove();
		parent::tearDown();
	}

	public function testAStandardPlaylistIsOneSequenceWithTheNestedPlaylists(): void
	{
		$this->write(1, 'items', '<img region="screen" src="a"/>{ITEMS_2}');
		$this->write(1, 'prefetch', 'prefetch 1 {PREFETCH_2}');
		$this->write(1, 'exclusive', 'exclusive 1');
		$this->write(2, 'items', '<img region="screen" src="b"/>');
		$this->write(2, 'prefetch', 'prefetch 2');

		$structure = $this->factory->createBuilder($this->target(1, 'master'))->buildPlaylist();

		static::assertSame(
			"\t\t\t\t\t\t\t<seq repeatCount=\"indefinite\">\n<img region=\"screen\" src=\"a\"/>\n<img region=\"screen\" src=\"b\"/>\n\t\t\t\t\t\t\t</seq>\n",
			$structure->getItems()
		);
		static::assertSame('prefetch 1 prefetch 2', $structure->getPrefetch());
		static::assertSame('exclusive 1', $structure->getExclusive());
	}

	public function testAnEmptyPlaylistIsAnEmptySequence(): void
	{
		$this->write(1, 'items', '');
		$this->write(1, 'prefetch', '');
		$this->write(1, 'exclusive', '');

		$structure = $this->factory->createBuilder($this->target(1, 'internal'))->buildPlaylist();

		static::assertSame("\t\t\t\t\t\t\t<seq repeatCount=\"indefinite\">\n\t\t\t\t\t\t\t</seq>\n", $structure->getItems());
		static::assertSame('', $structure->getPrefetch());
		static::assertSame('', $structure->getExclusive());
	}

	public function testTheFactoryChoosesTheBuilderByTheMode(): void
	{
		static::assertInstanceOf(MultizonePlaylistBuilder::class, $this->factory->createBuilder($this->target(1, 'multizone')));
		foreach (['master', 'internal', 'external', 'channel'] as $mode)
			static::assertInstanceOf(StandardPlaylistBuilder::class, $this->factory->createBuilder($this->target(1, $mode)), $mode);
	}

	public function testAMultizonePlaylistHasASequenceAndTheRegionForEveryScreen(): void
	{
		$this->write(11, 'items', '<img region="screen" src="a"/>');
		$this->write(11, 'prefetch', 'prefetch 11');
		$this->write(11, 'exclusive', '<priorityClass region="screen"/>');
		$this->write(12, 'items', '<video region="screen" src="b"/>');
		$this->write(12, 'prefetch', 'prefetch 12');
		$this->write(12, 'exclusive', '');
		$layout = ['resolution' => '1920x1080', 'zones' => [1 => ['zone_playlist_id' => 11], 2 => ['zone_playlist_id' => 12]]];

		$structure = $this->factory->createBuilder($this->target(5, 'multizone', $layout))->buildPlaylist();

		static::assertSame(
			"\t\t\t\t\t\t\t<seq id=\"media1\" repeatCount=\"indefinite\">\n<img region=\"screen1\" src=\"a\"/>\t\t\t\t\t\t\t</seq>\n" .
			"\t\t\t\t\t\t\t<seq id=\"media2\" repeatCount=\"indefinite\">\n<video region=\"screen2\" src=\"b\"/>\t\t\t\t\t\t\t</seq>\n",
			$structure->getItems()
		);
		static::assertSame("prefetch 11\nprefetch 12\n", $structure->getPrefetch());
		static::assertSame('<priorityClass region="screen1"/>', $structure->getExclusive());
	}

	/**
	 * @param array<string,mixed> $layout
	 */
	private function target(int $playlistId, string $mode, array $layout = []): PlaylistTargetInterface
	{
		$target = $this->createStub(PlaylistTargetInterface::class);
		$target->method('getPlaylistId')->willReturn($playlistId);
		$target->method('getPlaylistMode')->willReturn($mode);
		$target->method('getLayout')->willReturn($layout);

		return $target;
	}

	private function write(int $playlistId, string $file, string $content): void
	{
		$this->workspace->filesystem->write('var/smil/playlists/' . $playlistId . '/' . $file . '.smil', $content);
	}
}
