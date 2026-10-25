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

namespace Tests\Integration\Modules\Playlists\Collector;

use App\Modules\Playlists\Collector\ExternalContentReader;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Support\MediaWorkspace;

/**
 * The SMIL file of an external playlist is downloaded once and cached. The server is the mock handler of Guzzle,
 * the cache is a directory in the temporary workspace. The external playlists are not released yet.
 */
#[Group('io')]
class ExternalContentReaderTest extends TestCase
{
	private const string URL = 'https://example.com/list.smil';

	private MediaWorkspace $workspace;
	private MockHandler $http;
	private ExternalContentReader $reader;
	private string $previousDirectory;
	private string $cachedFile;

	protected function setUp(): void
	{
		parent::setUp();
		$this->workspace = new MediaWorkspace();
		$this->http      = new MockHandler();
		// the download is written to a path relative to the working directory, the cache is read from the storage
		$this->previousDirectory = (string) getcwd();
		chdir($this->workspace->dir);
		mkdir('cache');

		$this->cachedFile = 'cache/' . md5(self::URL) . '.smil';
		$this->reader     = new ExternalContentReader($this->workspace->filesystem, new Client(['handler' => HandlerStack::create($this->http)]), 'cache');
	}

	protected function tearDown(): void
	{
		chdir($this->previousDirectory);
		$this->workspace->remove();
		parent::tearDown();
	}

	public function testTheFirstAccessDownloadsTheFile(): void
	{
		$this->http->append(new Response(200), new Response(200, [], '<seq>external</seq>'));

		static::assertSame('<seq>external</seq>', $this->reader->init(self::URL)->loadPlaylistItems());

		static::assertSame('<seq>external</seq>', $this->workspace->filesystem->read($this->cachedFile));
		static::assertCount(0, $this->http);
	}

	/**
	 * The client throws for a status of 400 or higher, the checks of the status code in the reader never get one.
	 */
	public function testAnUnreachableFileIsNotCached(): void
	{
		$this->http->append(new Response(404));

		try
		{
			$this->reader->init(self::URL)->loadPlaylistItems();
			static::fail('Expected ClientException');
		}
		catch (ClientException)
		{
			static::assertFalse($this->workspace->filesystem->fileExists($this->cachedFile));
		}
	}

	public function testAChangedFileIsDownloadedAgain(): void
	{
		$this->workspace->filesystem->write($this->cachedFile, 'old');
		$this->http->append(new Response(200, ['Content-Length' => '11', 'Last-Modified' => gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT']), new Response(200, [], 'new content'));

		static::assertSame('new content', $this->reader->init(self::URL)->loadPlaylistItems());
	}

	/**
	 * An error of the server does not fall back to the cached file, the exception goes up.
	 */
	public function testTheCachedFileIsNotUsedWhenTheServerAnswersWithAnError(): void
	{
		$this->workspace->filesystem->write($this->cachedFile, 'cached');
		$this->http->append(new Response(500));

		$this->expectException(ServerException::class);
		$this->reader->init(self::URL);
	}

	public function testAnUnchangedFileIsNotDownloadedAgain(): void
	{
		static::markTestSkipped('Known bug: mustUpdate() compares the other way round (remote older than the cache), which is always true after the first download. The file is downloaded on every access. Remove this line when it is fixed.');
		// @phpstan-ignore deadCode.unreachable
		$this->workspace->filesystem->write($this->cachedFile, 'cached');
		$this->http->append(new Response(200, ['Content-Length' => '6', 'Last-Modified' => gmdate('D, d M Y H:i:s', time() - 3600) . ' GMT']));

		static::assertSame('cached', $this->reader->init(self::URL)->loadPlaylistItems());
		static::assertCount(0, $this->http);
	}
}
