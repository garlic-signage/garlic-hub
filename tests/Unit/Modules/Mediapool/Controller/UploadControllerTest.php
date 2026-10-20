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

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Modules\Mediapool\Controller\UploadController;
use App\Modules\Mediapool\Helper\Uploads\Orchestrator;
use Doctrine\DBAL\Exception as DBALException;
use GuzzleHttp\Exception\GuzzleException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UploadControllerTest extends TestCase
{
	/** @var array<string,mixed> */
	private array $body = ['node_id' => 1, 'csrf_token' => 'token'];
	/** @var array<string,mixed> */
	private array $files = ['file' => 'uploaded'];
	private Orchestrator&MockObject $orchestratorMock;
	private ServerRequestInterface&Stub $requestStub;
	private ResponseInterface&Stub $responseStub;
	private ResponseInterface&Stub $expectedResponseStub;
	private UploadController $controller;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->orchestratorMock     = $this->createMock(Orchestrator::class);
		$this->requestStub          = $this->createStub(ServerRequestInterface::class);
		$this->responseStub         = $this->createStub(ResponseInterface::class);
		$this->expectedResponseStub = $this->createStub(ResponseInterface::class);

		$this->requestStub->method('getParsedBody')->willReturn($this->body);
		$this->requestStub->method('getUploadedFiles')->willReturn($this->files);

		$this->controller = new UploadController($this->orchestratorMock);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws GuzzleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testSearchStockImagesReturnsValidationError(): void
	{
		$this->orchestratorMock->expects($this->once())->method('setInput')->with($this->body)->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateSearchStockImages')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);
		$this->orchestratorMock->expects($this->never())->method('searchStockImages');

		$result = $this->controller->searchStockImages($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws GuzzleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testSearchStockImagesSucceeds(): void
	{
		$this->orchestratorMock->expects($this->once())->method('setInput')->with($this->body)->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateSearchStockImages')
			->with($this->responseStub)
			->willReturn(null);
		$this->orchestratorMock->expects($this->once())->method('searchStockImages')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);

		$result = $this->controller->searchStockImages($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws GuzzleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testUploadLocalFileReturnsValidationError(): void
	{
		$this->orchestratorMock->expects($this->once())->method('setInput')->with($this->body)->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('setUploadedFiles')->with($this->files)->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateUploadLocalFile')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);
		$this->orchestratorMock->expects($this->never())->method('uploadLocalFile');

		$result = $this->controller->uploadLocalFile($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws GuzzleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testUploadLocalFileSucceeds(): void
	{
		$this->orchestratorMock->expects($this->once())->method('setInput')->with($this->body)->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('setUploadedFiles')->with($this->files)->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateUploadLocalFile')
			->with($this->responseStub)
			->willReturn(null);
		$this->orchestratorMock->expects($this->once())->method('uploadLocalFile')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);

		$result = $this->controller->uploadLocalFile($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws GuzzleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testUploadFromUrlReturnsValidationError(): void
	{
		$this->orchestratorMock->expects($this->once())->method('setInput')->with($this->body)->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateUploadFromUrl')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);
		$this->orchestratorMock->expects($this->never())->method('uploadFromUrl');

		$result = $this->controller->uploadFromUrl($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}

	/**
	 * @throws CoreException
	 * @throws DBALException
	 * @throws FrameworkException
	 * @throws GuzzleException
	 * @throws UserException
	 */
	#[Group('units')]
	public function testUploadFromUrlSucceeds(): void
	{
		$this->orchestratorMock->expects($this->once())->method('setInput')->with($this->body)->willReturnSelf();
		$this->orchestratorMock->expects($this->once())->method('validateUploadFromUrl')
			->with($this->responseStub)
			->willReturn(null);
		$this->orchestratorMock->expects($this->once())->method('uploadFromUrl')
			->with($this->responseStub)
			->willReturn($this->expectedResponseStub);

		$result = $this->controller->uploadFromUrl($this->requestStub, $this->responseStub);

		static::assertSame($this->expectedResponseStub, $result);
	}
}
