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

use App\Framework\Media\FileResponse;
use App\Framework\Media\MimeTypeExtensionMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * public/media.php and public/delivery.php deliver with the table of FileResponse (extension => MIME type).
 * It has to know every extension the MimeTypeExtensionMapper can give a file, otherwise the file is stored but
 * its URL answers with an error.
 */
#[Group('io')]
class DeliveryScriptsTest extends TestCase
{
	private const string PUBLIC_DIR = __DIR__ . '/../../../../public';

	public function testEveryExtensionOfTheMapperIsDelivered(): void
	{
		foreach ($this->mapperExtensions() as $mimeType => $extension)
		{
			static::assertArrayHasKey($extension, FileResponse::MIME, "The scripts do not deliver .$extension (from $mimeType).");
		}
		// the mapper gives .bin to everything it does not know
		static::assertArrayHasKey('bin', FileResponse::MIME);
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function scriptProvider(): array
	{
		return ['media.php' => ['media.php'], 'delivery.php' => ['delivery.php']];
	}

	/**
	 * A table of its own in a script would deliver a type differently than the other script.
	 */
	#[DataProvider('scriptProvider')]
	public function testTheScriptHasNoTableOfItsOwn(string $script): void
	{
		$source = (string) file_get_contents(self::PUBLIC_DIR . '/' . $script);

		static::assertStringContainsString('FileResponse::MIME', $source);
		static::assertStringContainsString('FileResponse::sendFileHeaders(', $source);
		static::assertDoesNotMatchRegularExpression("#'(jpg|mp4|pdf)'\s*=>#", $source, "$script has its own table of MIME types.");
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
