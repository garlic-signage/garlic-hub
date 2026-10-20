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

namespace Tests\Unit\Modules\Playlists\Collector;

use App\Framework\Exceptions\ModuleException;
use App\Modules\Playlists\Collector\ExternalContentReader;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class ExternalContentReaderTest extends TestCase
{
	private FileSystem&MockObject $fileSystemMock;
	private Client&MockObject $clientMock;
	private ExternalContentReader $reader;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->fileSystemMock = $this->createMock(Filesystem::class);
		$this->clientMock = $this->createMock(Client::class);

		$this->reader = new ExternalContentReader($this->fileSystemMock, $this->clientMock, 'path/to/cache');
	}

	/**
	 * @throws ModuleException
	 * @throws Exception
	 * @throws GuzzleException
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testLoadPlaylistItemsWithoutCachedFile(): void
	{
		$this->fileSystemMock->expects($this->once())
			->method('fileExists')
			->with('path/to/cache/' . md5('playlist/url') . '.smil')
			->willReturn(false);

		$responseMock = $this->createMock(ResponseInterface::class);
		$this->clientMock->expects($this->once())
			->method('head')
			->with('playlist/url')
			->willReturn($responseMock);

		$responseMock->expects($this->once())->method('getStatusCode')->willReturn(200);

		$this->clientMock
			->expects($this->once())
			->method('get')
			->with('playlist/url', ['sink' => 'path/to/cache/' . md5('playlist/url') . '.smil']);

		$this->fileSystemMock
			->expects($this->once())
			->method('read')
			->with('path/to/cache/' . md5('playlist/url') . '.smil')
			->willReturn('file_content');

		$this->reader->init('playlist/url');
		static::assertSame('file_content', $this->reader->loadPlaylistItems());
	}

	/**
	 * @throws ModuleException
	 * @throws Exception
	 * @throws GuzzleException
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testLoadPlaylistItemsWithExistingOutdatedCachedFile(): void
	{
		$this->fileSystemMock
			->expects($this->once())
			->method('fileExists')
			->with('path/to/cache/' . md5('playlist/url') . '.smil')
			->willReturn(true);

		$this->fileSystemMock
			->expects($this->once())
			->method('read')
			->with('path/to/cache/' . md5('playlist/url') . '.smil')
			->willReturn('existing_file_content');

		$responseMock = $this->createMock(ResponseInterface::class);
		$this->clientMock->expects($this->once())
			->method('head')
			->with('playlist/url')
			->willReturn($responseMock);

		$responseMock->expects($this->once())->method('getStatusCode')->willReturn(200);

		///$time = strtotime('Sun, 4 May 2025 11:21:36 GMT');
		$responseMock->expects($this->exactly(2))->method('getHeaderLine')
			->willReturnMap([
				['Last-Modified', 'Sun, 4 May 2025 11:21:36 GMT'],
				['Content-Length', '14'],
			]);
		$this->fileSystemMock->method('lastModified')->willReturn(14567892);
		$this->fileSystemMock->method('fileSize')->willReturn(112);

		$this->clientMock
			->expects($this->once())
			->method('get')
			->with('playlist/url', ['sink' => 'path/to/cache/' . md5('playlist/url') . '.smil']);

		$this->reader->init('playlist/url');
		static::assertSame('existing_file_content', $this->reader->loadPlaylistItems());
	}

	/**
	 * @throws ModuleException
	 * @throws Exception
	 * @throws GuzzleException
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testLoadPlaylistItemsWithExistingCurrentCachedFile(): void
	{
		$this->fileSystemMock
			->expects($this->once())
			->method('fileExists')
			->with('path/to/cache/' . md5('playlist/url') . '.smil')
			->willReturn(true);

		$this->fileSystemMock
			->expects($this->once())
			->method('read')
			->with('path/to/cache/' . md5('playlist/url') . '.smil')
			->willReturn('existing_file_content');

		$responseMock = $this->createMock(ResponseInterface::class);
		$this->clientMock->expects($this->once())
			->method('head')
			->with('playlist/url')
			->willReturn($responseMock);

		$responseMock->expects($this->once())->method('getStatusCode')->willReturn(200);

		$responseMock->expects($this->exactly(2))->method('getHeaderLine')
			->willReturnMap([
				['Last-Modified', 'Sun, 4 May 2025 11:21:36 GMT'],
				['Content-Length', '1440'],
			]);

		$time = strtotime('Sun, 4 May 2025 11:21:36 GMT');

		$this->fileSystemMock->method('lastModified')->willReturn($time);
		$this->fileSystemMock->method('fileSize')->willReturn(1440);
		$this->clientMock->expects($this->never())->method('get');


		$this->reader->init('playlist/url');
		static::assertSame('existing_file_content', $this->reader->loadPlaylistItems());
	}


	/**
	 * @throws FilesystemException
	 * @throws GuzzleException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testLoadPlaylistItemsWhenStatusCodeNot200(): void
	{
		$this->fileSystemMock
			->expects($this->once())
			->method('fileExists')
			->with('path/to/cache/' . md5('playlist/url') . '.smil')
			->willReturn(false);


		$responseMock = $this->createStub(ResponseInterface::class);
		$this->clientMock->expects($this->once())
			->method('head')
			->with('playlist/url')
			->willReturn($responseMock);

		$responseMock->method('getStatusCode')->willReturn(300);

		$this->expectException(ModuleException::class);

		$this->reader->init('playlist/url');
		$this->reader->loadPlaylistItems();
	}

}
