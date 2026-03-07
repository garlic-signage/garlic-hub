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
use App\Modules\Mediapool\Utils\Miscellaneous;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MiscellaneousTest extends TestCase
{
	private Filesystem&MockObject $filesystemMock;
	private Miscellaneous $misc;

	/**
	 * @throws Exception
	 * @throws CoreException
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$configMock = $this->createMock(Config::class);
		$this->filesystemMock = $this->createMock(Filesystem::class);

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

		$this->misc = new Miscellaneous($configMock, $this->filesystemMock);
		$this->misc->setStoragePaths('mediapool');
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testCheckFileBeforeUpload_ValidSize_DoesNotThrowException(): void
	{
		$this->expectNotToPerformAssertions();
		$this->misc->checkFileBeforeUpload(1073741824);
	}

	#[Group('units')]
	public function testCheckFileBeforeUploadExceedsMaxSizeThrowsModuleException(): void
	{
		$this->expectException(ModuleException::class);

		$this->misc->checkFileBeforeUpload(1073741824 + 1);
	}

	/**
	 * @throws ModuleException
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testCheckFileAfterUploadFileExistsDoesNotThrowException(): void
	{
		$this->filesystemMock->method('fileExists')->willReturn(true);
		$this->filesystemMock->method('fileSize')->willReturn(1073741824);

		$this->expectNotToPerformAssertions();
		$this->misc->validateStoredFile('/path/to/file');
	}

	/**
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testCheckFileAfterUploadFileNotExistsThrowsModuleException(): void
	{
		$this->expectException(ModuleException::class);

		$this->filesystemMock->method('fileExists')->willReturn(false);
		$this->misc->validateStoredFile('/path/to/file');
	}

	/**
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testCheckFileAfterUpload_ExceedsMaxSizeThrowsModuleException(): void
	{
		$this->expectException(ModuleException::class);

		$this->filesystemMock->method('fileExists')->willReturn(true);
		$this->filesystemMock->method('fileSize')->willReturn(1073741824 + 1);
		$this->misc->validateStoredFile('/path/to/file');
	}

	/**
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testCreateThumbnailCsvFileCreatesDatabaseIcon(): void
	{
		$this->filesystemMock->method('copy');

		$this->misc->createThumbnail('/path/to/file.csv');
		static::assertEquals('svg', $this->misc->getThumbExtension());
	}

	/**
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testCreateThumbnail(): void
	{
		$this->filesystemMock->method('copy');

		$this->misc->createThumbnail('/path/to/file.txt');
		static::assertEquals('svg', $this->misc->getThumbExtension());
	}}
