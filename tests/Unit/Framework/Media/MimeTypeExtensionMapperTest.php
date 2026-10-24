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


namespace Tests\Unit\Framework\Media;

use App\Framework\Media\MimeTypeExtensionMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class MimeTypeExtensionMapperTest extends TestCase
{
	private MimeTypeExtensionMapper $mimeTypeExtensionsMapper;

	protected function setUp(): void
	{
		parent::setUp();
		$this->mimeTypeExtensionsMapper = new MimeTypeExtensionMapper();
	}


	#[Group('units')]
	#[DataProvider('mimeTypeProvider')]
	public function testDetermineExtension(string $mimeType, string $expectedExtension): void
	{
		static::assertSame($expectedExtension, $this->mimeTypeExtensionsMapper->determineExtension($mimeType));
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function mimeTypeProvider(): array
	{
		return [
			'jpeg'           => ['image/jpeg', 'jpg'],
			'avif'           => ['image/avif', 'avif'],
			'png'            => ['image/png', 'png'],
			'gif'            => ['image/gif', 'gif'],
			'webp'           => ['image/webp', 'webp'],
			'svg'            => ['image/svg+xml', 'svg'],
			'bmp'            => ['image/bmp', 'bmp'],
			'bmp (x-bmp)'    => ['image/x-bmp', 'bmp'],
			'bmp (x-ms-bmp)' => ['image/x-ms-bmp', 'bmp'],
			'tiff'           => ['image/tiff', 'tif'],
			'mp3'            => ['audio/mpeg', 'mp3'],
			'audio mp4'      => ['audio/mp4', 'mp4'],
			'audio ogg'      => ['audio/ogg', 'ogg'],
			'opus'           => ['audio/opus', 'opus'],
			'wav'            => ['audio/wav', 'wav'],
			'video mp4'      => ['video/mp4', 'mp4'],
			'avi'            => ['video/x-msvideo', 'avi'],
			'mkv'            => ['video/x-matroska', 'mkv'],
			'webm'           => ['video/webm', 'webm'],
			'video ogg'      => ['video/ogg', 'ogg'],
			'mov'            => ['video/quicktime', 'mov'],
			'mpg'            => ['video/mpeg', 'mpg'],
			'pdf'            => ['application/pdf', 'pdf'],
			'widget'         => ['application/widget', 'wgt'],
			'octet-stream'   => ['application/octet-stream', 'wgt'],
			'zip'            => ['application/zip', 'zip'],
			'json'           => ['application/json', 'json'],
			'xml'            => ['application/xml', 'xml'],
			'text xml'       => ['text/xml', 'xml'],
			'rss'            => ['application/rss+xml', 'rss'],
			'atom'           => ['application/atom+xml', 'atom'],
			'apk'            => ['application/vnd.android.package-archive', 'apk'],
			'smil'           => ['application/smil', 'smil'],
			'text'           => ['text/plain', 'txt'],
			'csv'            => ['text/csv', 'csv'],
		];
	}

	#[Group('units')]
	#[DataProvider('unknownMimeTypeProvider')]
	public function testDetermineExtensionReturnsBinForAnUnknownMimeType(string $mimeType): void
	{
		static::assertSame('bin', $this->mimeTypeExtensionsMapper->determineExtension($mimeType));
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function unknownMimeTypeProvider(): array
	{
		return ['unknown' => ['unknown/mime-type'], 'empty' => [''], 'wrong case' => ['IMAGE/JPEG'], 'with parameter' => ['text/plain; charset=utf-8']];
	}
}
