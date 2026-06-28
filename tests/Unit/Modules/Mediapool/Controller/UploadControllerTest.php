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

namespace Tests\Unit\Modules\Mediapool\Controller;

use App\Framework\Core\CsrfToken;
use App\Framework\Core\Session;
use App\Modules\Mediapool\Controller\UploadController;
use App\Modules\Mediapool\Services\UploadService;
use GuzzleHttp\Exception\GuzzleException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Slim\Psr7\UploadedFile;

class UploadControllerTest extends TestCase
{
	private ServerRequestInterface&MockObject $requestMock;
	private ResponseInterface&MockObject $responseMock;
	private UploadService&MockObject $uploadServiceMock;
	private CsrfToken&MockObject $csrfTokenMock;
	private UploadController $controller;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->requestMock      = $this->createMock(ServerRequestInterface::class);
		$this->responseMock     = $this->createMock(ResponseInterface::class);
		$this->uploadServiceMock = $this->createMock(UploadService::class);
		$this->csrfTokenMock    = $this->createMock(CsrfToken::class);
		$this->controller       = new UploadController($this->uploadServiceMock, $this->csrfTokenMock);
	}

	/**
	 * @throws GuzzleException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testSearchStockImages(): void
	{
		$bodyParams = ['api_url' => 'https://api.unsplash.com', 'headers' => []];
		$this->requestMock->method('getParsedBody')->willReturn($bodyParams);
		$this->uploadServiceMock->method('requestApi')->with($bodyParams['api_url'])->willReturn(['response_body']);

		$this->mockResponse(['success' => true, 'data' => ['response_body']]);

		$this->controller->searchStockImages($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws GuzzleException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testSearchStockImagesFails(): void
	{
		$bodyParams = ['api_url' => 'https://api.notallowed.com', 'headers' => []];
		$this->requestMock->method('getParsedBody')->willReturn($bodyParams);
		$this->uploadServiceMock->expects($this->never())->method('requestApi');

		$this->mockResponse(['success' => false, 'error_message' => 'Host not allowed.']);

		$this->controller->searchStockImages($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUploadLocalFile(): void
	{
		$uploadedFile = ['file' => $this->createMock(UploadedFile::class)];
		$bodyParams   = ['node_id' => 1, 'metadata' => json_encode([])];
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);
		$this->requestMock->method('getUploadedFiles')->willReturn($uploadedFile);

		$this->requestMock->method('getParsedBody')->willReturn($bodyParams);
		$this->mockSession();
		$this->uploadServiceMock->method('uploadMediaFiles')->willReturn([['success' => true]]);

		$this->mockResponse(['success' => true]);
		$this->controller->uploadLocalFile($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUploadNoUploadFile(): void
	{
		$this->requestMock->method('getUploadedFiles')->willReturn([]);

		$this->requestMock->expects($this->never())->method('getParsedBody');

		$this->uploadServiceMock->expects($this->never())->method('uploadMediaFiles');

		$this->mockResponse(['success' => false, 'error_message' => 'No files to upload.']);

		$this->controller->uploadLocalFile($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUploadNoNodeId(): void
	{
		$uploadedFile = ['file' => $this->createMock(UploadedFile::class)];
		$this->requestMock->method('getUploadedFiles')->willReturn($uploadedFile);

		$this->requestMock->method('getParsedBody')->willReturn([]);
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);

		$this->uploadServiceMock->expects($this->never())->method('uploadMediaFiles');

		$this->mockResponse(['success' => false, 'error_message' => 'node is missing']);

		$this->controller->uploadLocalFile($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testUploadNoFile(): void
	{
		$uploadedFile = ['file' => null];
		$bodyParams   = ['node_id' => 1, 'metadata' => json_encode([])];
		$this->requestMock->method('getUploadedFiles')->willReturn($uploadedFile);

		$this->requestMock->method('getParsedBody')->willReturn($bodyParams);
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);

		$this->uploadServiceMock->expects($this->never())->method('uploadMediaFiles');

		$this->mockResponse(['success' => false, 'error_message' => 'no files']);

		$this->controller->uploadLocalFile($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUploadFromUrl(): void
	{
		$bodyParams = ['node_id' => 1, 'external_link' => 'https://example.com/file', 'metadata' => json_encode(['duration' => 16])];
		$this->requestMock->method('getParsedBody')->willReturn($bodyParams);
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);
		$this->mockSession();
		$this->uploadServiceMock->method('uploadExternalMedia')->willReturn(['success' => true]);

		$this->mockResponse(['success' => true]);

		$this->controller->uploadFromUrl($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUploadFromUrlFailsNoNodeId(): void
	{
		$bodyParams = ['external_link' => 'https://example.com/file'];
		$this->requestMock->method('getParsedBody')->willReturn($bodyParams);
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);
		$this->uploadServiceMock->expects($this->never())->method('uploadExternalMedia');

		$this->mockResponse(['success' => false, 'error_message' => 'node is missing']);

		$this->controller->uploadFromUrl($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUploadFromUrlFailsNoExternalLink(): void
	{
		$bodyParams = ['node_id' => 1];
		$this->requestMock->method('getParsedBody')->willReturn($bodyParams);
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);
		$this->uploadServiceMock->expects($this->never())->method('uploadExternalMedia');

		$this->mockResponse(['success' => false, 'error_message' => 'No external link submitted.']);

		$this->controller->uploadFromUrl($this->requestMock, $this->responseMock);
	}


	/**
	 * @throws Exception
	 */
	private function mockSession(): void
	{
		$sessionMock = $this->createMock(Session::class);
		$this->requestMock ->expects($this->once())
			->method('getAttribute')
			->with('session')
			->willReturn($sessionMock);

		$sessionMock->method('get')->with('user')->willReturn(['UID' => 1]);
	}

	/**
	 * @param array<string,mixed> $data
	 * @throws Exception
	 */
	private function mockResponse(array $data): void
	{
		$streamInterfaceMock = $this->createMock(StreamInterface::class);
		$this->responseMock->expects($this->once())
			->method('getBody')
			->willReturn($streamInterfaceMock);

		$streamInterfaceMock->expects($this->once())
			->method('write')
			->with(json_encode($data));

		$this->responseMock ->expects($this->once())
			->method('withHeader')
			->with('Content-Type', 'application/json')
			->willReturnSelf();

		$this->responseMock ->expects($this->once())
			->method('withStatus')
			->with(200)
			->willReturnSelf();
	}

}
