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

namespace Tests\Unit\Modules\Player\Helper\Index;

use App\Framework\Exceptions\ModuleException;
use App\Modules\Player\Helper\Index\FileUtils;
use App\Modules\Player\Helper\Index\IndexResponseHandler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Stream;

class IndexResponseHandlerTest extends TestCase
{
	private ResponseInterface&MockObject $responseMock;
	private FileUtils&MockObject $fileUtilsMock;
	private IndexResponseHandler $handler;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->fileUtilsMock = $this->createMock(FileUtils::class);
		$this->responseMock  = $this->createMock(ResponseInterface::class);

		$this->handler = new IndexResponseHandler($this->fileUtilsMock);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testDoHeadWith304(): void
	{
		$filePath     = '/tmp/test.smil';
		$etag         = '"SomeMatchingEtagStuf"';
		$server       = ['HTTP_IF_NONE_MATCH' => $etag];
		$fileMTime    = 1234567890;
		$cacheControl = 'public, must-revalidate, max-age=864000, pre-check=864000';

		$this->fileUtilsMock->method('getFileMTime')->willReturn($fileMTime);
		$lastModified = gmdate('D, d M Y H:i:s', $fileMTime) . ' GMT';

		$this->fileUtilsMock->expects($this->once())->method('getETag')
			->with($filePath)
			->willReturn($etag);
		$this->fileUtilsMock->expects($this->once())->method('getFileMTime')
			->with($filePath)
			->willReturn($fileMTime);


		$this->responseMock->expects($this->exactly(7))->method('withHeader')
			->willReturnMap([
				['Cache-Control', $cacheControl, $this->responseMock],
				['etag', $etag, $this->responseMock],
				['Last-Modified', $lastModified, $this->responseMock],
				['Access-Control-Allow-Origin', '*', $this->responseMock],
				['Access-Control-Allow-Methods', 'HEAD, GET, OPTIONS', $this->responseMock],
				['Access-Control-Max-Age', '86400', $this->responseMock],
				['Access-Control-Allow-Headers', 'User-Agent, If-None-Match, If-Modified-Since, Authorization, X-Signage-Agent', $this->responseMock]
			]);
		$this->responseMock->method('withStatus')->with(304);

		$this->handler->init($server, $filePath);
		$this->handler->doHEAD($this->responseMock);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testDoHeadWith200EmptyServerData(): void
	{
		$filePath     = '/tmp/test.smil';
		$etag         = '"SomeEtagStuf"';
		$server       = [];
		$fileMTime    = 1234567890;
		$cacheControl = 'public, must-revalidate, max-age=864000, pre-check=864000';

		$this->fileUtilsMock->method('getFileMTime')->willReturn($fileMTime);
		$lastModified = gmdate('D, d M Y H:i:s', $fileMTime) . ' GMT';

		$this->fileUtilsMock->expects($this->once())->method('getETag')
			->with($filePath)
			->willReturn($etag);
		$this->fileUtilsMock->expects($this->once())->method('getFileMTime')
			->with($filePath)
			->willReturn($fileMTime);


		$this->responseMock->expects($this->exactly(8))->method('withHeader')
			->willReturnMap([
				['Cache-Control', $cacheControl, $this->responseMock],
				['Content-Type',  'application/smil+xml', $this->responseMock],
				['etag', $etag, $this->responseMock],
				['Last-Modified', $lastModified, $this->responseMock],
				['Access-Control-Allow-Origin', '*', $this->responseMock],
				['Access-Control-Allow-Methods', 'HEAD, GET, OPTIONS', $this->responseMock],
				['Access-Control-Max-Age', '86400', $this->responseMock],
				['Access-Control-Allow-Headers', 'User-Agent, If-None-Match, If-Modified-Since, Authorization, X-Signage-Agent', $this->responseMock]
			]);
		$this->responseMock->method('withStatus')->with(200);

		$this->handler->init($server, $filePath);
		$this->handler->doHEAD($this->responseMock);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testDoHeadWith200AndETag(): void
	{
		$filePath     = '/tmp/test.smil';
		$etag         = '"SomeEtagStuf"';
		$server       = ['HTTP_IF_NONE_MATCH' => 'notMatchingEtagStuff'];
		$fileMTime    = 1234567890;
		$cacheControl = 'public, must-revalidate, max-age=864000, pre-check=864000';

		$this->fileUtilsMock->method('getFileMTime')->willReturn($fileMTime);
		$lastModified = gmdate('D, d M Y H:i:s', $fileMTime) . ' GMT';

		$this->fileUtilsMock->expects($this->once())->method('getETag')
			->with($filePath)
			->willReturn($etag);
		$this->fileUtilsMock->expects($this->once())->method('getFileMTime')
			->with($filePath)
			->willReturn($fileMTime);

		$this->responseMock->expects($this->exactly(8))->method('withHeader')
			->willReturnMap([
				['Cache-Control', $cacheControl, $this->responseMock],
				['Content-Type',  'application/smil+xml', $this->responseMock],
				['etag', $etag, $this->responseMock],
				['Last-Modified', $lastModified, $this->responseMock],
				['Access-Control-Allow-Origin', '*', $this->responseMock],
				['Access-Control-Allow-Methods', 'HEAD, GET, OPTIONS', $this->responseMock],
				['Access-Control-Max-Age', '86400', $this->responseMock],
				['Access-Control-Allow-Headers', 'User-Agent, If-None-Match, If-Modified-Since, Authorization, X-Signage-Agent', $this->responseMock]

			]);
		$this->responseMock->method('withStatus')->with(200);

		$this->handler->init($server, $filePath);
		$this->handler->doHEAD($this->responseMock);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testDoHeadWith304AndETagPlusLastModified(): void
	{
		$filePath     = '/tmp/test.smil';
		$etag         = '"SomeEtagStuf"';
		$server       = ['HTTP_IF_NONE_MATCH' => $etag, 'If-Modified-Since' => 'Wed, 21 Oct 1821 07:28:00 GMT'];
		$fileMTime    = 1234567890;
		$cacheControl = 'public, must-revalidate, max-age=864000, pre-check=864000';

		$this->fileUtilsMock->method('getFileMTime')->willReturn($fileMTime);
		$lastModified = gmdate('D, d M Y H:i:s', $fileMTime) . ' GMT';

		$this->fileUtilsMock->expects($this->once())->method('getETag')
			->with($filePath)
			->willReturn($etag);
		$this->fileUtilsMock->expects($this->once())->method('getFileMTime')
			->with($filePath)
			->willReturn($fileMTime);

		$this->responseMock->expects($this->exactly(7))->method('withHeader')
			->willReturnMap([
				['Cache-Control', $cacheControl, $this->responseMock],
				['etag', $etag, $this->responseMock],
				['Last-Modified', $lastModified, $this->responseMock],
				['Access-Control-Allow-Origin', '*', $this->responseMock],
				['Access-Control-Allow-Methods', 'HEAD, GET, OPTIONS', $this->responseMock],
				['Access-Control-Max-Age', '86400', $this->responseMock],
				['Access-Control-Allow-Headers', 'User-Agent, If-None-Match, If-Modified-Since, Authorization, X-Signage-Agent', $this->responseMock]
			]);
		$this->responseMock->method('withStatus')->with(304);

		$this->handler->init($server, $filePath);
		$this->handler->doHEAD($this->responseMock);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testDoHeadWith304AndLastModifiedOnly(): void
	{
		$filePath     = '/tmp/test.smil';
		$etag         = '"SomeEtagStuf"';
		$server       = ['HTTP_IF_MODIFIED_SINCE' => 'Wed, 21 Oct 2050 07:28:00 GMT'];
		$fileMTime    = 1234567890;
		$cacheControl = 'public, must-revalidate, max-age=864000, pre-check=864000';

		$this->fileUtilsMock->method('getFileMTime')->willReturn($fileMTime);
		$lastModified = gmdate('D, d M Y H:i:s', $fileMTime) . ' GMT';

		$this->fileUtilsMock->expects($this->once())->method('getETag')
			->with($filePath)
			->willReturn($etag);
		$this->fileUtilsMock->expects($this->once())->method('getFileMTime')
			->with($filePath)
			->willReturn($fileMTime);

		$this->responseMock->expects($this->exactly(7))->method('withHeader')
			->willReturnMap([
				['Cache-Control', $cacheControl, $this->responseMock],
				['etag', $etag, $this->responseMock],
				['Last-Modified', $lastModified, $this->responseMock],
				['Access-Control-Allow-Origin', '*', $this->responseMock],
				['Access-Control-Allow-Methods', 'HEAD, GET, OPTIONS', $this->responseMock],
				['Access-Control-Max-Age', '86400', $this->responseMock],
				['Access-Control-Allow-Headers', 'User-Agent, If-None-Match, If-Modified-Since, Authorization, X-Signage-Agent', $this->responseMock]
			]);
		$this->responseMock->method('withStatus')->with(304);

		$this->handler->init($server, $filePath);
		$this->handler->doHEAD($this->responseMock);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testDoHeadWith200AndLastModifiedOnly(): void
	{
		$filePath     = '/tmp/test.smil';
		$etag         = '"SomeEtagStuf"';
		$server       = ['HTTP_IF_MODIFIED_SINCE' => 'Wed, 21 Oct 1867 07:28:00 GMT'];
		$fileMTime    = 1234567890;
		$cacheControl = 'public, must-revalidate, max-age=864000, pre-check=864000';

		$this->fileUtilsMock->method('getFileMTime')->willReturn($fileMTime);
		$lastModified = gmdate('D, d M Y H:i:s', $fileMTime) . ' GMT';

		$this->fileUtilsMock->expects($this->once())->method('getETag')
			->with($filePath)
			->willReturn($etag);
		$this->fileUtilsMock->expects($this->once())->method('getFileMTime')
			->with($filePath)
			->willReturn($fileMTime);

		$this->responseMock->expects($this->exactly(8))->method('withHeader')
			->willReturnMap([
				['Cache-Control', $cacheControl, $this->responseMock],
				['Content-Type',  'application/smil+xml', $this->responseMock],
				['etag', $etag, $this->responseMock],
				['Last-Modified', $lastModified, $this->responseMock],
				['Access-Control-Allow-Origin', '*', $this->responseMock],
				['Access-Control-Allow-Methods', 'HEAD, GET, OPTIONS', $this->responseMock],
				['Access-Control-Max-Age', '86400', $this->responseMock],
				['Access-Control-Allow-Headers', 'User-Agent, If-None-Match, If-Modified-Since, Authorization, X-Signage-Agent', $this->responseMock]
			]);
		$this->responseMock->method('withStatus')->with(200);

		$this->handler->init($server, $filePath);
		$this->handler->doHEAD($this->responseMock);
	}

	/**
	 * @throws Exception
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testDoGETWith200EmptyServerdata(): void
	{
		$filePath     = '/tmp/test.smil';
		$etag         = '"SomeEtagStuf"';
		$server       = [];
		$fileMTime    = 1234567890;
		$fileSize     = 1024;
		$cacheControl = 'public, must-revalidate, max-age=864000, pre-check=864000';

		$this->fileUtilsMock->method('getFileMTime')->willReturn($fileMTime);
		$lastModified = gmdate('D, d M Y H:i:s', $fileMTime) . ' GMT';

		$this->fileUtilsMock->expects($this->once())->method('getETag')
			->with($filePath)
			->willReturn($etag);
		$this->fileUtilsMock->expects($this->once())->method('getFileMTime')
			->with($filePath)
			->willReturn($fileMTime);
		$this->fileUtilsMock->expects($this->once())->method('getFileSize')
			->with($filePath)
			->willReturn($fileSize);

		$filestreamStub = $this->createStub(Stream::class);
		$this->fileUtilsMock->expects($this->once())->method('createStream')
			->with($filePath)
			->willReturn($filestreamStub);

		$this->responseMock->method('withBody')->with($filestreamStub)->willReturnSelf();

		$this->responseMock->expects($this->exactly(11))->method('withHeader')
			->willReturnMap([
				['Cache-Control', $cacheControl, $this->responseMock],
				['etag', $etag, $this->responseMock],
				['Last-Modified', $lastModified, $this->responseMock],
				['Access-Control-Allow-Origin', '*', $this->responseMock],
				['Access-Control-Allow-Methods', 'HEAD, GET, OPTIONS', $this->responseMock],
				['Access-Control-Max-Age', '86400', $this->responseMock],
				['Access-Control-Allow-Headers', 'User-Agent, If-None-Match, If-Modified-Since, Authorization, X-Signage-Agent', $this->responseMock],
				['Content-Length', (string) $fileSize, $this->responseMock],
				['Content-Type', 'application/smil+xml', $this->responseMock],
				['Content-Description', 'File Transfer', $this->responseMock],
				['Content-Disposition', 'attachment; filename="test.smil"', $this->responseMock]
			]);
		$this->responseMock->method('withStatus')->with(200);

		$this->handler->init($server, $filePath);
		$this->handler->doGET($this->responseMock);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testDoGETWith304(): void
	{
		$filePath     = '/tmp/test.smil';
		$etag         = '"SomeEtagStuf"';
		$server       = ['HTTP_IF_NONE_MATCH' => $etag];
		$fileMTime    = 1234567890;
		$cacheControl = 'public, must-revalidate, max-age=864000, pre-check=864000';

		$this->fileUtilsMock->method('getFileMTime')->willReturn($fileMTime);
		$lastModified = gmdate('D, d M Y H:i:s', $fileMTime) . ' GMT';

		$this->fileUtilsMock->expects($this->once())->method('getETag')
			->with($filePath)
			->willReturn($etag);
		$this->fileUtilsMock->expects($this->once())->method('getFileMTime')
			->with($filePath)
			->willReturn($fileMTime);

		$this->responseMock->expects($this->never())->method('withBody');

		$this->responseMock->expects($this->exactly(7))->method('withHeader')
			->willReturnMap([
				['Cache-Control', $cacheControl, $this->responseMock],
				['etag', $etag, $this->responseMock],
				['Last-Modified', $lastModified, $this->responseMock],
				['Access-Control-Allow-Origin', '*', $this->responseMock],
				['Access-Control-Allow-Methods', 'HEAD, GET, OPTIONS', $this->responseMock],
				['Access-Control-Max-Age', '86400', $this->responseMock],
				['Access-Control-Allow-Headers', 'User-Agent, If-None-Match, If-Modified-Since, Authorization, X-Signage-Agent', $this->responseMock]
			]);
		$this->responseMock->method('withStatus')->with(304);

		$this->handler->init($server, $filePath);
		$this->handler->doGET($this->responseMock);
	}



}
