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

namespace Tests\Blackbox\Modules\Mediapool;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * The delivery of the originals to players (delivery.php). A player is recognized by a UUID in its User-Agent or in
 * the header X-Signage-Agent, nothing more: the file is named by the hash of its content.
 */
#[Group('blackbox')]
class PlayerDeliveryTest extends MediapoolTestCase
{
	private const string PLAYER = 'GAPI/1.0 (UUID:3f0cd56c-d511-486a-a8e1-9d2cefd78b3f; NAME:a) garlic-macOS/v0.6.0.679 (MODEL:Garlic)';

	/**
	 * @return array{string,string} the address of the original for players and its checksum
	 */
	private function original(): array
	{
		$media = $this->uploadedPicture();

		return ['/delivery.php/' . $media['checksum'] . '.' . $media['extension'], $media['checksum']];
	}

	/**
	 * @return array<string,string>
	 */
	private static function asPlayer(): array
	{
		return ['User-Agent' => self::PLAYER];
	}

	public function testAPlayerGetsTheFileWithTheHeadersWhichMakeItHarmless(): void
	{
		[$address, $checksum] = $this->original();

		$response = $this->rawGet($address, self::asPlayer());

		static::assertSame(200, $response['status']);
		static::assertSame(self::png(), $response['body']);
		static::assertSame('image/png', $response['headers']['content-type']);
		static::assertSame('nosniff', $response['headers']['x-content-type-options']);
		static::assertSame('sandbox', $response['headers']['content-security-policy']);
		static::assertSame('"' . $checksum . '"', $response['headers']['etag']);
		static::assertStringContainsString('immutable', $response['headers']['cache-control']);
	}

	/**
	 * @return array<string,array{array<string,string>,int}> the headers of the request and the status
	 */
	public static function visitors(): array
	{
		return [
			'a player in the user agent'       => [['User-Agent' => self::PLAYER], 200],
			'a player in the extra header'     => [['User-Agent' => 'Mozilla/5.0', 'X-Signage-Agent' => self::PLAYER], 200],
			'a browser'                        => [['User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) Firefox/130.0'], 403],
			'no user agent'                    => [['User-Agent' => ''], 403],
			'a user agent without a UUID'      => [['User-Agent' => 'GAPI/1.0 (NAME:a) garlic-macOS/v0.6.0.679'], 403],
			'the word UUID without a value'    => [['User-Agent' => 'UUID:'], 403],
		];
	}

	/**
	 * @param array<string,string> $headers
	 */
	#[DataProvider('visitors')]
	public function testOnlyWhoLooksLikeAPlayerGetsTheFile(array $headers, int $status): void
	{
		[$address] = $this->original();

		$response = $this->rawGet($address, $headers);

		static::assertSame($status, $response['status']);
		if ($status === 403)
		{
			static::assertSame('', $response['body']);
			static::assertSame('no-store', $response['headers']['cache-control']);
		}
	}

	public function testAKnownEtagIsNotModified(): void
	{
		[$address, $checksum] = $this->original();

		$response = $this->rawGet($address, self::asPlayer() + ['If-None-Match' => '"' . $checksum . '"']);

		static::assertSame(304, $response['status']);
		static::assertSame('', $response['body']);
	}

	public function testRangesWork(): void
	{
		[$address] = $this->original();
		$size = strlen(self::png());

		$part = $this->rawGet($address, self::asPlayer() + ['Range' => 'bytes=0-9']);
		$late = $this->rawGet($address, self::asPlayer() + ['Range' => 'bytes=1000-']);

		static::assertSame(206, $part['status']);
		static::assertSame('bytes 0-9/' . $size, $part['headers']['content-range']);
		static::assertSame(substr(self::png(), 0, 10), $part['body']);
		static::assertSame(416, $late['status']);
		static::assertSame('bytes */' . $size, $late['headers']['content-range']);
	}

	public function testAnUnknownFileIsNotFound(): void
	{
		$response = $this->rawGet('/delivery.php/' . str_repeat('a', 64) . '.png', self::asPlayer());

		static::assertSame(404, $response['status']);
		static::assertSame('', $response['body']);
	}

