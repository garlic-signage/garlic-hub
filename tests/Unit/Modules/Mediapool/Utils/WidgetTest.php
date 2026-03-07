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
use App\Framework\Utils\Widget\ConfigXML;
use App\Modules\Mediapool\Utils\Widget;
use App\Modules\Mediapool\Utils\ZipFilesystemFactory;
use Imagick;
use ImagickException;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class WidgetTest extends TestCase
{
	private Filesystem&MockObject $filesystemMock;
	private ZipFilesystemFactory&MockObject $zipFilesystemFactoryMock;
	private Imagick&MockObject $imagickMock;
	private ConfigXML&MockObject $configXmlMock;
	private Widget $widget;

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
		$this->zipFilesystemFactoryMock = $this->createMock(ZipFilesystemFactory::class);
		$this->configXmlMock = $this->createMock(ConfigXML::class);

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
				['downloads', 'mediapool', 'max_file_sizes', '1073741824']
			]);

		$this->widget = new Widget(
			$configMock,
			$this->filesystemMock,
			$this->zipFilesystemFactoryMock,
			$this->imagickMock,
			$this->configXmlMock
		);
		$this->widget->setStoragePaths('mediapool');
	}

	#[Group('units')]
	public function testCheckFileBeforeUploadThrowsExceptionWhenFileSizeExceedsLimit(): void
	{
		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Filesize: 1024 MB exceeds max widget size.');

		$this->widget->checkFileBeforeUpload(1073741824 + 1);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testCheckFileBeforeUploadDoesNotThrowExceptionWhenFileSizeIsWithinLimit(): void
	{
		$this->widget->checkFileBeforeUpload(1073741824);
		// @phpstan-ignore-next-line
		static::assertTrue(true); // If no exception is thrown, the test passes
	}

	/**
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testCheckFileAfterUploadThrowsExceptionWhenFileDoesNotExist(): void
	{
		$this->filesystemMock->method('fileExists')->willReturn(false);

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('After Upload Check: /path/to/file not exists.');

		$this->widget->checkFileAfterUpload('/path/to/file');
	}

	/**
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testCheckFileAfterUploadThrowsExceptionWhenFileSizeExceedsLimit(): void
	{
		$this->filesystemMock->method('fileExists')->willReturn(true);
		$this->filesystemMock->method('fileSize')->willReturn(1073741824 + 1);

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('After Upload Check: 1024 MB exceeds max widget size.');

		$this->widget->checkFileAfterUpload('/path/to/file');
	}

	/**
	 * @throws ModuleException
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testCheckFileAfterUploadThrowsExceptionWhenFileSizeNotExceedsLimit(): void
	{
		$this->filesystemMock->method('fileExists')->willReturn(true);
		$this->filesystemMock->method('fileSize')->willReturn(1073741824);

		$this->widget->checkFileAfterUpload('/path/to/file');
		// @phpstan-ignore-next-line
		static::assertTrue(true); // If no exception is thrown, the test passes
	}

	/**
	 * @throws Exception
	 * @throws FilesystemException
	 * @throws ModuleException
	 * @throws FrameworkException
	 * @throws ImagickException
	 */
	#[Group('units')]
	public function testCreateThumbnailFromIcon(): void
	{
		$zipFilesystemMock = $this->createMock(Filesystem::class);
		$zipFilesystemMock->method('fileExists')->willReturnMap([
			['config.xml', true],
			['icon.png', true]
		]);
		$zipFilesystemMock->method('read')->willReturnMap([
			['config.xml', '<config><icon>icon.png</icon></config>'],
			['icon.png', 'image content']
		]);

		$this->zipFilesystemFactoryMock->method('create')->willReturn($zipFilesystemMock);
		$this->configXmlMock->method('load')->willReturnSelf();
		$this->configXmlMock->method('parseBasic')->willReturnSelf();
		$this->configXmlMock->method('getIcon')->willReturn('icon.png');

		$this->imagickMock->expects($this->once())->method('readImageBlob')->with('image content');
		$this->imagickMock->expects($this->once())->method('thumbnailImage');
		$this->imagickMock->expects($this->once())->method('writeImage');

		$this->widget->createThumbnail('/path/to/file.wgt');
	}

	/**
	 * @throws Exception
	 * @throws FilesystemException
	 * @throws ModuleException
	 * @throws FrameworkException
	 * @throws ImagickException
	 */
	#[Group('units')]
	public function testCreateThumbnailWhenIconNotFound(): void
	{
		$zipFilesystemMock = $this->createMock(Filesystem::class);

		$zipFilesystemMock->method('fileExists')->willReturnMap([
			['config.xml', true],
			['icon.png', false]
		]);
		$zipFilesystemMock->method('read')->willReturnMap([
			['config.xml', '<config><icon>icon.png</icon></config>']
		]);

		$this->zipFilesystemFactoryMock->method('create')->willReturn($zipFilesystemMock);
		$this->configXmlMock->method('load')->willReturnSelf();
		$this->configXmlMock->method('parseBasic')->willReturnSelf();
		$this->configXmlMock->method('getIcon')->willReturn('icon.png');

		$this->filesystemMock->expects($this->once())->method('copy')->with('//icons/widget.svg', '//thumbnails/file.svg');

		$this->widget->createThumbnail('/path/to/file.wgt');
	}


}
