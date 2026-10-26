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

use App\Framework\Media\MediaUrlSigner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;

/**
 * The delivery of files to browsers (media.php): signed addresses, caching, ranges and what the headers do against
 * a file which is more than it seems. The secret is the one of the test deployment.
 */
#[Group('blackbox')]
class MediaDeliveryTest extends MediapoolTestCase
{
	private const string SECRET = 'blackbox-secret-0123456789abcdef';

	/**
	 * @param array<string,string> $headers
	 */
	private function fetch(string $address, string $method = 'GET', array $headers = []): ResponseInterface
	{
		return $this->anna->request($method, $address, ['headers' => $headers]);
	}

	/**
	 * @return array{string,string,string} the original address, the checksum and the extension
	 */
	private function original(): array
	{
		$media = $this->uploadedPicture();

		return [$media['urls']['original'], $media['checksum'], $media['extension']];
	}

	public function testAFileIsDeliveredWithTheHeadersWhichMakeItHarmless(): void
	{
		[$address] = $this->original();

		$response = $this->fetch($address);

		static::assertSame(200, $response->getStatusCode());
		static::assertSame(self::png(), (string) $response->getBody());
		static::assertSame('image/png', $response->getHeaderLine('Content-Type'));
		static::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
		static::assertSame('sandbox', $response->getHeaderLine('Content-Security-Policy'));
		static::assertSame('bytes', $response->getHeaderLine('Accept-Ranges'));
		static::assertSame((string) strlen(self::png()), $response->getHeaderLine('Content-Length'));
		static::assertNotSame('', $response->getHeaderLine('ETag'));
		static::assertStringStartsWith('private', $response->getHeaderLine('Cache-Control'));
	}

	public function testTheThumbnailIsDeliveredToo(): void
	{
		$media = $this->uploadedPicture();

		$response = $this->fetch($media['urls']['thumbnail']);

		static::assertSame(200, $response->getStatusCode());
		static::assertSame('image/png', $response->getHeaderLine('Content-Type'));
	}

	public function testAKnownEtagIsNotModified(): void
	{
		[$address] = $this->original();
		$etag = $this->fetch($address)->getHeaderLine('ETag');

		$response = $this->fetch($address, headers: ['If-None-Match' => $etag]);

		static::assertSame(304, $response->getStatusCode());
		static::assertSame('', (string) $response->getBody());
	}

	public function testHeadGivesTheHeadersWithoutTheFile(): void
	{
		[$address] = $this->original();

		$response = $this->fetch($address, 'HEAD');

		static::assertSame(200, $response->getStatusCode());
		static::assertSame((string) strlen(self::png()), $response->getHeaderLine('Content-Length'));
		static::assertSame('', (string) $response->getBody());
	}

	/**
	 * @return array<string,array{string,int,string,string}> the Range header, the status, Content-Range and the body (of the 70 bytes picture)
	 */
	public static function ranges(): array
	{
		$size = strlen(self::png());

		return [
			'the first ten bytes' => ['bytes=0-9', 206, 'bytes 0-9/' . $size, substr(self::png(), 0, 10)],
			'the last five bytes' => ['bytes=-5', 206, 'bytes ' . ($size - 5) . '-' . ($size - 1) . '/' . $size, substr(self::png(), -5)],
			'from a byte on'      => ['bytes=60-', 206, 'bytes 60-' . ($size - 1) . '/' . $size, substr(self::png(), 60)],
			'after the end'       => ['bytes=0-99999', 206, 'bytes 0-' . ($size - 1) . '/' . $size, self::png()],
			'behind the file'     => ['bytes=1000-', 416, 'bytes */' . $size, ''],
			'backwards'           => ['bytes=9-2', 416, 'bytes */' . $size, ''],
			'text'                => ['bytes=abc', 200, '', self::png()],
			'several ranges'      => ['bytes=0-1,3-4', 200, '', self::png()],
			'another unit'        => ['items=0-1', 200, '', self::png()],
			'no numbers'          => ['bytes=-', 200, '', self::png()],
		];
	}

