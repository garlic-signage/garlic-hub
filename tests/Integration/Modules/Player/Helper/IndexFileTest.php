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

namespace Tests\Integration\Modules\Player\Helper;

use App\Framework\Exceptions\ModuleException;
use App\Modules\Player\IndexCreation\IndexFile;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The index file of a player in a real storage.
 */
#[Group('io')]
class IndexFileTest extends TestCase
{
	private const string PATH = 'var/smil/3f0cd56c-d511-486a-a8e1-9d2cefd78b3f/7.smil';

	private string $directory;
	private TestHandler $logs;
	private IndexFile $indexFile;

	protected function setUp(): void
	{
		parent::setUp();
		$this->directory = sys_get_temp_dir() . '/garlic-hub-indexfile-' . bin2hex(random_bytes(4));
		mkdir($this->directory);
		$this->logs      = new TestHandler();
		$this->indexFile = (new IndexFile(new Filesystem(new LocalFilesystemAdapter($this->directory)), new Logger('test', [$this->logs])))->setIndexFilePath(self::PATH);
	}

	protected function tearDown(): void
	{
		$this->removeDirectory($this->directory);
		parent::tearDown();
	}

	private function removeDirectory(string $dir): void
	{
		if (!is_dir($dir))
			return;

		foreach (array_diff((array) scandir($dir), ['.', '..']) as $entry)
			is_dir($dir . '/' . $entry) ? $this->removeDirectory($dir . '/' . $entry) : unlink($dir . '/' . $entry);
		rmdir($dir);
	}

	private function path(): string
	{
		return $this->directory . '/' . self::PATH;
	}

	/**
	 * @return list<string> the files in the directory of the index
	 */
	private function files(): array
	{
		return array_values(array_filter((array) scandir(dirname($this->path())), static fn(mixed $name): bool => is_string($name) && !in_array($name, ['.', '..'], true)));
	}

	public function testANewIndexIsWrittenAndTheDirectoryIsMade(): void
	{
		$this->indexFile->handleIndexFile('<smil>one</smil>');

		static::assertSame('<smil>one</smil>', file_get_contents($this->path()));
		static::assertSame(['7.smil'], $this->files(), 'No temporary file stays.');
	}

	public function testAChangedIndexReplacesTheOldOne(): void
	{
		$this->indexFile->handleIndexFile('<smil>one</smil>');

		$this->indexFile->handleIndexFile('<smil>two</smil>');

		static::assertSame('<smil>two</smil>', file_get_contents($this->path()));
		static::assertSame(['7.smil'], $this->files());
	}

	/**
	 * A player asks with the time of the file: an index that did not change keeps its time.
	 */
	public function testAnIndexWhichDidNotChangeIsNotWrittenAgain(): void
	{
		$this->indexFile->handleIndexFile('<smil>one</smil>');
		touch($this->path(), 1_750_000_000);
		clearstatcache();

		$this->indexFile->handleIndexFile('<smil>one</smil>');
		clearstatcache();

		static::assertSame(1_750_000_000, filemtime($this->path()));
		static::assertSame([], $this->logs->getRecords());
	}

	public function testAnEmptyIndexKeepsTheOldOneAndSaysSo(): void
	{
		$this->indexFile->handleIndexFile('<smil>one</smil>');

		$this->indexFile->handleIndexFile('');

		static::assertSame('<smil>one</smil>', file_get_contents($this->path()));
		static::assertTrue($this->logs->hasWarningThatContains('the old index is played instead'));
		static::assertTrue($this->logs->hasWarningThatContains(self::PATH));
	}

	public function testAnEmptyIndexWithoutAnOldOneIsAnError(): void
	{
		try
		{
			$this->indexFile->handleIndexFile('');
			static::fail('Expected a ModuleException');
		}
		catch (ModuleException $e)
		{
			static::assertStringContainsString('no old index', $e->getMessage());
		}

		static::assertTrue($this->logs->hasWarningThatContains('generation failed'));
		static::assertFalse($this->logs->hasWarningThatContains('played instead'));
		static::assertFileDoesNotExist($this->path());
	}

	public function testAnIndexWhichConsistsOfAZeroIsAContentToo(): void
	{
		$this->indexFile->handleIndexFile('0');

		static::assertSame('0', file_get_contents($this->path()));
	}

	public function testTheOldFileIsNeverHalfWrittenWhenTheWriteFails(): void
	{
		$this->indexFile->handleIndexFile('<smil>one</smil>');
		// a directory in the way of the new file: the move fails
		$broken = (new IndexFile(new Filesystem(new LocalFilesystemAdapter($this->directory)), new Logger('test', [$this->logs])))->setIndexFilePath(self::PATH . '/inside');
		mkdir($this->path() . '.d');

		try
		{
			$broken->handleIndexFile('<smil>two</smil>');
		}
		catch (\Throwable)
		{
			static::addToAssertionCount(1);
		}

		static::assertSame('<smil>one</smil>', file_get_contents($this->path()));
	}

	/**
	 * The new content is written beside the index first: when it can not be put in place, nothing of it stays.
	 */
	public function testATemporaryFileIsRemovedWhenTheMoveFails(): void
	{
		$filesystem = new class(new LocalFilesystemAdapter($this->directory)) extends Filesystem {
			/**
			 * @param array<string,mixed> $config
			 */
			public function move(string $source, string $destination, array $config = []): void
			{
				throw new \RuntimeException('the move failed');
			}
		};
		$indexFile = (new IndexFile($filesystem, new Logger('test', [$this->logs])))->setIndexFilePath(self::PATH);

		try
		{
			$indexFile->handleIndexFile('<smil>two</smil>');
			static::fail('Expected a RuntimeException');
		}
		catch (\RuntimeException $e)
		{
			static::assertSame('the move failed', $e->getMessage());
		}

		static::assertSame([], $this->files());
	}
}
