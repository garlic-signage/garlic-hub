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

namespace Tests\Integration\Framework\Media;

use App\Framework\Media\MimeTypeExtensionMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * public/media.php and public/delivery.php work without autoloader and have their own table
 * (extension => MIME type). It has to know every extension the MimeTypeExtensionMapper can give a
 * file, otherwise the file is stored but its URL answers with an error.
 */
#[Group('io')]
class DeliveryScriptsTest extends TestCase
{
	private const string PUBLIC_DIR = __DIR__ . '/../../../../public';

	/**
	 * @return array<string, array{string}>
	 */
	public static function scriptProvider(): array
	{
		return ['media.php' => ['media.php'], 'delivery.php' => ['delivery.php']];
	}

	#[DataProvider('scriptProvider')]
	public function testTheScriptKnowsEveryExtensionOfTheMapper(string $script): void
	{
		$table = $this->mimeTable($script);

		foreach ($this->mapperExtensions() as $mimeType => $extension)
		{
			static::assertArrayHasKey($extension, $table, "$script does not deliver .$extension (from $mimeType).");
		}
		// the mapper gives .bin to everything it does not know
		static::assertArrayHasKey('bin', $table);
	}

	#[DataProvider('scriptProvider')]
	public function testEveryDownloadExtensionIsInTheTable(string $script): void
	{
		$table = $this->mimeTable($script);

		foreach ($this->downloadList($script) as $extension)
		{
			static::assertArrayHasKey($extension, $table, "$script: .$extension is a download but has no MIME type.");
		}
	}

	/**
	 * Types which are shown in the browser must not be downloads and the other way round,
	 * or a player gets different answers from the two scripts.
	 */
	public function testBothScriptsDeliverTheSameTypesTheSameWay(): void
	{
		$media    = $this->mimeTable('media.php');
		$delivery = $this->mimeTable('delivery.php');
		ksort($media);
		ksort($delivery);

		static::assertSame($media, $delivery);

		$mediaDownloads    = $this->downloadList('media.php');
		$deliveryDownloads = $this->downloadList('delivery.php');
		sort($mediaDownloads);
		sort($deliveryDownloads);

		static::assertSame($mediaDownloads, $deliveryDownloads);
	}

	/**
	 * @return array<string,string> extension => MIME type of the script
	 */
	private function mimeTable(string $script): array
	{
		$source = (string) file_get_contents(self::PUBLIC_DIR . '/' . $script);
		static::assertSame(1, preg_match('/const MEDIA_MIME = \[(.*?)\n\];/s', $source, $block), "No MEDIA_MIME in $script.");
		preg_match_all("/'([a-z0-9]+)'\s*=>\s*'([^']+)'/", $block[1], $pairs, PREG_SET_ORDER);

		$table = [];
		foreach ($pairs as $pair)
		{
			$table[$pair[1]] = $pair[2];
		}

		return $table;
	}

	/**
	 * @return list<string>
	 */
	private function downloadList(string $script): array
	{
		$source = (string) file_get_contents(self::PUBLIC_DIR . '/' . $script);
		static::assertSame(1, preg_match('/const MEDIA_DOWNLOAD = \[(.*?)\];/s', $source, $block), "No MEDIA_DOWNLOAD in $script.");
		preg_match_all("/'([a-z0-9]+)'/", $block[1], $extensions);

		return array_values(array_unique($extensions[1]));
	}

	/**
	 * @return array<string,string> MIME type => extension, read from the mapper itself
	 */
	private function mapperExtensions(): array
	{
		$property = new \ReflectionProperty(MimeTypeExtensionMapper::class, 'preferredMimeTypes');

		/** @var array<string,string> $types */
		$types = $property->getValue(new MimeTypeExtensionMapper());

		return $types;
	}
}