	#[DataProvider('ranges')]
	public function testRanges(string $range, int $status, string $contentRange, string $body): void
	{
		[$address] = $this->original();

		$response = $this->fetch($address, headers: ['Range' => $range]);

		static::assertSame($status, $response->getStatusCode(), $range);
		static::assertSame($contentRange, $response->getHeaderLine('Content-Range'), $range);
		if ($status !== 416)
			static::assertSame($body, (string) $response->getBody(), $range);
	}

	/**
	 * @return array<string,array{callable(string,string,string):string}> the address of the file changed by an attacker: checksum, extension and the query
	 */
	public static function manipulatedAddresses(): array
	{
		return [
			'without the query'        => [static fn(string $c, string $e, string $q): string => "/media.php/orig/$c.$e"],
			'without the signature'    => [static fn(string $c, string $e, string $q): string => "/media.php/orig/$c.$e?" . preg_replace('/&?sig=[^&]*/', '', $q)],
			'without the expiry'       => [static fn(string $c, string $e, string $q): string => "/media.php/orig/$c.$e?" . preg_replace('/exp=[^&]*&?/', '', $q)],
			'a wrong signature'        => [static fn(string $c, string $e, string $q): string => "/media.php/orig/$c.$e?" . preg_replace('/sig=[0-9a-f]+/', 'sig=' . str_repeat('0', 64), $q)],
			'an empty signature'       => [static fn(string $c, string $e, string $q): string => "/media.php/orig/$c.$e?" . preg_replace('/sig=[0-9a-f]+/', 'sig=', $q)],
			'signatures as a list'     => [static fn(string $c, string $e, string $q): string => "/media.php/orig/$c.$e?" . preg_replace('/sig=/', 'sig[]=', $q)],
			'expiry as a list'         => [static fn(string $c, string $e, string $q): string => "/media.php/orig/$c.$e?" . preg_replace('/exp=/', 'exp[]=', $q)],
			'a later expiry'           => [static fn(string $c, string $e, string $q): string => "/media.php/orig/$c.$e?" . preg_replace('/exp=\d+/', 'exp=99999999999', $q)],
			'another variant'          => [static fn(string $c, string $e, string $q): string => "/media.php/thumb/$c.$e?$q"],
			'another extension'        => [static fn(string $c, string $e, string $q): string => "/media.php/orig/$c.jpg?$q"],
			'another file'             => [static fn(string $c, string $e, string $q): string => "/media.php/orig/" . str_repeat('a', 64) . ".$e?$q"],
			'a variant of a template'  => [static fn(string $c, string $e, string $q): string => "/media.php/tpl-orig/1.$e?$q"],
		];
	}

	/**
	 * @param callable(string,string,string):string $change
	 */
	#[DataProvider('manipulatedAddresses')]
	public function testAManipulatedAddressDeliversNothing(callable $change): void
	{
		[$address, $checksum, $extension] = $this->original();
		$query = (string) parse_url($address, PHP_URL_QUERY);

		$response = $this->fetch($change($checksum, $extension, $query));

		static::assertContains($response->getStatusCode(), [400, 403, 404]);
		static::assertNotSame('image/png', $response->getHeaderLine('Content-Type'));
		static::assertStringNotContainsString(substr(self::png(), 0, 8), (string) $response->getBody());
	}

	public function testAnExpiredAddressWithAValidSignatureDeliversNothing(): void
	{
		[, $checksum, $extension] = $this->original();
		$signed = (new MediaUrlSigner(self::SECRET, 3600))->sign($checksum, $extension, MediaUrlSigner::VARIANT_ORIGINAL, time() - 86400);

		$response = $this->fetch("/media.php/orig/$checksum.$extension?exp={$signed['exp']}&sig={$signed['sig']}");

		static::assertSame(403, $response->getStatusCode());
	}

