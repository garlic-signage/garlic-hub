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

use Psr\Http\Message\ResponseInterface;
use Tests\Blackbox\Support\BlackboxTestCase;
use Tests\Blackbox\Support\Browser;

/**
 * The base of the blackbox tests of the mediapool: anna is logged in and may use her folder Fotos (node 8).
 * Users: 1 admin, 2 anna, 3 bernd. Bernd has the folder Privat (node 11) and Geteilt (node 12, public).
 *
 * PHPUnit does not inherit groups: every concrete class needs #[Group('blackbox')] itself.
 */
abstract class MediapoolTestCase extends BlackboxTestCase
{
	protected Browser $anna;

	protected function setUp(): void
	{
		parent::setUp();
		// the server keeps its storage between the tests of a class
		foreach (['originals', 'previews', 'thumbs'] as $folder)
		{
			foreach (glob($this->deployment()->directory() . '/var/storage/mediapool/' . $folder . '/*') ?: [] as $file)
				unlink($file);
		}

		$this->loadFixture('acl/users.sql');
		$this->loadFixture('mediapool/nodes.sql');

		$this->anna = $this->browser();
		$this->anna->login('anna', 'Test1234!');
	}

	/**
	 * A picture of one pixel.
	 */
	protected static function png(): string
	{
		return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
	}

	/**
	 * Another picture, so a second upload has another content.
	 */
	protected static function otherPng(): string
	{
		return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
	}

	protected function upload(string $filename, string $bytes, int $node = 8, ?Browser $as = null, bool $withToken = true, string $mime = 'image/png', string $field = 'file'): ResponseInterface
	{
		$as ??= $this->anna;
		$parts = [
			['name' => 'node_id', 'contents' => (string) $node],
			['name' => $field, 'contents' => $bytes, 'filename' => $filename, 'headers' => ['Content-Type' => $mime]],
		];
		if ($withToken)
			$parts[] = ['name' => 'csrf_token', 'contents' => $as->csrfToken('/mediapool')];

		return $as->request('POST', '/async/mediapool/uploadLocalFile', ['multipart' => $parts]);
	}

	/**
	 * An upload with a header which the client library would not send: the name of the file as raw text.
	 */
	protected function uploadRaw(string $rawFilename, string $bytes, int $node = 8): ResponseInterface
	{
		$boundary = 'blackbox' . bin2hex(random_bytes(6));
		$token    = $this->anna->csrfToken('/mediapool');
		$body     = '';
		foreach (['node_id' => (string) $node, 'csrf_token' => $token] as $name => $value)
			$body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$name\"\r\n\r\n$value\r\n";
		$body .= "--$boundary\r\nContent-Disposition: form-data; name=\"file\"; filename=\"$rawFilename\"\r\nContent-Type: image/png\r\n\r\n$bytes\r\n--$boundary--\r\n";

		return $this->anna->request('POST', '/async/mediapool/uploadLocalFile', ['body' => $body, 'headers' => ['Content-Type' => 'multipart/form-data; boundary=' . $boundary]]);
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function json(ResponseInterface $response): array
	{
		$decoded = json_decode((string) $response->getBody(), true);
		static::assertIsArray($decoded, 'No JSON: ' . substr((string) $response->getBody(), 0, 200));

		return $decoded;
	}

	/**
	 * @return list<array<string,mixed>> the media of the folder as the list gives them
	 */
	protected function mediaOf(int $node = 8): array
	{
		$list = $this->json($this->anna->get('/async/mediapool/media/list/' . $node))['media_list'] ?? [];
		static::assertIsArray($list);

		return array_values($list);
	}

	/**
	 * Uploads the picture and gives the entry of the list.
	 *
	 * @return array<string,mixed>
	 */
	protected function uploadedPicture(string $filename = 'pixel.png'): array
	{
		$answer = $this->json($this->upload($filename, self::png()));
		static::assertTrue($answer['success'] ?? false, json_encode($answer, JSON_THROW_ON_ERROR));
		foreach ($this->mediaOf() as $media)
		{
			if ($media['filename'] === $filename)
				return $media;
		}

		static::fail('The picture is not in the list.');
	}

	/**
	 * All files of the deployment which are not session, cache, log, database or temporary, relative to its directory.
	 *
	 * @return list<string>
	 */
	protected function files(): array
	{
		$root  = $this->deployment()->directory();
		$files = [];
		foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file)
		{
			$relative = substr($file->getPathname(), strlen($root) + 1);
			if (preg_match('#^(var/(sessions|cache|logs|weblogs)/|tmp/|server\.log|var/hub\.sqlite|vendor)#', $relative) === 1)
				continue;

			$files[] = $relative;
		}
		sort($files);

		return $files;
	}

	/**
	 * What appeared in the deployment since the files were counted.
	 *
	 * @param list<string> $before
	 * @return list<string>
	 */
	protected function newFilesSince(array $before): array
	{
		return array_values(array_diff($this->files(), $before));
	}

	protected function mediaCount(): int
	{
		return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM mediapool_files');
	}
}
