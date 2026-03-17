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

namespace Tests\Unit\Framework\Media;

use App\Framework\Exceptions\ModuleException;
use App\Framework\Media\FileInfoWrapper;
use App\Framework\Media\MimeTypeDetector;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MimeTypeDetectorTest extends TestCase
{
	private FileInfoWrapper&MockObject $fileInfoWrapperMock;

	private MimeTypeDetector $mimeTypeDetector;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->fileInfoWrapperMock = $this->createMock(FileInfoWrapper::class);

		$this->mimeTypeDetector = new MimeTypeDetector($this->fileInfoWrapperMock);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testDetectFromFileReturnsCorrectMimeType(): void
	{
		$filePath = 'some/testfile.txt';
		$this->fileInfoWrapperMock->method('fileExists')
			->with($filePath)
			->willReturn(true);

		$this->fileInfoWrapperMock->expects($this->once())->method('detectMimeTypeFromFile')
			->with($filePath)
			->willReturn('mime/type');
		$mimeType = $this->mimeTypeDetector->detectFromFile($filePath);
		static::assertEquals('mime/type', $mimeType);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testDetectFromFileThrowsExceptionForNonExistentFile(): void
	{
		$this->fileInfoWrapperMock->method('fileExists')->willReturn(false);
		$this->fileInfoWrapperMock->expects($this->never())->method('detectMimeTypeFromFile');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("File 'non_existent_file.txt' not exists.");

		$this->mimeTypeDetector->detectFromFile('non_existent_file.txt');
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testDetectFromFileReturnsWidgetMimeTypeForWgtExtension(): void
	{
		$filePath = 'some/path/to/a/testfile.wgt';
		$this->fileInfoWrapperMock->method('fileExists')->willReturn(true);
		$this->fileInfoWrapperMock->expects($this->never())->method('detectMimeTypeFromFile');

		$mimeType = $this->mimeTypeDetector->detectFromFile($filePath);
		static::assertEquals('application/widget', $mimeType);
		unlink($filePath);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testDetectFromFileFails(): void
	{
		$filePath = 'some/undetectableMime.type';
		$this->fileInfoWrapperMock->method('fileExists')->willReturn(true);
		$this->fileInfoWrapperMock->expects($this->once())->method('detectMimeTypeFromFile')
			->with($filePath)
			->willReturn(false);

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage("MIME-Type for '$filePath' could not be detected.");
		$this->mimeTypeDetector->detectFromFile($filePath);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testDetectFromStreamReturnsCorrectMimeType(): void
	{
		$stream        = 'some stream';
		$streamContent = 'some stream content';
		$mimetype      = 'video/ogg';

		$this->fileInfoWrapperMock->method('isStream')
			->with($stream)
			->willReturn(true);
		$this->fileInfoWrapperMock->method('getStreamContent')
			->with($stream)
			->willReturn($streamContent);
		$this->fileInfoWrapperMock->method('detectMimeTypeFromStreamContent')
			->with($streamContent)
			->willReturn($mimetype);

		static::assertEquals($mimetype, $this->mimeTypeDetector->detectFromStream($stream));
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testDetectFromStreamThrowsExceptionForInvalidStream(): void
	{
		$stream        = 'some not stream';
		$this->fileInfoWrapperMock->method('isStream')
			->with($stream)
			->willReturn(false);
		$this->fileInfoWrapperMock->expects($this->never())->method('getStreamContent');
		$this->fileInfoWrapperMock->expects($this->never())->method('detectMimeTypeFromStreamContent');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid stream.');

		$this->mimeTypeDetector->detectFromStream($stream);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testDetectFromStreamThrowsModuleExceptionForUnreadableStream(): void
	{
		$stream        = 'some stream';
		$this->fileInfoWrapperMock->method('isStream')
			->with($stream)
			->willReturn(true);
		$this->fileInfoWrapperMock->method('getStreamContent')
			->with($stream)
			->willReturn(false);
		$this->fileInfoWrapperMock->expects($this->never())->method('detectMimeTypeFromStreamContent');

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Stream was not readable.');

		$this->mimeTypeDetector->detectFromStream($stream);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testDetectFromStreamThrowsModuleExceptionForUnknownMimeType(): void
	{
		$stream        = 'some stream';
		$streamContent = 'some stream content';

		$this->fileInfoWrapperMock->method('isStream')
			->with($stream)
			->willReturn(true);
		$this->fileInfoWrapperMock->method('getStreamContent')
			->with($stream)
			->willReturn($streamContent);
		$this->fileInfoWrapperMock->method('detectMimeTypeFromStreamContent')
			->with($streamContent)
			->willReturn(false);

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('MIME-Type could not be detected from stream.');

		$this->mimeTypeDetector->detectFromStream($stream);
	}
}