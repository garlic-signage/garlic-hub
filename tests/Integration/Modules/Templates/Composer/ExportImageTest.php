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

namespace Tests\Integration\Modules\Templates\Composer;

use App\Framework\Media\DataUrlDecoder;
use App\Framework\Media\FileInfoWrapper;
use App\Framework\Media\MimeTypeDetector;
use App\Framework\Media\MimeTypeExtensionMapper;
use App\Framework\Media\MimeTypeService;
use App\Modules\Templates\Helper\Composer\ExportImage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Support\MediaWorkspace;

/**
 * The image of a template or of a playlist item as data url: decoded, stored as original and as thumbnail.
 * The files are written to a temporary directory.
 */
#[Group('io')]
class ExportImageTest extends TestCase
{
	private const string PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

	private MediaWorkspace $workspace;
	private ExportImage $exportImage;

	protected function setUp(): void
	{
		parent::setUp();
		$this->workspace = new MediaWorkspace();
		foreach (['templates', 'playlists/items'] as $module)
		{
			foreach (['originals', 'thumbs'] as $sub)
				mkdir($this->workspace->dir . '/var/storage/' . $module . '/' . $sub, 0775, true);
		}

		$decoder          = new DataUrlDecoder(new MimeTypeService(new MimeTypeDetector(new FileInfoWrapper()), new MimeTypeExtensionMapper()));
		$this->exportImage = new ExportImage($decoder, $this->workspace->handlerFactory());
	}

	protected function tearDown(): void
	{
		$this->workspace->remove();
		parent::tearDown();
	}

	/**
	 * @return list<string>
	 */
	private function files(string $directory): array
	{
		return array_map(static fn(mixed $path): string => basename((string) $path), (array) glob($this->workspace->dir . '/var/storage/' . $directory . '/*'));
	}

	public function testATemplateIsStoredAsOriginalAndThumbnail(): void
	{
		$this->exportImage->decode(self::PNG);

		static::assertTrue($this->exportImage->exportBase64(12));

		static::assertSame(['12.png'], $this->files('templates/originals'));
		static::assertSame(['12.png'], $this->files('templates/thumbs'));
		static::assertSame([], $this->files('playlists/items/originals'));
		static::assertSame('image/png', $this->exportImage->getMimeType());
		static::assertSame('', $this->exportImage->getErrorText());
	}

	public function testAPlaylistItemIsStoredInTheDirectoriesOfThePlaylists(): void
	{
		$this->exportImage->decode(self::PNG);

		static::assertTrue($this->exportImage->exportPlaylistItem(34));

		static::assertSame(['34.png'], $this->files('playlists/items/originals'));
		static::assertSame(['34.png'], $this->files('playlists/items/thumbs'));
		static::assertSame([], $this->files('templates/originals'));
	}

	public function testAFileWhichIsNoImageIsNotKept(): void
	{
		$this->exportImage->decode('data:image/png;base64,' . base64_encode('this is not an image'));

		static::assertFalse($this->exportImage->exportBase64(12));

		static::assertNotSame('', $this->exportImage->getErrorText());
		static::assertSame([], $this->files('templates/originals'));
		static::assertSame([], $this->files('templates/thumbs'));
	}

	public function testAFileWhichIsNoImageIsNotKeptForAPlaylistItemEither(): void
	{
		$this->exportImage->decode('data:image/png;base64,' . base64_encode('this is not an image'));

		static::assertFalse($this->exportImage->exportPlaylistItem(34));

		static::assertNotSame('', $this->exportImage->getErrorText());
		static::assertSame([], $this->files('playlists/items/originals'));
		static::assertSame([], $this->files('playlists/items/thumbs'));
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function invalidDataUrls(): array
	{
		return [
			'no data url'        => ['no image'],
			'without data'       => ['data:image/png;base64,'],
			'no base64'          => ['data:image/png;base64,%%%'],
			'without a mimetype' => ['data:;base64,AAAA'],
			'an empty text'      => [''],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('invalidDataUrls')]
	public function testAnImageWhichIsNoDataUrlIsNotExported(string $dataUrl): void
	{
		$this->exportImage->decode($dataUrl);

		static::assertNotSame('', $this->exportImage->getErrorText());
		static::assertFalse($this->exportImage->exportBase64(12));
		static::assertFalse($this->exportImage->exportPlaylistItem(12));
		static::assertSame([], $this->files('templates/originals'));
		static::assertSame([], $this->files('playlists/items/originals'));
	}

	public function testAFailedDecodeDoesNotExportTheImageOfAnEarlierOne(): void
	{
		$this->exportImage->decode(self::PNG);
		$this->exportImage->decode('data:image/png;base64,');

		static::assertFalse($this->exportImage->exportBase64(12));

		static::assertSame([], $this->files('templates/originals'));
	}

	public function testWithoutDecodeNothingIsExported(): void
	{
		static::assertFalse($this->exportImage->exportBase64(12));
		static::assertSame('No image decoded.', $this->exportImage->getErrorText());
		static::assertSame([], $this->files('templates/originals'));
	}

	public function testTheErrorOfAnEarlierDecodeIsGone(): void
	{
		$this->exportImage->decode('no image');
		$this->exportImage->decode(self::PNG);

		static::assertSame('', $this->exportImage->getErrorText());
		static::assertTrue($this->exportImage->exportBase64(12));
	}
}
