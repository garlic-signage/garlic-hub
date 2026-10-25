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

namespace Tests\Integration\Modules\Mediapool\Utils;

use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Modules\Mediapool\Utils\Miscellaneous;
use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\UploadedFile;
use Tests\Integration\Support\MediaWorkspace;

/**
 * What all media handlers share: the place of an upload, the name in the storage, the download from outside.
 * The handlers work on a real storage in a temporary directory. The downloads come from a small web server of PHP.
 */
#[Group('io')]
class AbstractMediaHandlerTest extends TestCase
{
	private static string $serverDir = '';
	private static int $port = 0;
	/** @var resource|null */
	private static $server = null;

	private MediaWorkspace $workspace;
	private Miscellaneous $handler;

	public static function setUpBeforeClass(): void
	{
		self::$serverDir = sys_get_temp_dir() . '/garlic-hub-download-' . bin2hex(random_bytes(4));
		mkdir(self::$serverDir);
		file_put_contents(self::$serverDir . '/router.php', <<<'PHP'
<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/small.csv')
{
	header('Content-Type: text/csv');
	echo "a;b\n1;2\n";
}
elseif ($path === '/big.bin')
{
	header('Content-Type: application/octet-stream');
	echo str_repeat("\0", 1024 * 1024);
}
else
	http_response_code(404);
PHP);
		$probe = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
		self::assertIsResource($probe);
		self::$port = (int) parse_url((string) stream_socket_get_name($probe, false), PHP_URL_PORT);
		fclose($probe);
		$process = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . self::$port, '-t', self::$serverDir, self::$serverDir . '/router.php'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
		self::assertIsResource($process);
		self::$server = $process;
		for ($i = 0; $i < 50; $i++)
		{
			if (@fsockopen('127.0.0.1', self::$port))
				return;
			usleep(100_000);
		}
		self::fail('The test server did not start.');
	}

	public static function tearDownAfterClass(): void
	{
		if (is_resource(self::$server))
		{
			proc_terminate(self::$server);
			proc_close(self::$server);
		}
		@unlink(self::$serverDir . '/router.php');
		@rmdir(self::$serverDir);
	}

	protected function setUp(): void
	{
		parent::setUp();
		$this->workspace = new MediaWorkspace();
		$this->handler   = new Miscellaneous($this->workspace->config, $this->workspace->filesystem);
		$this->handler->setStoragePaths('mediapool');
	}

	protected function tearDown(): void
	{
		$this->workspace->remove();
		parent::tearDown();
	}

	private function upload(string $name, string $content = 'data'): UploadedFile
	{
		$path = $this->workspace->dir . '/incoming/' . bin2hex(random_bytes(3));
		file_put_contents($path, $content);

		return new UploadedFile($path, $name, 'text/csv', strlen($content));
	}

	private function url(string $path): string
	{
		return 'http://127.0.0.1:' . self::$port . $path;
	}

	public function testTheStoragePathsComeFromTheModule(): void
	{
		static::assertSame('var/storage/mediapool/originals', $this->handler->getOriginalPath());
		$this->handler->setStoragePaths('templates');
		static::assertNotSame('var/storage/mediapool/originals', $this->handler->getOriginalPath());
	}

	public function testMetadataAndAbsolutePath(): void
	{
		$this->handler->setMetadata(['description' => 'Strand', 'duration' => 3]);

		static::assertSame(['description' => 'Strand', 'duration' => 3], $this->handler->getMetadata());
		static::assertSame($this->workspace->dir . '/var/x', $this->handler->getAbsolutePath('/var/x'));
	}

	public function testExists(): void
	{
		$path = $this->workspace->store('a.csv', 'x');

		static::assertTrue($this->handler->exists($path));
		static::assertFalse($this->handler->exists($this->workspace->original('b.csv')));
	}

	public function testTheNameInTheStorageIsTheHashOfTheContent(): void
	{
		$path = $this->workspace->store('anything.csv', "a;b\n1;2\n");

		static::assertSame(hash('sha256', "a;b\n1;2\n"), $this->handler->determineNewFilename($path));
		static::assertSame(hash('sha256', ''), $this->handler->determineNewFilename($this->workspace->store('empty.csv', '')));
	}

	public function testTheHashOfAMissingFileIsAnError(): void
	{
		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('not exists');

		$this->handler->determineNewFilename($this->workspace->original('nothing.csv'));
	}

