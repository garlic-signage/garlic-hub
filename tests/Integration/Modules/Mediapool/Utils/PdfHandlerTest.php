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
use App\Modules\Mediapool\Utils\Pdf;
use Imagick;
use ImagickException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Support\MediaWorkspace;

/**
 * The pdf handler with a real pdf. The thumbnail of the first page needs Ghostscript, without it the test is skipped.
 */
#[Group('io')]
class PdfHandlerTest extends TestCase
{
	private MediaWorkspace $workspace;
	private Pdf $handler;

	protected function setUp(): void
	{
		parent::setUp();
		$this->workspace = new MediaWorkspace();
		$this->handler   = new Pdf($this->workspace->config, $this->workspace->filesystem, new Imagick());
		$this->handler->setStoragePaths('mediapool');
	}

	protected function tearDown(): void
	{
		$this->workspace->remove();
		parent::tearDown();
	}

	private function pdf(string $name): string
	{
		$imagick = new Imagick();
		$imagick->newImage(595, 842, 'white');
		$imagick->setImageFormat('pdf');

		return $this->workspace->store($name, $imagick->getImagesBlob());
	}

	public function testTheMaximumIs20Megabyte(): void
	{
		static::assertSame(20 * 1024 * 1024, $this->handler->getMaxFileSize());
	}

	public function testAFileOfTheMaximumSizeIsAllowedBeforeTheUploadAndOneByteMoreIsNot(): void
	{
		$this->handler->checkFileBeforeUpload(20 * 1024 * 1024);

		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('filesize_exceeded');

		$this->handler->checkFileBeforeUpload(20 * 1024 * 1024 + 1);
	}

	public function testTheSizeOfAPdfIsKnownAfterTheCheck(): void
	{
		$path = $this->pdf('doc.pdf');

		$this->handler->validateStoredFile($path);

		static::assertSame((int) filesize($this->workspace->absolute($path)), $this->handler->getFileSize());
	}

	public function testAPdfOverTheMaximumSizeIsRefused(): void
	{
		$path = $this->workspace->sparse('big.pdf', 20 * 1024 * 1024 + 1);

		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('filesize_exceeded');

		$this->handler->validateStoredFile($path);
	}

	public function testAMissingFileIsAnError(): void
	{
		$this->expectException(ModuleException::class);

		$this->handler->validateStoredFile($this->workspace->original('nothing.pdf'));
	}

	public function testTheThumbnailIsTheFirstPageAsJpegWhichFitsIntoThePreview(): void
	{
		$path = $this->pdf('doc.pdf');

		try
		{
			$this->handler->createThumbnail($path);
		}
		catch (ImagickException $e)
		{
			static::markTestSkipped('The pdf can not be read here (Ghostscript or the policy of ImageMagick): ' . $e->getMessage());
		}

		static::assertSame(['doc.jpg'], $this->workspace->listing('thumbs'));
		$size = getimagesize($this->workspace->dir . '/var/storage/mediapool/thumbs/doc.jpg');
		static::assertNotFalse($size);
		static::assertLessThanOrEqual(300, $size[0]);
		static::assertSame(200, $size[1]);
		static::assertSame(IMAGETYPE_JPEG, $size[2]);
	}
}
