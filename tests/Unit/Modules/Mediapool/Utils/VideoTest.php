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
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Media\Ffmpeg;
use App\Modules\Mediapool\Utils\Video;
use Imagick;
use ImagickException;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class VideoTest extends TestCase
{
	private Video $video;
	private Filesystem&MockObject $filesystemMock;
	private Imagick&MockObject $imagickMock;
	private Ffmpeg&MockObject $ffmpegMock;

	/**
	 * @throws Exception
	 * @throws CoreException
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$configMock = $this->createMock(Config::class);
		$this->filesystemMock = $this->createMock(Filesystem::class);
		$this->imagickMock = $this->createMock(Imagick::class);
		$this->ffmpegMock = $this->createMock(Ffmpeg::class);

		$configMock->method('getConfigValue')
			->willReturnMap([
				['width', 'mediapool', 'max_resolution', '3840'],
				['height', 'mediapool', 'max_resolution', '3840'],
				['thumb_width', 'mediapool', 'dimensions', '150'],
				['thumb_height', 'mediapool', 'dimensions', '150'],
				['uploads', 'mediapool', 'directories', '/uploads'],
				['thumbnails', 'mediapool', 'directories', '/thumbnails'],
				['originals', 'mediapool', 'directories', '/originals'],
				['previews', 'mediapool', 'directories', '/previews'],
				['icons', 'mediapool', 'directories', '/icons'],
				['videos', 'mediapool', 'max_file_sizes', '1073741824']
			]);

		$this->video = new Video($configMock, $this->filesystemMock, $this->ffmpegMock, $this->imagickMock);
		$this->video->setStoragePaths('mediapool');
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testCheckFileBeforeUploadValidSizeDoesNotThrowException(): void
	{
		$this->expectNotToPerformAssertions();
		$this->video->checkFileBeforeUpload(1073741824);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testCheckFileBeforeUploadExceedsMaxSizeThrowsModuleException(): void
	{
		$this->expectException(ModuleException::class);
		$this->video->checkFileBeforeUpload(1073741824 + 1);
	}

	/**
	 * @throws ModuleException
	 * @throws FilesystemException
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCheckFileAfterUploadFileExistsDoesNotThrowException(): void
	{
		$this->filesystemMock->method('fileExists')->willReturn(true);
		$this->filesystemMock->method('fileSize')->willReturn(1073741824);
		$this->ffmpegMock->method('init')->with('/path/to/file');
		$properties = ['width' => 3840, 'height' => 3840];
		$this->ffmpegMock->method('getMediaProperties')->willReturn($properties);

		$this->video->validateStoredFile('/path/to/file');
	}

	/**
	 * @throws CoreException
	 * @throws FilesystemException
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testCheckFileAfterUploadFileNotExistsThrowsModuleException(): void
	{
		$this->expectException(ModuleException::class);
		$this->filesystemMock->method('fileExists')->willReturn(false);
		$this->video->validateStoredFile('/path/to/file');
	}

	/**
	 * @throws CoreException
	 * @throws FilesystemException
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testCheckFileAfterUploadExceedsMaxSizeThrowsModuleException(): void
	{
		$this->expectException(ModuleException::class);
		$this->filesystemMock->method('fileExists')->willReturn(true);
		$this->filesystemMock->method('fileSize')->willReturn(1073741824 + 1);
		$this->video->validateStoredFile('/path/to/file');
	}

	/**
	 * @throws CoreException
	 * @throws FilesystemException
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testCheckFileAfterUploadExceedsMaxWidthThrowsModuleException(): void
	{
		$this->expectException(ModuleException::class);
		$this->filesystemMock->method('fileExists')->willReturn(true);
		$this->filesystemMock->method('fileSize')->willReturn(1073741824);
		$this->ffmpegMock->method('init')->with('/path/to/file');
		$properties = ['width' => 3840 + 1, 'height' => 3840];
		$this->ffmpegMock->method('getMediaProperties')->willReturn($properties);

		$this->video->validateStoredFile('/path/to/file');
	}

	/**
	 * @throws CoreException
	 * @throws FilesystemException
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testCheckFileAfterUploadExceedsMaxHeightThrowsModuleException(): void
	{
		$this->expectException(ModuleException::class);
		$this->filesystemMock->method('fileExists')->willReturn(true);
		$this->filesystemMock->method('fileSize')->willReturn(1073741824);
		$this->ffmpegMock->method('init')->with('/path/to/file');
		$properties = ['width' => 3840, 'height' => 3840 + 1];
		$this->ffmpegMock->method('getMediaProperties')->willReturn($properties);

		$this->video->validateStoredFile('/path/to/file');
	}

	/**
	 * @throws ImagickException
	 * @throws FilesystemException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCreateThumbnail(): void
	{
		$this->filesystemMock->method('move');
		$this->imagickMock->expects($this->once())->method('readImage');
		$this->imagickMock->expects($this->once())->method('thumbnailImage')->with(150, 150, true);
		$this->imagickMock->expects($this->once())->method('writeImage');
		$this->ffmpegMock->expects($this->once())->method('createVideoThumbnail');

		$this->video->createThumbnail('/path/to/file.mp4');
	}
}
