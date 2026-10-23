<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Unit\Framework\Database\Migration;

use App\Framework\Database\Migration\Repository;
use App\Framework\Database\Migration\Runner;
use App\Framework\Exceptions\DatabaseException;
use ArrayIterator;
use Doctrine\DBAL\Exception;
use League\Flysystem\DirectoryListing;
use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RunnerTest extends TestCase
{
	private Runner $runner;
	private Repository&MockObject $repositoryMock;
	private Filesystem&MockObject $filesystemMock;

	/**
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->repositoryMock = $this->createMock(Repository::class);
		$this->filesystemMock = $this->createMock(Filesystem::class);

		$this->runner = new Runner($this->repositoryMock, $this->filesystemMock);
	}

	/**
	 * @throws DatabaseException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 * @throws FilesystemException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testExecuteWithMigrations(): void
	{
		$this->repositoryMock->method('showTables')
			->willReturn(['_migration_version']);
		$this->repositoryMock->method('getAppliedMigrations')
			->willReturn([['version' => 1]]);

		$fileMock1 = $this->createStub(FileAttributes::class);
		$fileMock1->method('isFile')->willReturn(true);
		$fileMock1->method('path')->willReturn('001_init.up.sql');
		$fileMock2 = $this->createStub(FileAttributes::class);
		$fileMock2->method('isFile')->willReturn(true);
		$fileMock2->method('path')->willReturn('002_name.up.sql');

		$mockDirectoryListing = $this->createStub(DirectoryListing::class);
		$mockDirectoryListing->method('getIterator')
			->willReturn(new ArrayIterator([$fileMock1, $fileMock2]));

		$this->filesystemMock->expects($this->once())
			->method('listContents')
			->willReturn($mockDirectoryListing);

		$this->filesystemMock->expects($this->once())->method('read')
			->willReturn('SQL QUERY');

		$this->repositoryMock->expects($this->once())
			->method('applySqlBatch')
			->with('SQL QUERY');

		$this->runner->execute();

		static::assertTrue($this->runner->isApplied());
	}

	/**
	 * @throws FilesystemException
	 * @throws DatabaseException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testExecuteWithOutMigrationsTable(): void
	{
		$this->repositoryMock->expects($this->once())->method('showTables')->willReturn([]);
		$this->repositoryMock->expects($this->once())->method('createMigrationTable');
		$this->repositoryMock->expects($this->once())->method('getAppliedMigrations')->willReturn([]);
		$this->filesystemMock->expects($this->once())->method('listContents')->with('', false)
			->willReturn(new DirectoryListing([]));
		$this->filesystemMock->expects($this->never())->method('read');

		$this->runner->execute();

		static::assertFalse($this->runner->isApplied());
	}

	/**
	 * @throws DatabaseException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 * @throws FilesystemException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testExecuteWithoutMigrations(): void
	{
		$this->repositoryMock->expects($this->atLeastOnce())->method('showTables')
			->willReturn(['_migration_version']);
		$this->repositoryMock->expects($this->atLeastOnce())->method('getAppliedMigrations')
			->willReturn([]);

		// Mock für DirectoryListing erstellen
		$mockDirectoryListing = $this->createStub(DirectoryListing::class);
		$mockDirectoryListing->method('toArray')
			->willReturn([]);

		$this->filesystemMock->expects($this->atLeastOnce())->method('listContents')
			->willReturn($mockDirectoryListing);

		$this->runner->execute();

		static::assertFalse($this->runner->isApplied());
	}

	/**
	 * @throws DatabaseException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 * @throws FilesystemException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testRollbackWithMigrations(): void
	{
		$this->repositoryMock->method('showTables')
			->willReturn(['_migration_version']);
		$this->repositoryMock->method('getAppliedMigrations')
			->willReturn([['version' => 1], ['version' => 2]]);

		$fileMock1 = $this->createStub(FileAttributes::class);
		$fileMock1->method('isFile')->willReturn(true);
		$fileMock1->method('path')->willReturn('001_init.down.sql');
		$fileMock2 = $this->createStub(FileAttributes::class);
		$fileMock2->method('isFile')->willReturn(true);
		$fileMock2->method('path')->willReturn('002_name.down.sql');

		$mockDirectoryListing = $this->createStub(DirectoryListing::class);
		$mockDirectoryListing->method('getIterator')
			->willReturn(new ArrayIterator([$fileMock1, $fileMock2]));

		$this->filesystemMock->expects($this->atLeastOnce())->method('listContents')
			->willReturn($mockDirectoryListing);

		$this->filesystemMock->expects($this->atLeastOnce())->method('read')
			->willReturn('SQL ROLLBACK QUERY');

		$this->repositoryMock->expects($this->exactly(2))
			->method('applySqlBatch')
			->with('SQL ROLLBACK QUERY');

		$this->runner->rollback();

		static::assertTrue($this->runner->isApplied());
	}

	/**
	 * @throws FilesystemException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testRollbackWithOutMigrationsTable(): void
	{
		$this->filesystemMock->expects($this->never())->method($this->anything());
		$this->repositoryMock->expects($this->atLeastOnce())->method('showTables')
			->willReturn([]);

		$this->expectException(DatabaseException::class);
		$this->expectExceptionMessage('Migration table not found');

		$this->runner->rollback();
	}

}