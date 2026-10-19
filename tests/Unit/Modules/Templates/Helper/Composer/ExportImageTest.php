<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Unit\Modules\Templates\Helper\Composer;

use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Media\DataUrlDecoder;
use App\Framework\Media\DecodedDataUrlFile;
use App\Modules\Mediapool\Utils\AbstractMediaHandler;
use App\Modules\Mediapool\Utils\MediaHandlerFactory;
use App\Modules\Templates\Helper\Composer\ExportImage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ExportImageTest extends TestCase
{
	private DataUrlDecoder&MockObject $dataUrlDecoderMock;
	private MediaHandlerFactory&MockObject $mediaHandlerFactoryMock;
	private ExportImage $exportImage;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->dataUrlDecoderMock      = $this->createMock(DataUrlDecoder::class);
		$this->mediaHandlerFactoryMock = $this->createMock(MediaHandlerFactory::class);

		$this->exportImage = new ExportImage($this->dataUrlDecoderMock, $this->mediaHandlerFactoryMock);
	}

	/**
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testDecode(): void
	{
		$this->decode();
		$this->mediaHandlerFactoryMock->expects($this->never())->method($this->anything());

		static::assertSame('image/png', $this->exportImage->getMimeType());
	}

	/**
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testExportBase64(): void
	{
		$this->decode();
		$mediaHandlerMock = $this->createMock(AbstractMediaHandler::class);
		$this->mediaHandlerFactoryMock->expects($this->once())->method('createTemplatesHandler')
			->with('image/png')
			->willReturn($mediaHandlerMock);
		$this->mediaHandlerFactoryMock->expects($this->never())->method('createPlaylistsHandler');
		$this->expectStoring($mediaHandlerMock, 'templates/originals', '/templates/originals/12.png', '/abs/templates/originals/12.png');

		static::assertTrue($this->exportImage->exportBase64(12));
		static::assertSame('', $this->exportImage->getErrorText());
	}

	/**
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testExportBase64Fails(): void
	{
		$this->decode();
		$mediaHandlerMock = $this->createMock(AbstractMediaHandler::class);
		$this->mediaHandlerFactoryMock->expects($this->once())->method('createTemplatesHandler')->willReturn($mediaHandlerMock);
		$mediaHandlerMock->method('getOriginalPath')->willReturn('templates/originals');
		$mediaHandlerMock->expects($this->once())->method('writeBinaryString')
			->willThrowException(new RuntimeException('disk full'));
		$mediaHandlerMock->expects($this->never())->method('createThumbnail');

		static::assertFalse($this->exportImage->exportBase64(12));
		static::assertSame('disk full', $this->exportImage->getErrorText());
	}

	#[Group('units')]
	public function testExportBase64WithoutDecodeFails(): void
	{
		$this->dataUrlDecoderMock->expects($this->never())->method('decode');
		$this->mediaHandlerFactoryMock->expects($this->never())->method('createTemplatesHandler');

		static::assertFalse($this->exportImage->exportBase64(12));
		static::assertNotSame('', $this->exportImage->getErrorText());
	}

	/**
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testExportPlaylistItem(): void
	{
		$this->decode();
		$mediaHandlerMock = $this->createMock(AbstractMediaHandler::class);
		$this->mediaHandlerFactoryMock->expects($this->once())->method('createPlaylistsHandler')
			->with('image/png')
			->willReturn($mediaHandlerMock);
		$this->mediaHandlerFactoryMock->expects($this->never())->method('createTemplatesHandler');
		$this->expectStoring($mediaHandlerMock, 'playlists/originals', '/playlists/originals/34.png', '/abs/playlists/originals/34.png');

		static::assertTrue($this->exportImage->exportPlaylistItem(34));
	}

	/**
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testExportPlaylistItemFails(): void
	{
		$this->decode();
		$mediaHandlerMock = $this->createMock(AbstractMediaHandler::class);
		$this->mediaHandlerFactoryMock->expects($this->once())->method('createPlaylistsHandler')->willReturn($mediaHandlerMock);
		$mediaHandlerMock->method('getOriginalPath')->willReturn('playlists/originals');
		$mediaHandlerMock->expects($this->once())->method('validateStoredFile')
			->willThrowException(new ModuleException('mediapool', 'invalid file'));
		$mediaHandlerMock->expects($this->never())->method('createThumbnail');

		static::assertFalse($this->exportImage->exportPlaylistItem(34));
		static::assertSame('invalid file', $this->exportImage->getErrorText());
	}

	/**
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	private function decode(): void
	{
		$this->dataUrlDecoderMock->expects($this->once())->method('decode')
			->with('data:image/png;base64,abc')
			->willReturn(new DecodedDataUrlFile('image/png', 'png', 'binary', 6));

		$this->exportImage->decode('data:image/png;base64,abc');
	}

	private function expectStoring(AbstractMediaHandler&MockObject $mediaHandlerMock, string $originalPath, string $filePath, string $absolutePath): void
	{
		$mediaHandlerMock->expects($this->once())->method('getOriginalPath')->willReturn($originalPath);
		$mediaHandlerMock->expects($this->once())->method('getAbsolutePath')->with($filePath)->willReturn($absolutePath);
		$mediaHandlerMock->expects($this->once())->method('writeBinaryString')->with($filePath, 'binary');
		$mediaHandlerMock->expects($this->once())->method('validateStoredFile')->with($filePath);
		$mediaHandlerMock->expects($this->once())->method('createThumbnail')->with($absolutePath);
	}
}