	public function testAnAddressWithAValidSignatureWorks(): void
	{
		[, $checksum, $extension] = $this->original();
		$signed = (new MediaUrlSigner(self::SECRET, 3600))->sign($checksum, $extension, MediaUrlSigner::VARIANT_ORIGINAL);

		$response = $this->fetch("/media.php/orig/$checksum.$extension?exp={$signed['exp']}&sig={$signed['sig']}");

		static::assertSame(200, $response->getStatusCode());
	}

	/**
	 * @return array<string,array{string}> paths which try to leave the storage or to get another kind of file
	 */
	public static function pathTricks(): array
	{
		$name = str_repeat('a', 64);

		return [
			'parent directories'            => ['/media.php/orig/../../../../.env.png'],
			'parent directories encoded'    => ['/media.php/orig/%2e%2e%2f%2e%2e%2f.env.png'],
			'parent directories twice encoded' => ['/media.php/orig/%252e%252e%252f.env.png'],
			'the variant is a path'         => ['/media.php/../../config/route.php'],
			'the variant is an absolute path' => ['/media.php//etc/passwd.png'],
			'a backslash'                   => ['/media.php/orig/..%5c..%5c.env.png'],
			'a null byte'                   => ["/media.php/orig/$name.png%00.php"],
			'a null byte before the end'    => ["/media.php/orig/$name%00.png"],
			'a php extension'               => ["/media.php/orig/$name.php"],
			'a phtml extension'             => ["/media.php/orig/$name.phtml"],
			'an html extension'             => ["/media.php/orig/$name.html"],
			'a double extension'            => ["/media.php/orig/$name.php.png"],
			'a name in capital letters'     => ['/media.php/orig/' . strtoupper($name) . '.png'],
			'a name which is too long'      => ['/media.php/orig/' . str_repeat('a', 300) . '.png'],
			'a name which is too short'     => ['/media.php/orig/abc.png'],
			'a slash at the end'            => ["/media.php/orig/$name.png/"],
			'a directory'                   => ['/media.php/orig/'],
			'nothing'                       => ['/media.php/'],
		];
	}

	#[DataProvider('pathTricks')]
	public function testAPathWhichIsNoFileDeliversNothing(string $path): void
	{
		$response = $this->rawGet($path . '?exp=99999999999&sig=' . str_repeat('0', 64));

		static::assertContains($response['status'], [400, 403, 404], $path);
		static::assertStringNotContainsString('APP_SECRET', $response['body']);
		static::assertStringNotContainsString('<?php', $response['body']);
	}

	/**
	 * @return array<string,array{string,string,string}> the name of the file, its content and the type which the browser says
	 */
	public static function otherKindsOfFiles(): array
	{
		return [
			'a web page'  => ['page.html', '<html><script>alert(document.cookie)</script></html>', 'text/html'],
			'a text'      => ['note.txt', 'text', 'text/plain'],
			'json'        => ['data.json', '{"a":1}', 'application/json'],
			'xml'         => ['data.xml', '<?xml version="1.0"?><a/>', 'application/xml'],
			'a script'    => ['run.js', 'alert(1)', 'application/javascript'],
			'a stylesheet' => ['x.css', 'body{}', 'text/css'],
		];
	}

	#[DataProvider('otherKindsOfFiles')]
	public function testNoFileIsDeliveredAsAPageOfTheApplication(string $filename, string $bytes, string $mime): void
	{
		$answer = $this->json($this->upload($filename, $bytes, mime: $mime));
		if (($answer['success'] ?? false) !== true)
		{
			static::addToAssertionCount(1);

			return;
		}

		foreach ($this->mediaOf() as $media)
		{
			$response = $this->fetch($media['urls']['original']);
			// delivered as a file to save, or not at all, but never as a page which runs in the origin
			static::assertDoesNotMatchRegularExpression('#text/html|javascript|text/css#i', $response->getHeaderLine('Content-Type'), $filename);
			static::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options') ?: 'nosniff');
			if ($response->getStatusCode() === 200 && in_array($media['extension'], ['txt', 'json', 'xml'], true))
				static::assertStringStartsWith('attachment', $response->getHeaderLine('Content-Disposition'), $filename);
		}
	}
}
