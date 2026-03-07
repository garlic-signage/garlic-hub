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

namespace Tests\Unit\Modules\Mediapool\Utils;

use App\Framework\Core\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Mediapool\Utils\AbstractMediaHandler;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UploadedFileInterface;
use TypeError;

class ConcreteMediaHandler extends AbstractMediaHandler
{
	public function checkFileBeforeUpload(int $size): void {}
	public function checkFileAfterUpload(string $filePath): void {}
	public function createThumbnail(string $filePath): void {}
}
class AbstractMediaHandlerTest extends TestCase
{
	use PHPMock;

	private AbstractMediaHandler $concreteMediaHandler;
	private Filesystem&MockObject $filesystemMock;
	private Config&MockObject $configMock;

	/**
	 * @throws Exception
	 * @throws CoreException
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->configMock           = $this->createMock(Config::class);
		$this->filesystemMock = $this->createMock(Filesystem::class);

		$this->configMock->method('getConfigValue')
			->willReturnMap([
				['width', 'mediapool', 'max_resolution', '3840'],
				['height', 'mediapool', 'max_resolution', '3840'],
				['thumb_width', 'mediapool', 'dimensions', '150'],
				['thumb_height', 'mediapool', 'dimensions', '150'],
				['uploads', 'mediapool', 'directories', '/uploads'],
				['thumbnails', 'mediapool', 'directories', '/thumbnails'],
				['originals', 'mediapool', 'directories', '/originals'],
				['previews', 'mediapool', 'directories', '/previews'],
				['icons', 'mediapool', 'directories', '/icons']
			]);

		$this->concreteMediaHandler = new ConcreteMediaHandler($this->configMock, $this->filesystemMock);
		$this->concreteMediaHandler->setStoragePaths('mediapool');

	}

	#[Group('units')]
	public function testSomeGetters(): void
	{
		static::assertEmpty($this->concreteMediaHandler->getDimensions());
		static::assertEmpty($this->concreteMediaHandler->getFileSize());
		static::assertEmpty($this->concreteMediaHandler->getConfigData());
		static::assertEquals(0.0, $this->concreteMediaHandler->getDuration());}

	/**
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testExistsSucceed(): void
	{
		$this->filesystemMock->method('fileExists')->willReturn(true);
		static::assertTrue($this->concreteMediaHandler->exists('/path/to/file'));
	}

	/**
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testExistsFailed(): void
	{
		$this->filesystemMock->method('fileExists')->willReturn(false);
		static::assertFalse($this->concreteMediaHandler->exists('/path/to/file'));
	}

	/**
	 * @throws FilesystemException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testDetermineNewFilenameThrowsException(): void
	{
		$this->filesystemMock->method('fileExists')->willReturn(false);
		$this->expectException(ModuleException::class);
		$this->concreteMediaHandler->determineNewFilename('/path/to/file');
	}

	/**
	 * @throws FilesystemException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testDetermineNewFilenameThrowsException2(): void
	{
		$this->filesystemMock->method('fileExists')->willReturn(true);
		$this->filesystemMock->method('readStream')->willReturn(null);
		$this->expectException(TypeError::class);
		$this->concreteMediaHandler->determineNewFilename('/path/to/file');
	}

	/**
	 * @throws FilesystemException
	 * @throws ModuleException
	 */
	#[RunInSeparateProcess] #[Group('units')]
	public function testDetermineNewFilenameThrowsModuleException3(): void
	{
		$this->filesystemMock->method('fileExists')->willReturn(true);
		$this->filesystemMock->method('readStream')->willReturn(fopen('php://memory', 'r+'));

		$stream_get_contents = $this->getFunctionMock('App\Modules\Mediapool\Utils', 'stream_get_contents');
		$stream_get_contents->expects($this->once())->willReturn(false);

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Stream from /path/to/file not readable');
		$this->concreteMediaHandler->determineNewFilename('/path/to/file');
	}


