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

namespace Tests\Unit\Modules\Player\IndexCreation;

use App\Framework\Exceptions\ModuleException;
use App\Modules\Player\IndexCreation\IndexFile;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class IndexFileTest extends TestCase
{
	private FileSystem&MockObject $fileSystemMock;
	private LoggerInterface&MockObject $loggerMock;
	private IndexFile $indexFile;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->fileSystemMock = $this->createMock(Filesystem::class);
		$this->loggerMock     = $this->createMock(LoggerInterface::class);

		$this->indexFile = new IndexFile($this->fileSystemMock, $this->loggerMock);
	}

	/**
	 * @throws ModuleException
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testHandleIndexFileNewContent(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$this->indexFile->setIndexFilePath('/some/path');

		$this->fileSystemMock->expects($this->once())->method('fileExists')
			->with('/some/path')
			->willReturn(true);

		$this->fileSystemMock->expects($this->once())->method('read')
			->with('/some/path')
			->willReturn('old content');

		$this->fileSystemMock->expects($this->once())->method('write')
			->with('/some/path', 'new content');

		$this->indexFile->handleIndexFile('new content');
	}

	/**
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testHandleIndexFileWithEmptyOldContentThrowsException(): void
	{
		$this->indexFile->setIndexFilePath('/some/path');

		$this->fileSystemMock
			->expects($this->once())->method('fileExists')
			->with('/some/path')
			->willReturn(false);

		$this->loggerMock->expects($this->once())->method('warning')
			->with(static::stringContains('Index content generation failed'));

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Index content generation failed and no old index file present');

		$this->indexFile->handleIndexFile('');
	}

	/**
	 * @throws ModuleException
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testHandleIndexFileDoesNotWriteWhenNewContentMatchesOld(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$this->indexFile->setIndexFilePath('/some/path');

		$this->fileSystemMock
			->expects($this->once())
			->method('fileExists')
			->with('/some/path')
			->willReturn(true);

		$this->fileSystemMock
			->expects($this->once())
			->method('read')
			->with('/some/path')
			->willReturn('same content');

		$this->fileSystemMock
			->expects($this->never())
			->method('write');

		$this->indexFile->handleIndexFile('same content');
	}
}