	public function testTheHashOfABigFileIsTheSameAsOfTheWholeFile(): void
	{
		$content = random_bytes(5 * 1024 * 1024 + 123);
		$path    = $this->workspace->store('big.bin', $content);

		static::assertSame(hash('sha256', $content), $this->handler->determineNewFilename($path));
	}

	/**
	 * The place in the storage does not depend on where the upload was.
	 */
	public function testTheNewPathIsInTheStorageOfTheOriginals(): void
	{
		static::assertSame('/var/storage/mediapool/originals/abc.png', $this->handler->determineNewFilePath('/var/storage/mediapool/uploads/123/name.PNG', 'abc', 'png'));
		static::assertSame('/var/storage/mediapool/originals/abc.png', $this->handler->determineNewFilePath('/somewhere/else.jpg', 'abc', 'png'));
	}

	public function testABinaryStringIsWritten(): void
	{
		$path = $this->workspace->original('data.bin');

		$this->handler->writeBinaryString($path, "\x00\x01\x02");

		static::assertSame("\x00\x01\x02", file_get_contents($this->workspace->absolute($path)));
	}

	public function testABinaryStringReplacesTheFile(): void
	{
		$path = $this->workspace->store('data.bin', 'old content which is longer');

		$this->handler->writeBinaryString($path, 'new');

		static::assertSame('new', file_get_contents($this->workspace->absolute($path)));
	}

	public function testAnEmptyBinaryStringIsAnErrorAndKeepsTheFile(): void
	{
		$path = $this->workspace->store('data.bin', 'old');

		try
		{
			$this->handler->writeBinaryString($path, '');
			static::fail('Expected a ModuleException');
		}
		catch (ModuleException $e)
		{
			static::assertStringContainsString('empty', $e->getMessage());
		}

		static::assertSame('old', file_get_contents($this->workspace->absolute($path)));
	}

	public function testAnUploadedFileLiesInADirectoryOfItsOwn(): void
	{
		$path = $this->handler->uploadFromLocal($this->upload('Bericht.CSV', 'a;b'));

		static::assertMatchesRegularExpression('#^/var/storage/mediapool/uploads/[0-9a-f]{16}/bericht.csv$#', $path);
		static::assertSame('a;b', file_get_contents($this->workspace->absolute($path)));
		static::assertSame([], $this->workspace->listing('originals'));
	}

	public function testTwoUploadsWithTheSameNameDoNotMeet(): void
	{
		$first  = $this->handler->uploadFromLocal($this->upload('same.csv', 'first'));
		$second = $this->handler->uploadFromLocal($this->upload('same.csv', 'second'));

		static::assertNotSame($first, $second);
		static::assertSame('first', file_get_contents($this->workspace->absolute($first)));
		static::assertSame('second', file_get_contents($this->workspace->absolute($second)));
	}

	/**
	 * @return array<string,array{string,string}>
	 */
	public static function namesFromTheOutside(): array
	{
		return [
			'parent directories' => ['../../Evil.csv', 'evil.csv'],
			'backslashes'        => ['..\\..\\Evil.csv', 'evil.csv'],
			'absolute path'      => ['/etc/Evil.csv', 'evil.csv'],
			'control characters' => ["Ev\x00il\n.csv", 'evil.csv'],
			'a path of dots'     => ['a/b/c.csv', 'c.csv'],
		];
	}

	#[DataProvider('namesFromTheOutside')]
	public function testTheNameOfTheClientIsReducedToAFileName(string $name, string $expected): void
	{
		$path = $this->handler->uploadFromLocal($this->upload($name));

		static::assertSame($expected, basename($path));
		static::assertSame(1, preg_match('#^/var/storage/mediapool/uploads/[0-9a-f]{16}/[^/]+$#', $path));
	}

	/**
	 * @return array<string,array{?string}>
	 */
	public static function namesWithoutAName(): array
	{
		return ['empty' => [''], 'dot' => ['.'], 'dots' => ['..'], 'parents only' => ['a/..'], 'none' => [null]];
	}

	#[DataProvider('namesWithoutAName')]
	public function testAnUploadWithoutAUsableNameIsRefusedAndLeavesNothing(?string $name): void
	{
		$file = new UploadedFile($this->workspace->dir . '/incoming/x', $name, 'text/csv', 1);

		try
		{
			$this->handler->uploadFromLocal($file);
			static::fail('Expected a ModuleException');
		}
		catch (ModuleException $e)
		{
			static::assertStringContainsString('Invalid file name', $e->getMessage());
		}

		static::assertSame([], $this->workspace->listing('uploads'));
	}

