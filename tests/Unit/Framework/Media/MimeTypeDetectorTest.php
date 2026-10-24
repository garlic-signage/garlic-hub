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
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * The logic of the detector around FileInfoWrapper. The detection itself (finfo) is tested
 * with real files in tests/Integration/Framework/Media.
 */
class MimeTypeDetectorTest extends TestCase
{
	private FileInfoWrapper&Stub $fileInfoWrapperStub;
	private MimeTypeDetector $mimeTypeDetector;

	protected function setUp(): void
	{
		parent::setUp();
		$this->fileInfoWrapperStub = $this->createStub(FileInfoWrapper::class);
		$this->mimeTypeDetector    = new MimeTypeDetector($this->fileInfoWrapperStub);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testDetectFromFileReturnsTheDetectedMimeType(): void
	{
		$this->fileInfoWrapperStub->method('fileExists')->willReturn(true);
		$this->fileInfoWrapperStub->method('detectMimeTypeFromFile')->willReturnMap([['some/testfile.txt', 'text/plain']]);

		static::assertSame('text/plain', $this->mimeTypeDetector->detectFromFile('some/testfile.txt'));
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testDetectFromFileThrowsExceptionForNonExistentFile(): void
	{
		$this->fileInfoWrapperStub->method('fileExists')->willReturn(false);
		$this->fileInfoWrapperStub->method('detectMimeTypeFromFile')->willReturn('text/plain');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage("File 'non_existent_file.txt' not exists.");

		$this->mimeTypeDetector->detectFromFile('non_existent_file.txt');
	}

	/**
	 * Widgets are zip archives which finfo can not tell from others: the extension decides.
	 *
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testDetectFromFileReturnsWidgetMimeTypeForWgtExtension(): void
	{
		$this->fileInfoWrapperStub->method('fileExists')->willReturn(true);
		$this->fileInfoWrapperStub->method('detectMimeTypeFromFile')->willReturn('application/zip');

		static::assertSame('application/widget', $this->mimeTypeDetector->detectFromFile('some/path/to/a/testfile.wgt'));
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testTheWidgetExceptionDependsOnTheCaseOfTheExtension(): void
	{
		$this->fileInfoWrapperStub->method('fileExists')->willReturn(true);
		$this->fileInfoWrapperStub->method('detectMimeTypeFromFile')->willReturn('application/zip');

		static::assertSame('application/zip', $this->mimeTypeDetector->detectFromFile('some/path/to/a/testfile.WGT'));
	}

	#[Group('units')]
	public function testDetectFromFileFails(): void
	{
		$this->fileInfoWrapperStub->method('fileExists')->willReturn(true);
		$this->fileInfoWrapperStub->method('detectMimeTypeFromFile')->willReturn(false);

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage("MIME-Type for 'some/undetectableMime.type' could not be detected.");

		$this->mimeTypeDetector->detectFromFile('some/undetectableMime.type');
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testDetectFromStreamReturnsTheMimeTypeOfTheContent(): void
	{
		$this->fileInfoWrapperStub->method('isStream')->willReturn(true);
		$this->fileInfoWrapperStub->method('getStreamContent')->willReturn('some stream content');
		$this->fileInfoWrapperStub->method('detectMimeTypeFromStreamContent')->willReturnMap([['some stream content', 'video/ogg']]);

		static::assertSame('video/ogg', $this->mimeTypeDetector->detectFromStream('some stream'));
	}

	#[Group('units')]
	public function testDetectFromStreamThrowsExceptionForInvalidStream(): void
	{
		$this->fileInfoWrapperStub->method('isStream')->willReturn(false);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid stream.');

		$this->mimeTypeDetector->detectFromStream('some not stream');
	}

	#[Group('units')]
	public function testDetectFromStreamThrowsModuleExceptionForUnreadableStream(): void
	{
		$this->fileInfoWrapperStub->method('isStream')->willReturn(true);
		$this->fileInfoWrapperStub->method('getStreamContent')->willReturn(false);

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Stream was not readable.');

		$this->mimeTypeDetector->detectFromStream('some stream');
	}

	#[Group('units')]
	public function testDetectFromStreamThrowsModuleExceptionForUnknownMimeType(): void
	{
		$this->fileInfoWrapperStub->method('isStream')->willReturn(true);
		$this->fileInfoWrapperStub->method('getStreamContent')->willReturn('some stream content');
		$this->fileInfoWrapperStub->method('detectMimeTypeFromStreamContent')->willReturn(false);

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('MIME-Type could not be detected from stream.');

		$this->mimeTypeDetector->detectFromStream('some stream');
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testDetectFromStringContentReturnsTheMimeType(): void
	{
		$this->fileInfoWrapperStub->method('detectMimeTypeFromStreamContent')->willReturnMap([['plain text', 'text/plain']]);

		static::assertSame('text/plain', $this->mimeTypeDetector->detectFromStringContent('plain text'));
	}

	#[Group('units')]
	public function testDetectFromStringContentThrowsModuleExceptionForUnknownMimeType(): void
	{
		$this->fileInfoWrapperStub->method('detectMimeTypeFromStreamContent')->willReturn(false);

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('MIME-Type could not be detected from string content.');

		$this->mimeTypeDetector->detectFromStringContent('???');
	}
}
