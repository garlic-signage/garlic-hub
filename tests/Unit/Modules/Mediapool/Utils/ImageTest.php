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
use App\Modules\Mediapool\Utils\Image;
use Imagick;
use ImagickException;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ImageTest extends TestCase
{
	private Filesystem&MockObject $filesystemMock;
	private Imagick&MockObject $imagickMock;
	private Image $image;

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
				['images', 'mediapool', 'max_file_sizes', '20971520']
			]);

		$this->image = new Image($configMock, $this->filesystemMock, $this->imagickMock);
		$this->image->setStoragePaths('mediapool');


	}

	#[Group('units')]
	public function testCheckFileBeforeUploadThrowsExceptionWhenFileSizeExceedsLimit(): void
	{
		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Filesize: 20 MB exceeds max image size.');

		$this->image->checkFileBeforeUpload(20 * 1024 * 1024 + 1);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testCheckFileBeforeUploadDoesNotThrowExceptionWhenFileSizeIsWithinLimit(): void
	{
		$this->image->checkFileBeforeUpload(20 * 1024 * 1024);
		// @phpstan-ignore-next-line
		static::assertTrue(true); // If no exception is thrown, the test passes
	}

	/**
	 * @throws ImagickException
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testCheckFileAfterUploadThrowsExceptionWhenFileDoesNotExist(): void
	{
		$this->filesystemMock->method('fileExists')->willReturn(false);

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('After Upload Check: /path/to/file not exists.');

		$this->image->validateStoredFile('/path/to/file');
	}

	/**
	 * @throws ImagickException
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testCheckFileAfterUploadThrowsExceptionWhenFileSizeExceedsLimit(): void
	{
		$this->filesystemMock->method('fileExists')->willReturn(true);
		$this->filesystemMock->method('fileSize')->willReturn(20 * 1024 * 1024 + 1);

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('After Upload Check: 20 MB exceeds max image size.');

		$this->image->validateStoredFile('/path/to/file');
	}

	/**
	 * @throws ImagickException
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testCheckFileAfterUploadThrowsExceptionWhenImageWidthExceedsLimit(): void
	{
		$this->filesystemMock->method('fileExists')->willReturn(true);
		$this->filesystemMock->method('fileSize')->willReturn(20 * 1024 * 1024);
		$this->imagickMock->method('getImageWidth')->willReturn(5001);

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('After Upload Check:  Image width 5001 exceeds maximum.');

		$this->image->validateStoredFile('/path/to/file');
	}

	/**
	 * @throws ImagickException
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testCheckFileAfterUploadThrowsExceptionWhenImageHeightExceedsLimit(): void
	{
		$this->filesystemMock->method('fileExists')->willReturn(true);
		$this->filesystemMock->method('fileSize')->willReturn(20 * 1024 * 1024);
		$this->imagickMock->method('getImageHeight')->willReturn(5001);

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('After Upload Check:  Image height 5001 exceeds maximum.');

		$this->image->validateStoredFile('/path/to/file');
	}

	/**
	 * @throws ModuleException
	 * @throws ImagickException
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testCheckFileAfterUploadDoesNotThrowExceptionWhenFileIsValid(): void
	{
		$this->filesystemMock->method('fileExists')->willReturn(true);
		$this->filesystemMock->method('fileSize')->willReturn(20 * 1024 * 1024);
		$this->imagickMock->method('getImageWidth')->willReturn(3840);
		$this->imagickMock->method('getImageHeight')->willReturn(3840);

		$this->image->validateStoredFile('/path/to/file');
		// @phpstan-ignore-next-line
		static::assertTrue(true); // If no exception is thrown, the test passes
	}

	/**
	 * @throws ImagickException
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testCreateThumbnailCreatesGifThumbnail(): void
	{
		$this->imagickMock->expects($this->once())->method('thumbnailImage');
		$this->imagickMock->expects($this->once())->method('writeImage');

		$this->image->createThumbnail('/path/to/file.gif');
	}

	/**
	 * @throws ImagickException
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testCreateThumbnailCreatesSvgThumbnail(): void
	{
		$this->filesystemMock->expects($this->once())->method('copy');

		$this->image->createThumbnail('/path/to/file.svg');
	}

	/**
	 * @throws ImagickException
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testCreateThumbnailCreatesStandardThumbnail(): void
	{
		$this->imagickMock->expects($this->once())->method('thumbnailImage');
		$this->imagickMock->expects($this->once())->method('writeImage');

		$this->image->createThumbnail('/path/to/file.jpg');
	}
}
