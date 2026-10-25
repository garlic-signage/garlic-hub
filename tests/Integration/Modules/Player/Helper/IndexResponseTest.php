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

namespace Tests\Integration\Modules\Player\Helper;

use App\Framework\Exceptions\ModuleException;
use App\Modules\Player\Helper\Index\FileUtils;
use App\Modules\Player\Helper\Index\IndexResponseHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Response;

/**
 * The answer with the index file for the player: headers, the cache of the player and the answers to HEAD.
 * The file is a real file, which has a fixed time of change.
 */
#[Group('io')]
class IndexResponseTest extends TestCase
{
	private const string CONTENT = '<smil><head/><body/></smil>';
	private const int MTIME = 1_750_000_000;

	private string $directory;
	private string $file;
	private IndexResponseHandler $handler;

	protected function setUp(): void
	{
		parent::setUp();
		$this->directory = sys_get_temp_dir() . '/garlic-hub-index-' . bin2hex(random_bytes(4));
		mkdir($this->directory);
		$this->file = $this->directory . '/7.smil';
		file_put_contents($this->file, self::CONTENT);
		touch($this->file, self::MTIME);
		$this->handler = new IndexResponseHandler(new FileUtils());
	}

	protected function tearDown(): void
	{
		@unlink($this->file);
		@rmdir($this->directory);
		parent::tearDown();
	}

	/**
	 * @param array<string,string> $server
	 */
	private function init(array $server = []): void
	{
		$this->handler->init($server, $this->file);
	}

	private function etag(): string
	{
		return md5(self::CONTENT);
	}

	private function lastModified(): string
	{
		return gmdate('D, d M Y H:i:s', self::MTIME) . ' GMT';
	}

	public function testTheFileIsSentWithTheHeadersForThePlayer(): void
	{
		$this->init();

		$response = $this->handler->doGET(new Response());

		static::assertSame(200, $response->getStatusCode());
		static::assertSame(self::CONTENT, (string) $response->getBody());
		static::assertSame('application/smil+xml', $response->getHeaderLine('Content-Type'));
		static::assertSame((string) strlen(self::CONTENT), $response->getHeaderLine('Content-Length'));
		static::assertSame($this->etag(), $response->getHeaderLine('etag'));
		static::assertSame($this->lastModified(), $response->getHeaderLine('Last-Modified'));
		static::assertSame('public, must-revalidate, max-age=864000, pre-check=864000', $response->getHeaderLine('Cache-Control'));
		static::assertSame('attachment; filename="7.smil"', $response->getHeaderLine('Content-Disposition'));
	}

	/**
	 * The answer depends on who asks (the uuid is in the user agent): a cache between must know that.
	 */
	public function testEveryAnswerSaysThatItDependsOnTheAgent(): void
	{
		$this->init();
		$answers = [$this->handler->doGET(new Response()), $this->handler->doHEAD(new Response())];
		$this->init(['HTTP_IF_NONE_MATCH' => $this->etag()]);
		$answers[] = $this->handler->doGET(new Response());

		foreach ($answers as $response)
			static::assertSame('User-Agent, X-Signage-Agent', $response->getHeaderLine('Vary'));
	}

	public function testEveryAnswerCanBeReadByAnotherWebsiteWhichKnowsTheAgent(): void
	{
		$this->init();

		$response = $this->handler->doGET(new Response());

		static::assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
		static::assertSame('HEAD, GET, OPTIONS', $response->getHeaderLine('Access-Control-Allow-Methods'));
		static::assertStringContainsString('X-Signage-Agent', $response->getHeaderLine('Access-Control-Allow-Headers'));
	}

	public function testHeadHasTheHeadersButNoBody(): void
	{
		$this->init();

		$response = $this->handler->doHEAD(new Response());

		static::assertSame(200, $response->getStatusCode());
		static::assertSame('', (string) $response->getBody());
		static::assertSame($this->etag(), $response->getHeaderLine('etag'));
		static::assertSame($this->lastModified(), $response->getHeaderLine('Last-Modified'));
		static::assertSame('application/smil+xml', $response->getHeaderLine('Content-Type'));
	}