	/**
	 * @throws FilesystemException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testDetermineNewFilenameSucceed(): void
	{
		$this->filesystemMock->method('fileExists')->willReturn(true);
		$this->filesystemMock->method('readStream')->willReturn(fopen('php://memory', 'r+'));
		fwrite($this->filesystemMock->readStream('/path/to/file'), 'file content');
		rewind($this->filesystemMock->readStream('/path/to/file'));

		$hash = $this->concreteMediaHandler->determineNewFilename('/path/to/file');
		static::assertEquals(hash('sha256', 'file content'), $hash);
	}

	#[Group('units')]
	public function testDetermineNewFilePathReturnsCorrectPathWithExtension(): void
	{
		$oldFilePath = '/path/to/file.txt';
		$filehash = '1234567890abcdef';
		$extension = 'txt';

		$newFilePath = $this->concreteMediaHandler->determineNewFilePath($oldFilePath, $filehash, $extension);

		static::assertEquals('/path/to/1234567890abcdef.txt', $newFilePath);
	}

	#[Group('units')]
	public function testDetermineNewFilePathReturnsCorrectPathWithoutExtension(): void
	{
		$oldFilePath = '/path/to/file';
		$filehash = '1234567890abcdef';
		$extension = 'txt';

		$newFilePath = $this->concreteMediaHandler->determineNewFilePath($oldFilePath, $filehash, $extension);

		static::assertEquals('/path/to/1234567890abcdef.txt', $newFilePath);
	}

	#[Group('units')]
	public function testDetermineNewFilePathNormalizesJpegExtension(): void
	{
		$oldFilePath = '/path/to/file.jpeg';
		$filehash = '1234567890abcdef';
		$extension = 'jpg';

		$newFilePath = $this->concreteMediaHandler->determineNewFilePath($oldFilePath, $filehash, $extension);

		static::assertEquals('/path/to/1234567890abcdef.jpg', $newFilePath);
	}

	#[Group('units')]
	public function testDetermineNewFilePath_UsesMimeTypeWhenNoExtension(): void
	{
		$oldFilePath = '/path/to/file';
		$filehash = '1234567890abcdef';
		$extension = 'jpg';

		$newFilePath = $this->concreteMediaHandler->determineNewFilePath($oldFilePath, $filehash, $extension);

		static::assertEquals('/path/to/1234567890abcdef.jpg', $newFilePath);
	}

	/**
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testRemoveUploadedFileDeletesFile(): void
	{
		$this->filesystemMock->expects($this->once())->method('delete')->with('/path/to/file');
		$this->concreteMediaHandler->removeUploadedFile('/path/to/file');
	}

	/**
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testRenameMovesFile(): void
	{
		$this->filesystemMock->expects($this->once())->method('move')->with('/old/path', '/new/path');
		$this->concreteMediaHandler->rename('/old/path', '/new/path');
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUploadFromLocalSuccessfullyUploadsFile(): void
	{
		$uploadedFileMock = $this->createMock(UploadedFileInterface::class);
		$uploadedFileMock->method('getClientFilename')->willReturn('testfile.jpg');
		$uploadedFileMock->expects($this->once())->method('moveTo');

		$result = $this->concreteMediaHandler->uploadFromLocal($uploadedFileMock);

		static::assertEquals('//originals/testfile.jpg', $result);
	}

	/**
	 * @throws GuzzleException|Exception
	 */
	#[Group('units')]
	public function testUploadFromExternalReturnsCorrectPath(): void
	{
		$clientMock = $this->createMock(Client::class);
		$this->configMock->expects($this->once())->method('getPaths')
			->with('systemDir')
			->willReturn('/absolute/path/to');

		$clientMock->expects($this->once())->method('request')
			->with('GET', 'https://example.com/file', ['sink' => '/absolute/path/to//originals/file']);

		$result = $this->concreteMediaHandler->uploadFromExternal($clientMock, 'https://example.com/file');
		static::assertEquals('//originals/file', $result);
	}
}
