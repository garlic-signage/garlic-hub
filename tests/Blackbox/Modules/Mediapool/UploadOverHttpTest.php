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
 * Uploading a file over HTTP: what is stored, where, under which name, and what is refused.
 */
#[Group('blackbox')]
class UploadOverHttpTest extends MediapoolTestCase
{
	/**
	 * @return array<string,array{string,string,string}> the name of the file, its content and the type of the picture
	 */
	public static function pictures(): array
	{
		return [
			'png' => ['pixel.png', self::png(), 'image/png'],
		];
	}

	#[DataProvider('pictures')]
	public function testAPictureIsStoredAndListed(string $filename, string $bytes, string $mime): void
	{
		$before = $this->files();

		$answer = $this->json($this->upload($filename, $bytes, mime: $mime));

		static::assertTrue($answer['success'] ?? false, json_encode($answer, JSON_THROW_ON_ERROR));
		$media = $this->mediaOf();
		static::assertSame([$filename], array_column($media, 'filename'));
		// the name of the stored file is the hash of the content
		static::assertSame(hash('sha256', $bytes), $media[0]['checksum']);
		static::assertSame(2, (int) $media[0]['UID']);
		$new = $this->newFilesSince($before);
		static::assertContains('var/storage/mediapool/originals/' . $media[0]['checksum'] . '.png', $new);
		foreach ($new as $file)
			static::assertStringStartsWith('var/storage/mediapool/', $file);
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function dangerousNames(): array
	{
		return [
			'parent directories'  => ['../../evil.png'],
			'backslashes'         => ['..\\..\\evil.png'],
			'absolute path'       => ['/etc/evil.png'],
			'php before the end'  => ['x.php.png'],
			'encoded null byte'   => ['evil.php%00.png'],
			'only dots'           => ['....png'],
			'special characters'  => ['a<b>"c\'d;e|f&g.png'],
			'very long'           => [str_repeat('a', 300) . '.png'],
			'umlauts and spaces'  => ['Größe und Maß.png'],
		];
	}

	#[DataProvider('dangerousNames')]
	public function testAFileNameCanNotLeaveTheStorage(string $filename): void
	{
		$before = $this->files();

		$this->upload($filename, self::png());

		// stored or refused: nothing is outside of the storage of the mediapool and nothing can be run
		static::assertSame([], array_filter($this->newFilesSince($before), static fn(string $file): bool => !str_starts_with($file, 'var/storage/mediapool/')));
		foreach ($this->newFilesSince($before) as $file)
		{
			static::assertStringStartsWith('var/storage/mediapool/', $file);
			static::assertMatchesRegularExpression('#^var/storage/mediapool/(originals|thumbs|previews)/[a-f0-9]{64}\.png$#', $file);
		}
		foreach ($this->connection->fetchFirstColumn('SELECT filename FROM mediapool_files WHERE UID = 2') as $stored)
		{
			static::assertStringNotContainsString('/', (string) $stored);
			static::assertStringNotContainsString('\\', (string) $stored);
			static::assertStringNotContainsString("\0", (string) $stored);
			static::assertNotContains((string) $stored, ['.', '..', '']);
		}
	}

	/**
	 * @return array<string,array{string,string,string}> the name, the content and the type which the browser says
	 */
	public static function filesWhichAreNoPictures(): array
	{
		return [
			'php as png'         => ['shell.png', '<?php echo shell_exec($_GET["c"]);', 'image/png'],
			'php as php'         => ['shell.php', '<?php echo 1;', 'application/x-httpd-php'],
			'php as phtml'       => ['shell.phtml', '<?php echo 1;', 'text/html'],
			'text as jpg'        => ['text.jpg', 'just some text', 'image/jpeg'],
			'html as png'        => ['page.png', '<html><script>alert(1)</script></html>', 'image/png'],
			'htaccess'           => ['.htaccess', "php_flag engine on\nAddType application/x-httpd-php .png", 'text/plain'],
			'empty file'         => ['empty.png', '', 'image/png'],
			'a png with php'     => ['both.php', self::png() . '<?php echo 1;', 'image/png'],
		];
	}

	public function testANullByteInTheFileNameCanNotCutTheExtension(): void
	{
		$before = $this->files();

		$this->uploadRaw("evil.php\0.png", self::png());

		foreach ($this->newFilesSince($before) as $file)
			static::assertMatchesRegularExpression('#^var/storage/mediapool/(originals|thumbs|previews)/[a-f0-9]{64}\.png$#', $file);
		foreach ($this->connection->fetchFirstColumn('SELECT filename FROM mediapool_files') as $stored)
			static::assertStringNotContainsString("\0", (string) $stored);
	}

	#[DataProvider('filesWhichAreNoPictures')]
	public function testAFileWhichIsNoPictureIsRefusedOrHarmless(string $filename, string $bytes, string $mime): void
	{
		$before = $this->files();

		$answer = $this->json($this->upload($filename, $bytes, mime: $mime));

		foreach ($this->newFilesSince($before) as $file)
		{
			static::assertStringStartsWith('var/storage/mediapool/', $file);
			// nothing which a web server would run
			static::assertDoesNotMatchRegularExpression('/\.(php\d?|phtml|phar|htaccess|html?)$/i', $file);
		}
		if (($answer['success'] ?? false) === true)
		{
			// accepted: then not as the type which the name claims
			foreach ($this->mediaOf() as $media)
			{
				if (str_ends_with($filename, '.png') && str_starts_with($bytes, '<'))
					static::assertNotSame('png', $media['extension'], 'a page is no png');
			}
		}
		else
		{
			static::assertSame(0, $this->mediaCount());
		}
	}

	public function testAFailedUploadLeavesNothingBehind(): void
	{
		$before = $this->files();

		$answer = $this->json($this->upload('shell.png', '<?php echo 1;'));

		static::assertFalse($answer['success'] ?? true);
		static::assertSame([], $this->newFilesSince($before));
		static::assertSame(0, $this->mediaCount());
	}

	public function testTheSameContentTwiceIsStoredOnce(): void
	{
		$this->upload('first.png', self::png());
		$this->upload('second.png', self::png());

		$originals = glob($this->deployment()->directory() . '/var/storage/mediapool/originals/*.png');
		static::assertIsArray($originals);
		static::assertCount(1, $originals);
		static::assertEqualsCanonicalizing(['first.png', 'second.png'], array_column($this->mediaOf(), 'filename'));
	}

	public function testTheSameNameWithAnotherContentDoesNotReplaceTheOriginal(): void
	{
		$first = $this->uploadedPicture('same.png');
		$this->upload('same.png', self::otherPng());

		$originals = glob($this->deployment()->directory() . '/var/storage/mediapool/originals/*.png');
		static::assertIsArray($originals);
		static::assertCount(2, $originals);
		static::assertSame(self::png(), file_get_contents($this->deployment()->directory() . '/' . $first['paths']['original']));
	}

	public function testAnUploadNeedsTheCsrfToken(): void
	{
		$before = $this->files();

		$answer = $this->json($this->upload('pixel.png', self::png(), withToken: false));

		static::assertFalse($answer['success'] ?? true);
		static::assertSame([], $this->newFilesSince($before));
		static::assertSame(0, $this->mediaCount());
	}

	public function testAnUploadNeedsAFile(): void
	{
		$response = $this->anna->request('POST', '/async/mediapool/uploadLocalFile', ['form_params' => ['node_id' => '8', 'csrf_token' => $this->anna->csrfToken('/mediapool')]]);

		static::assertFalse($this->json($response)['success'] ?? true);
		static::assertSame(0, $this->mediaCount());
	}

	/**
	 * @return array<string,array{int}> the folders anna may not use
	 */
	public static function foldersOfOthers(): array
	{
		return ['private folder of bernd' => [11], 'public folder of bernd' => [12], 'the public tree' => [2], 'the folder which is none' => [9999]];
	}

	#[DataProvider('foldersOfOthers')]
	public function testAUserDoesNotUploadIntoAForeignFolder(int $node): void
	{
		$before = $this->files();

		$answer = $this->json($this->upload('pixel.png', self::png(), $node));

		static::assertFalse($answer['success'] ?? true, 'node ' . $node);
		static::assertSame([], $this->newFilesSince($before));
		static::assertSame(0, $this->mediaCount());
	}

	/**
	 * @return array<string,array{string}> a node id which is no id
	 */
	public static function nodesWhichAreNoIds(): array
	{
		return ['text' => ['abc'], 'zero' => ['0'], 'negative' => ['-8'], 'a list' => ['8,11'], 'empty' => ['']];
	}

	#[DataProvider('nodesWhichAreNoIds')]
	public function testAFolderWhichIsNoIdIsNoUpload(string $node): void
	{
		$response = $this->anna->request('POST', '/async/mediapool/uploadLocalFile', ['multipart' => [
			['name' => 'node_id', 'contents' => $node],
			['name' => 'csrf_token', 'contents' => $this->anna->csrfToken('/mediapool')],
			['name' => 'file', 'contents' => self::png(), 'filename' => 'pixel.png'],
		]]);

		static::assertFalse($this->json($response)['success'] ?? true, $node);
		static::assertSame(0, $this->mediaCount());
	}

	public function testSeveralFilesInOneFieldAreNoUpload(): void
	{
		$response = $this->anna->request('POST', '/async/mediapool/uploadLocalFile', ['multipart' => [
			['name' => 'node_id', 'contents' => '8'],
			['name' => 'csrf_token', 'contents' => $this->anna->csrfToken('/mediapool')],
			['name' => 'file[]', 'contents' => self::png(), 'filename' => 'one.png'],
			['name' => 'file[]', 'contents' => self::otherPng(), 'filename' => 'two.png'],
		]]);

		static::assertSame(400, $response->getStatusCode() >= 400 ? 400 : $response->getStatusCode());
		static::assertSame(0, $this->mediaCount());
	}

	public function testAGuestCanNotUpload(): void
	{
		$guest    = $this->browser();
		$response = $guest->request('POST', '/async/mediapool/uploadLocalFile', ['multipart' => [
			['name' => 'node_id', 'contents' => '8'],
			['name' => 'csrf_token', 'contents' => 'x'],
			['name' => 'file', 'contents' => self::png(), 'filename' => 'pixel.png'],
		]]);

		static::assertGreaterThanOrEqual(400, $response->getStatusCode());
		static::assertSame(0, $this->mediaCount());
	}

	public function testAScriptInAnSvgIsStoredButTheDeliveryDefusesIt(): void
	{
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(document.cookie)</script><rect width="1" height="1"/></svg>';

		$answer = $this->json($this->upload('x.svg', $svg, mime: 'image/svg+xml'));
		if (($answer['success'] ?? false) !== true)
		{
			static::addToAssertionCount(1);

			return;
		}

		$media = $this->mediaOf()[0];
		$url   = $media['urls']['original'];
		$file  = $this->anna->get($url);
		static::assertSame(200, $file->getStatusCode());
		static::assertSame('image/svg+xml', $file->getHeaderLine('Content-Type'));
		static::assertSame('nosniff', $file->getHeaderLine('X-Content-Type-Options'));
		// sandbox: no script runs in the origin of the application when the address is opened
		static::assertSame('sandbox', $file->getHeaderLine('Content-Security-Policy'));
	}
}