	/**
	 * @return array<string,array{array<string,string>,int}> the headers of the player and the status
	 */
	public static function conditions(): array
	{
		$etag     = md5(self::CONTENT);
		$modified = gmdate('D, d M Y H:i:s', self::MTIME) . ' GMT';
		$later    = gmdate('D, d M Y H:i:s', self::MTIME + 3600) . ' GMT';
		$earlier  = gmdate('D, d M Y H:i:s', self::MTIME - 3600) . ' GMT';

		return [
			'nothing'                        => [[], 200],
			'same etag'                      => [['HTTP_IF_NONE_MATCH' => $etag], 304],
			'other etag'                     => [['HTTP_IF_NONE_MATCH' => 'other'], 200],
			'etag with quotes'               => [['HTTP_IF_NONE_MATCH' => '"' . $etag . '"'], 200],
			'empty etag'                     => [['HTTP_IF_NONE_MATCH' => ''], 200],
			'etag as name of the header'     => [['If-None-Match' => $etag], 304],
			'date of the file'               => [['HTTP_IF_MODIFIED_SINCE' => $modified], 304],
			'later date'                     => [['HTTP_IF_MODIFIED_SINCE' => $later], 304],
			'earlier date'                   => [['HTTP_IF_MODIFIED_SINCE' => $earlier], 200],
			'date as name of the header'     => [['If-Modified-Since' => $modified], 304],
			'no date'                        => [['HTTP_IF_MODIFIED_SINCE' => 'yesterday-ish'], 200],
			'empty date'                     => [['HTTP_IF_MODIFIED_SINCE' => ''], 200],
			'etag wins, other etag, date ok' => [['HTTP_IF_NONE_MATCH' => 'other', 'HTTP_IF_MODIFIED_SINCE' => $later], 200],
			'etag wins, same etag, old date' => [['HTTP_IF_NONE_MATCH' => $etag, 'HTTP_IF_MODIFIED_SINCE' => $earlier], 304],
		];
	}

	/**
	 * The player has the file when its etag is the one of the file, or its date is the date of the file or newer.
	 * The etag decides when both come.
	 *
	 * @param array<string,string> $server
	 */
	#[DataProvider('conditions')]
	public function testThePlayerGetsTheFileOnlyWhenItHasNotTheCurrentOne(array $server, int $status): void
	{
		$this->init($server);

		foreach ([$this->handler->doGET(new Response()), $this->handler->doHEAD(new Response())] as $response)
		{
			static::assertSame($status, $response->getStatusCode());
			static::assertSame($this->etag(), $response->getHeaderLine('etag'));
			static::assertSame('public, must-revalidate, max-age=864000, pre-check=864000', $response->getHeaderLine('Cache-Control'));
			if ($status === 304)
				static::assertSame('', (string) $response->getBody());
		}
	}

	public function testAChangedFileHasANewEtag(): void
	{
		$this->init();
		$before = $this->handler->doHEAD(new Response())->getHeaderLine('etag');

		file_put_contents($this->file, self::CONTENT . '<!-- changed -->');
		$this->init(['HTTP_IF_NONE_MATCH' => $before]);
		$after = $this->handler->doGET(new Response());

		static::assertSame(200, $after->getStatusCode());
		static::assertNotSame($before, $after->getHeaderLine('etag'));
	}

	public function testAFileWhichIsNotThereIsAnError(): void
	{
		set_error_handler(static fn(): bool => true);
		try
		{
			$this->handler->init([], $this->directory . '/nothing.smil');
			static::fail('Expected a ModuleException');
		}
		catch (ModuleException $e)
		{
			static::assertStringContainsString('nothing.smil', $e->getMessage());
		}
		finally
		{
			restore_error_handler();
		}
	}

	public function testTheHelperKnowsTimeSizeStreamAndEtagOfAFile(): void
	{
		$utils = new FileUtils();

		static::assertSame(self::MTIME, $utils->getFileMTime($this->file));
		static::assertSame(strlen(self::CONTENT), $utils->getFileSize($this->file));
		static::assertSame($this->etag(), $utils->getETag($this->file));
		static::assertSame(self::CONTENT, (string) $utils->createStream($this->file));
	}

	public function testTheHelperFailsForEveryMissingFile(): void
	{
		$utils   = new FileUtils();
		$missing = $this->directory . '/nothing.smil';

		set_error_handler(static fn(): bool => true);
		try
		{
			foreach ([fn() => $utils->getFileMTime($missing), fn() => $utils->getFileSize($missing), fn() => $utils->getETag($missing), fn() => $utils->createStream($missing)] as $call)
			{
				try
				{
					$call();
					static::fail('Expected a ModuleException');
				}
				catch (ModuleException)
				{
					static::addToAssertionCount(1);
				}
			}
		}
		finally
		{
			restore_error_handler();
		}
	}
}
