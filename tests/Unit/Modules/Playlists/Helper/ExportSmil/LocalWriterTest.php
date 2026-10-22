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
use App\Framework\Exceptions\CoreException;
use App\Modules\Playlists\Helper\ExportSmil\LocalWriter;
use App\Modules\Playlists\Helper\ExportSmil\PlaylistContent;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LocalWriterTest extends TestCase
{
	private Filesystem&MockObject $fileSystemMock;
	private LocalWriter $writer;

	/**
	 * @throws Exception
	 * @throws CoreException
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$configMock = $this->createMock(Config::class);
		$this->fileSystemMock = $this->createMock(Filesystem::class);
		$configMock->method('getConfigValue')
			->with('path_playlists', 'playlists')
			->willReturn('path/to/playlists');
		$this->writer = new LocalWriter($configMock, $this->fileSystemMock);
	}

	/**
	 * @throws FilesystemException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testWriteSMILFilesSuccessfully(): void
	{
		$playlistId = 123;
		$this->writer->initExport($playlistId);

		$playlistContentStub = $this->createStub(PlaylistContent::class);
		$playlistContentStub->method('getContentPrefetch')
			->willReturn('prefetch content');
		$playlistContentStub->method('getContentElements')
			->willReturn('elements content');
		$playlistContentStub->method('getContentExclusive')
			->willReturn('exclusive content');

		$playlistPath = 'path/to/playlists/' . $playlistId;
		$this->fileSystemMock->expects($this->once())
			->method('createDirectory')
			->with($playlistPath);

		$this->fileSystemMock->expects($this->exactly(3))
			->method('write')
			->willReturnMap([
				[$playlistPath . '/prefetch.smil', 'prefetch content'],
				[$playlistPath . '/items.smil', 'elements content'],
				[$playlistPath . '/exclusive.smil', 'exclusive content']
			]);

		$this->writer->writeSMILFiles($playlistContentStub);
	}

}