	public function testAnUploadWhichCanNotBeMovedLeavesNoDirectoryBehind(): void
	{
		$file = new UploadedFile($this->workspace->dir . '/incoming/does-not-exist', 'a.csv', 'text/csv', 1);

		try
		{
			$this->handler->uploadFromLocal($file);
			static::fail('Expected an exception');
		}
		catch (\Throwable)
		{
			static::addToAssertionCount(1);
		}

		static::assertSame([], $this->workspace->listing('uploads'));
	}

	public function testRemovingAnUploadRemovesItsDirectoryToo(): void
	{
		$path  = $this->handler->uploadFromLocal($this->upload('a.csv'));
		$other = $this->handler->uploadFromLocal($this->upload('b.csv'));

		$this->handler->removeUploadedFile($path);

		static::assertFalse($this->handler->exists($path));
		static::assertCount(1, $this->workspace->listing('uploads'), 'the upload of another one stays');
		static::assertTrue($this->handler->exists($other));
	}

	public function testRemovingAFileOfTheStorageKeepsTheStorage(): void
	{
		$keep = $this->workspace->store('keep.csv', 'x');
		$path = $this->workspace->store('gone.csv', 'x');

		$this->handler->removeUploadedFile($path);

		static::assertSame(['keep.csv'], $this->workspace->listing('originals'));
		static::assertTrue($this->handler->exists($keep));
	}

	public function testRemovingAFileWhichIsNotThereIsNoError(): void
	{
		$this->handler->removeUploadedFile($this->workspace->original('nothing.csv'));

		static::addToAssertionCount(1);
	}

	public function testAnUploadIsMovedIntoTheStorage(): void
	{
		$upload = $this->handler->uploadFromLocal($this->upload('a.csv', 'a;b'));
		$target = $this->handler->determineNewFilePath($upload, 'abc', 'csv');

		$this->handler->rename($upload, $target);

		static::assertSame('a;b', file_get_contents($this->workspace->absolute($target)));
		static::assertFalse($this->handler->exists($upload));
	}

	public function testAFileFromOutsideLiesInADirectoryOfItsOwnWithTheNameOfTheAddress(): void
	{
		$path = $this->handler->uploadFromExternal(new Client(), $this->url('/small.csv?token=1'), []);

		static::assertMatchesRegularExpression('#^/var/storage/mediapool/uploads/[0-9a-f]{16}/small.csv$#', $path);
		static::assertSame("a;b\n1;2\n", file_get_contents($this->workspace->absolute($path)));
		static::assertSame([], $this->workspace->listing('originals'));
	}

	public function testAnAddressWithoutAFileNameGetsAName(): void
	{
		$path = $this->handler->uploadFromExternal(new Client(['http_errors' => false]), 'http://127.0.0.1:' . self::$port, []);

		static::assertSame('external', basename($path));
	}

	public function testABrokenAddressLeavesNothingBehind(): void
	{
		try
		{
			$this->handler->uploadFromExternal(new Client(), $this->url('/not-there.csv'), []);
			static::fail('Expected an exception');
		}
		catch (\GuzzleHttp\Exception\TransferException)
		{
			static::addToAssertionCount(1);
		}

		static::assertSame([], $this->workspace->listing('uploads'));
	}

	/**
	 * The check before the download is a request of its own, which the server can answer differently than the
	 * download. The download itself stops at the maximum of the handler.
	 */
	public function testADownloadOverTheMaximumIsStopped(): void
	{
		// a handler with a maximum of 100 KB: the file of the server has 1 MB
		$handler = new class($this->workspace->config, $this->workspace->filesystem) extends Miscellaneous {
			public function getMaxFileSize(): int
			{
				return 100_000;
			}
		};
		$handler->setStoragePaths('mediapool');

		try
		{
			$handler->uploadFromExternal(new Client(), $this->url('/big.bin'), []);
			static::fail('Expected a TranslatableModuleException');
		}
		catch (TranslatableModuleException $e)
		{
			static::assertSame('filesize_exceeded', $e->getTranslationKey());
		}

		static::assertSame([], $this->workspace->listing('uploads'), 'nothing of the download stays');
	}

	public function testADownloadUnderTheMaximumIsComplete(): void
	{
		$path = $this->handler->uploadFromExternal(new Client(), $this->url('/big.bin'), []);

		static::assertSame(1024 * 1024, filesize($this->workspace->absolute($path)));
	}
}
