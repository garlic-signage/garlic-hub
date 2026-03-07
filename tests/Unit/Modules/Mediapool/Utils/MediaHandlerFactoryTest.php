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
use App\Framework\Media\Ffmpeg;
use App\Modules\Mediapool\Utils\Image;
use App\Modules\Mediapool\Utils\ImagickFactory;
use App\Modules\Mediapool\Utils\MediaHandlerFactory;
use App\Modules\Mediapool\Utils\Miscellaneous;
use App\Modules\Mediapool\Utils\Pdf;
use App\Modules\Mediapool\Utils\Video;
use App\Modules\Mediapool\Utils\Widget;
use App\Modules\Mediapool\Utils\ZipFilesystemFactory;
use Imagick;
use League\Flysystem\Filesystem;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MediaHandlerFactoryTest extends TestCase
{
	private Config&MockObject $configMock;
	private ImagickFactory&MockObject $imagickFactoryMock;
	private MediaHandlerFactory $mediaHandlerFactory;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->configMock         = $this->createMock(Config::class);
		$filesystemMock           = $this->createMock(Filesystem::class);
		$zipFilesystemFactoryMock = $this->createMock(ZipFilesystemFactory::class);
		$this->imagickFactoryMock = $this->createMock(ImagickFactory::class);
		$ffmpegMock = $this->createMock(Ffmpeg::class);

		$this->mediaHandlerFactory = new MediaHandlerFactory(
			$this->configMock,
			$filesystemMock,
			$zipFilesystemFactoryMock,
			$this->imagickFactoryMock,
			$ffmpegMock
		);
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testCreateHandlerReturnsImageForImageMimeType(): void
	{
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
				['icons', 'mediapool', 'directories', '/icons'],
				['images', 'mediapool', 'max_file_sizes', '20971520']
			]);

		$this->imagickFactoryMock->method('createImagick')->willReturn(new Imagick());
		$handler = $this->mediaHandlerFactory->createMediaHandler('image/jpeg');
		static::assertInstanceOf(Image::class, $handler);
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testCreateHandlerReturnsVideoForVideoMimeType(): void
	{
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
				['icons', 'mediapool', 'directories', '/icons'],
				['videos', 'mediapool', 'max_file_sizes', '1073741824']
			]);

		$this->imagickFactoryMock->method('createImagick')->willReturn(new Imagick());
		$handler = $this->mediaHandlerFactory->createMediaHandler('video/mp4');
		static::assertInstanceOf(Video::class, $handler);
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testCreateHandlerReturnsPdfForPdfMimeType(): void
	{
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
				['icons', 'mediapool', 'directories', '/icons'],
				['documents', 'mediapool', 'max_file_sizes', '20971520']
			]);

		$this->imagickFactoryMock->method('createImagick')->willReturn(new Imagick());
		$handler = $this->mediaHandlerFactory->createMediaHandler('application/pdf');
		static::assertInstanceOf(Pdf::class, $handler);
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testCreateHandlerReturnsWidgetForWidgetMimeType(): void
	{
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
				['icons', 'mediapool', 'directories', '/icons'],
				['downloads', 'mediapool', 'max_file_sizes', '1073741824']
			]);

		$this->imagickFactoryMock->method('createImagick')->willReturn(new Imagick());
		$handler = $this->mediaHandlerFactory->createMediaHandler('application/widget');
		static::assertInstanceOf(Widget::class, $handler);
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testCreateHandlerReturnsMiscellaneousForMiscellaneousMimeType(): void
	{
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
				['icons', 'mediapool', 'directories', '/icons'],
				['downloads', 'mediapool', 'max_file_sizes', '1073741824']
			]);

		$handler = $this->mediaHandlerFactory->createMediaHandler('application/zip');
		static::assertInstanceOf(Miscellaneous::class, $handler);
	}

	#[Group('units')]
	public function testCreateHandlerThrowsExceptionForUnsupportedMimeType(): void
	{
		$this->expectException(CoreException::class);
		$this->mediaHandlerFactory->createMediaHandler('unsupported/mime');
	}
}
