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
use App\Modules\Mediapool\Controller\MediaController;
use App\Modules\Mediapool\Services\MediaService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;

class MediaControllerTest extends TestCase
{
	private ServerRequestInterface&MockObject $requestMock;
	private ResponseInterface&MockObject $responseMock;
	private MediaService&MockObject $mediaServiceMock;
	private CsrfToken&MockObject $csrfTokenMock;
	private MediaController $controller;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->requestMock      = $this->createMock(ServerRequestInterface::class);
		$this->responseMock     = $this->createMock(ResponseInterface::class);
		$this->mediaServiceMock = $this->createMock(MediaService::class);
		$this->csrfTokenMock    = $this->createMock(CsrfToken::class);
		$this->controller       = new MediaController($this->mediaServiceMock, $this->csrfTokenMock);

	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 * @throws Exception
	 */
	#[Group('units')]
	public function testListNoNodeId(): void
	{
		$this->requestMock->expects($this->never())->method('getAttribute');

		$this->mockResponse(['success' => false, 'error_message' => 'node is missing']);

		$this->controller->list($this->requestMock , $this->responseMock , []);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 * @throws Exception
	 */
	#[Group('units')]
	public function testListSucceed(): void
	{
		$this->mockSession();

		$this->mediaServiceMock->expects($this->once())
			->method('setUID')
			->with(1);

		$this->mediaServiceMock->expects($this->once())
			->method('listMedia')
			->with(2, '')
			->willReturn(['media1', 'media2']);

		$this->mockResponse(['success' => true, 'media_list' => ['media1', 'media2']]);

		$this->controller->list($this->requestMock , $this->responseMock , ['node_id' => 2]);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testGetInfoNoMedia(): void
	{
		$this->requestMock ->expects($this->never())->method('getAttribute');

		$this->mockResponse(['success' => false, 'error_message' => 'media_id is missing']);

		$this->controller->getInfo($this->requestMock , $this->responseMock , []);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testGetInfoSucceed(): void
	{
		$this->mockSession();

		$this->mediaServiceMock ->expects($this->once())
			->method('setUID')
			->with(1);

		$this->mediaServiceMock ->expects($this->once())
			->method('fetchMedia')
			->with('1')
			->willReturn(['media' => 'data']);

		$this->mockResponse(['success' => true, 'media' => ['media' => 'data']]);

		$this->controller->getInfo($this->requestMock , $this->responseMock , ['media_id' => '1']);
	}

	/**
	 * @return void
	 * @throws Exception
	 */
	#[Group('units')]
	public function testEditNoCsrfToken(): void
	{
		$this->requestMock->expects($this->once())
			->method('getParsedBody')
			->willReturn(['filename' => 'test.jpg', 'description' => 'Test description']);

		$this->mockResponse(['success' => false, 'error_message' => 'Csrf token mismatch.']);

		$this->controller->edit($this->requestMock, $this->responseMock);
	}


	/**
	 * @return void
	 * @throws Exception
	 */
	#[Group('units')]
	public function testEditNoMediaId(): void
	{
		$this->requestMock->expects($this->once())
			->method('getParsedBody')
			->willReturn(['csrf_token' => 'token', 'filename' => 'test.jpg', 'description' => 'Test description']);

		$this->csrfTokenMock->expects($this->once())->method('validateToken')->with('token')->willReturn(true);
		$this->mockResponse(['success' => false, 'error_message' => 'Media id is missing']);

		$this->controller->edit($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testEditNoFilename(): void
	{
		$this->requestMock->expects($this->once())
			->method('getParsedBody')
			->willReturn(['media_id' => 1, 'description' => 'Test description']);

		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);
		$this->mockResponse(['success' => false, 'error_message' => 'Filename is missing']);

		$this->controller->edit($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testEditNoDescription(): void
	{
		$this->requestMock->expects($this->once())
			->method('getParsedBody')
			->willReturn(['media_id' => 1, 'filename' => 'test.jpg']);

		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);
		$this->mockResponse(['success' => false, 'error_message' => 'Description is missing']);

		$this->controller->edit($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testEditSucceed(): void
	{
		$this->requestMock->expects($this->once())
			->method('getParsedBody')
			->willReturn(['media_id' => 'a_media_id', 'filename' => 'test.jpg', 'description' => 'Test description']);
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);

		$this->mockSession();

		$this->mediaServiceMock ->expects($this->once())
			->method('setUID')
			->with(1);

		$this->mediaServiceMock->expects($this->once())
			->method('updateMedia')
			->with('a_media_id', 'test.jpg', 'Test description');

		$this->mockResponse(['success' => true]);

		$this->controller->edit($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception|Exception
	 */
	#[Group('units')]
	public function testDeleteNoMediaId(): void
	{
		$this->requestMock->expects($this->once())
			->method('getParsedBody')
			->willReturn([]);
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);

		$this->mockResponse(['success' => false, 'error_message' => 'media id is missing']);

		$this->controller->delete($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 * @throws Exception
	 */
	#[Group('units')]
	public function testDeleteSucceed(): void
	{
		$this->requestMock->expects($this->once())
			->method('getParsedBody')
			->willReturn(['media_id' => '1']);
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);

		$this->mediaServiceMock->expects($this->once())
			->method('setUID')
			->with(1);

		$this->mockSession();

		$this->mediaServiceMock->expects($this->once())
			->method('deleteMedia')
			->with('1')
			->willReturn(1);

		$this->mockResponse(['success' => true, 'data' => ['deleted_media' => 1]]);

		$this->controller->delete($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 * @throws Exception
	 */
	#[Group('units')]
	public function testMoveFails(): void
	{
		$this->requestMock->expects($this->once())
			->method('getParsedBody')
			->willReturn(['media_id' => 1]);
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);

		$this->mockResponse(['success' => false, 'error_message' => 'media id or node is missing']);

		$this->controller->move($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 * @throws Exception
	 */
	#[Group('units')]
	public function testMoveSucceed(): void
	{
		$this->requestMock->expects($this->once())
			->method('getParsedBody')
			->willReturn(['media_id' => '1', 'node_id' => 2]);
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);

		$this->mockSession();
		$this->mediaServiceMock->expects($this->once())
			->method('setUID')
			->with(1);

		$this->mediaServiceMock->expects($this->once())
			->method('moveMedia')
			->with('1', 2)
			->willReturn(1);

		$this->mockResponse(['success' => true, 'data' => ['deleted_media' => 1]]);

		$this->controller->move($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 * @throws Exception
	 */
	#[Group('units')]
	public function testCloneNoMediaId(): void
	{
		$this->requestMock->expects($this->once())
			->method('getParsedBody')
			->willReturn([]);
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);

		$this->mockResponse(['success' => false, 'error_message' => 'media id is missing']);

		$this->controller->clone($this->requestMock, $this->responseMock);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 * @throws Exception
	 */
	#[Group('units')]
	public function testCloneSucceed(): void
	{

		$this->requestMock->expects($this->once())
			->method('getParsedBody')
			->willReturn(['media_id' => 'a_media_id']);
		$this->csrfTokenMock->expects($this->once())->method('validateToken')->willReturn(true);

		$this->mockSession();
		$this->mediaServiceMock->expects($this->once())
			->method('setUID')
			->with(1);

		$this->mediaServiceMock->expects($this->once())
			->method('cloneMedia')
			->with('a_media_id')
			->willReturn(['new_media' => 'data']);

		$this->mockResponse(['success' => true, 'new_media' => ['new_media' => 'data']]);

		$this->controller->clone($this->requestMock, $this->responseMock);
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

		$this->responseMock->expects($this->once())
			->method('withHeader')
			->with('Content-Type', 'application/json')
			->willReturnSelf();

		$this->responseMock ->expects($this->once())
			->method('withStatus')
			->with(200)
			->willReturnSelf();
	}
}
