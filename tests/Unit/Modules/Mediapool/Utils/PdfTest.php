<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2025 Nikolaos Sagiadinos <garlic@saghiadinos.de>
 This file is part of the garlic-hub source code

 This program is free software: you can redistribute it and/or  modify
 it under the terms of the GNU Affero General Public License, version 3,
 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

namespace Tests\Unit\Modules\Mediapool\Utils;

use App\Framework\Core\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Mediapool\Utils\Pdf;
use Imagick;
use ImagickException;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PdfTest extends TestCase
{
	private Pdf $pdf;
	private Filesystem&MockObject $filesystemMock;
	private Imagick&MockObject $imagickMock;

	/**
	 * @throws Exception|CoreException
	 */
	protected function setUp(): void
	{
		$configMock = $this->createMock(Config::class);
		$this->filesystemMock = $this->createMock(Filesystem::class);
		$this->imagickMock = $this->createMock(Imagick::class);

		$configMock->method('getConfigValue')
			->willReturnMap([
				['width', 'mediapool', 'max_resolution', 3840],
				['height', 'mediapool', 'max_resolution', 3840],
				['thumb_width', 'mediapool', 'dimensions', 150],
				['thumb_height', 'mediapool', 'dimensions', 150],
				['uploads', 'mediapool', 'directories', '/uploads'],
				['thumbnails', 'mediapool', 'directories', '/thumbnails'],
				['originals', 'mediapool', 'directories', '/originals'],
				['previews', 'mediapool', 'directories', '/previews'],
				['icons', 'mediapool', 'directories', '/icons'],
				['documents', 'mediapool', 'max_file_sizes', 1073741824]
			]);

		$this->pdf = new Pdf($configMock, $this->filesystemMock, $this->imagickMock);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testCheckFileBeforeUploadValidSizeDoesNotThrowException()
	{
		$this->expectNotToPerformAssertions();
		$this->pdf->checkFileBeforeUpload(1073741824);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testCheckFileBeforeUploadExceedsMaxSizeThrowsModuleException()
	{
		$this->expectException(ModuleException::class);
		$this->pdf->checkFileBeforeUpload(1073741824 + 1);
	}

	/**
	 * @throws ModuleException
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testCheckFileAfterUploadFileExistsDoesNotThrowException()
	{
		$this->filesystemMock->method('fileExists')->willReturn(true);
		$this->filesystemMock->method('fileSize')->willReturn(1073741824);

		$this->expectNotToPerformAssertions();
		$this->pdf->checkFileAfterUpload('/path/to/file');
	}

	/**
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testCheckFileAfterUpload_FileNotExistsThrowsModuleException()
	{
		$this->expectException(ModuleException::class);
		$this->filesystemMock->method('fileExists')->willReturn(false);
		$this->pdf->checkFileAfterUpload('/path/to/file');
	}

	/**
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testCheckFileAfterUpload_ExceedsMaxSizeThrowsModuleException()
	{
		$this->expectException(ModuleException::class);
		$this->filesystemMock->method('fileExists')->willReturn(true);
		$this->filesystemMock->method('fileSize')->willReturn(1073741824 + 1);
		$this->pdf->checkFileAfterUpload('/path/to/file');
	}

	/**
	 * @throws ImagickException
	 */
	#[Group('units')]
	public function testCreateThumbnail()
	{
		$this->imagickMock->expects($this->once())->method('setResolution')->with(150, 150);
		$this->imagickMock->expects($this->once())->method('readImage')->with($this->stringContains('[0]'));
		$this->imagickMock->expects($this->once())->method('setImageAlphaChannel')->with(Imagick::ALPHACHANNEL_REMOVE);
		$this->imagickMock->expects($this->once())->method('setImageFormat')->with('jpg');
		$this->imagickMock->expects($this->once())->method('thumbnailImage')->with(150, 150, true);
		$this->imagickMock->expects($this->once())->method('writeImage');

		$this->pdf->createThumbnail('/path/to/file.pdf');
	}
}
