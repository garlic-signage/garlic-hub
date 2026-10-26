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

use App\Framework\Media\FileResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The Range header for media.php and delivery.php: which part of a file of 1000 bytes is sent.
 * The answers over HTTP are tested in tests/Blackbox/Modules/Mediapool (MediaDeliveryTest, PlayerDeliveryTest).
 */
class FileResponseTest extends TestCase
{
	/**
	 * @return array<string, array{string, int, int, bool}> header, start, end, part of the file
	 */
	public static function ranges(): array
	{
		return [
			'no header'               => ['', 0, 999, false],
			'the first ten bytes'     => ['bytes=0-9', 0, 9, true],
			'from the middle on'      => ['bytes=500-', 500, 999, true],
			'the last 100 bytes'      => ['bytes=-100', 900, 999, true],
			'more than the file'      => ['bytes=-5000', 0, 999, true],
			'an end behind the file'  => ['bytes=990-5000', 990, 999, true],
			'the last byte'           => ['bytes=999-999', 999, 999, true],
			'multi-range'             => ['bytes=0-9,20-29', 0, 999, false],
			'no numbers'              => ['bytes=-', 0, 999, false],
			'another unit'            => ['items=0-9', 0, 999, false],
			'text'                    => ['bytes=a-b', 0, 999, false],
			'a minus sign'            => ['bytes=-5-9', 0, 999, false],
		];
	}

	#[Group('units')]
	#[DataProvider('ranges')]
	public function testTheRangeGivesThePartOfTheFile(string $header, int $start, int $end, bool $partial): void
	{
		static::assertSame(['start' => $start, 'end' => $end, 'partial' => $partial], FileResponse::resolveRange($header, 1000));
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function rangesOutsideTheFile(): array
	{
		return [
			'start at the end'     => ['bytes=1000-'],
			'start behind the end' => ['bytes=5000-6000'],
			'start after the end'  => ['bytes=9-0'],
			'nothing of the end'   => ['bytes=-0'],
		];
	}

	#[Group('units')]
	#[DataProvider('rangesOutsideTheFile')]
	public function testARangeOutsideTheFileIsNotSatisfiable(string $header): void
	{
		static::assertNull(FileResponse::resolveRange($header, 1000));
	}

	#[Group('units')]
	public function testAnEmptyFileWithoutRangeIsSentEmpty(): void
	{
		static::assertSame(['start' => 0, 'end' => -1, 'partial' => false], FileResponse::resolveRange('', 0));
		static::assertNull(FileResponse::resolveRange('bytes=0-', 0));
	}

	#[Group('units')]
	public function testEveryDownloadHasAMediaType(): void
	{
		foreach (FileResponse::DOWNLOAD as $extension)
			static::assertArrayHasKey($extension, FileResponse::MIME);
	}

	/**
	 * Nothing which a browser would run as a page or a script: such files are delivered as download or not at all.
	 */
	#[Group('units')]
	public function testNoMediaTypeIsAPageOrAScript(): void
	{
		foreach (FileResponse::MIME as $extension => $type)
		{
			static::assertDoesNotMatchRegularExpression('#html|javascript|ecmascript|text/css#i', $type, $extension);
			if (str_starts_with($type, 'text/') || (str_starts_with($type, 'application/') && $type !== 'application/pdf'))
				static::assertContains($extension, FileResponse::DOWNLOAD, $extension . ' is shown in the browser');
		}
	}
}