	/**
	 * @return array<string,array{string}> paths which try to leave the storage or to get something else
	 */
	public static function pathTricks(): array
	{
		$name = str_repeat('a', 64);

		return [
			'parent directories'            => ['/delivery.php/../../../../.env.png'],
			'parent directories encoded'    => ['/delivery.php/%2e%2e%2f%2e%2e%2f.env.png'],
			'a backslash'                   => ['/delivery.php/..%5c..%5c.env.png'],
			'a null byte'                   => ["/delivery.php/$name.png%00.php"],
			'a php extension'               => ["/delivery.php/$name.php"],
			'an html extension'             => ["/delivery.php/$name.html"],
			'a name in capital letters'     => ['/delivery.php/' . strtoupper($name) . '.png'],
			'a name which is too long'      => ['/delivery.php/' . str_repeat('a', 300) . '.png'],
			'a directory'                   => ['/delivery.php/'],
			'thumbnails are no originals'   => ["/delivery.php/thumb/$name.png"],
			'a source which does not exist' => ["/delivery.php/other/$name.png"],
			'the source twice'              => ["/delivery.php/tpl/tpl/1.png"],
			'a template which is no number' => ['/delivery.php/tpl/abc.jpg'],
			'a template with parents'       => ['/delivery.php/tpl/../../../.env.jpg'],
			'a template with a long number' => ['/delivery.php/tpl/' . str_repeat('1', 30) . '.jpg'],
			'a template with a short hmac'  => ['/delivery.php/tpl/5-abc.jpg'],
			'a template with a hmac only'   => ['/delivery.php/tpl/-' . str_repeat('a', 32) . '.jpg'],
			'a template with capitals'      => ['/delivery.php/tpl/5-' . str_repeat('A', 32) . '.jpg'],
			'a template with parents in it' => ['/delivery.php/tpl/5-../../.env.jpg'],
			'a mediapool name with a hyphen' => ["/delivery.php/5-$name.png"],
		];
	}

	#[DataProvider('pathTricks')]
	public function testAPathWhichIsNoFileDeliversNothing(string $path): void
	{
		$response = $this->rawGet($path, self::asPlayer());

		static::assertContains($response['status'], [400, 404], $path);
		static::assertStringNotContainsString('APP_SECRET', $response['body']);
		static::assertStringNotContainsString('<?php', $response['body']);
	}

	/**
	 * A template item is named by its id and the hmac of its content. Until the first export after an update
	 * it is named by its id only.
	 */
	public function testATemplateItemIsDeliveredByTheNameOfItsContentAndByItsIdBeforeTheFirstExport(): void
	{
		$directory = $this->deployment()->directory() . '/var/storage/playlists/items/originals';
		$name      = '5-' . str_repeat('0123456789abcdef', 2);
		file_put_contents($directory . '/' . $name . '.png', self::png());
		file_put_contents($directory . '/6.png', self::png());

		try
		{
			$byContent = $this->rawGet('/delivery.php/tpl/' . $name . '.png', self::asPlayer());
			$byId      = $this->rawGet('/delivery.php/tpl/6.png', self::asPlayer());
		}
		finally
		{
			unlink($directory . '/' . $name . '.png');
			unlink($directory . '/6.png');
		}

		static::assertSame([200, 200], [$byContent['status'], $byId['status']]);
		static::assertSame(self::png(), $byContent['body']);
		static::assertSame('image/png', $byContent['headers']['content-type']);
	}

	public function testATemplateWhichWasNotRenderedIsNotFound(): void
	{
		$response = $this->rawGet('/delivery.php/tpl/1.jpg', self::asPlayer());

		static::assertSame(404, $response['status']);
	}

	public function testTheEtagOfATemplateDoesNotMatchBeforeItExists(): void
	{
		$response = $this->rawGet('/delivery.php/tpl/1.jpg', self::asPlayer() + ['If-None-Match' => '"1-1"']);

		static::assertSame(404, $response['status']);
	}
}
