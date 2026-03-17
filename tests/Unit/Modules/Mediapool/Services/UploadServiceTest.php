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

namespace Tests\Unit\Modules\Mediapool\Services;

use App\Framework\Media\MimeTypeService;
use App\Modules\Mediapool\Repositories\FilesRepository;
use App\Modules\Mediapool\Services\NodesService;
use App\Modules\Mediapool\Services\UploadService;
use App\Modules\Mediapool\Utils\AbstractMediaHandler;
use App\Modules\Mediapool\Utils\MediaHandlerFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Stream;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\UploadedFile;

class UploadServiceTest extends TestCase
{
	private UploadService $uploadService;
	private MediaHandlerFactory&MockObject $mediaHandlerFactoryMock;
	private NodesService&MockObject $nodeServiceMock;
	private Client&MockObject $clientMock;
	private FilesRepository&MockObject $mediaRepositoryMock;
	private MimeTypeService&MockObject $mimeTypeService;
	private LoggerInterface&MockObject $loggerMock;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->mediaHandlerFactoryMock = $this->createMock(MediaHandlerFactory::class);
		$this->nodeServiceMock = $this->createMock(NodesService::class);
		$this->clientMock = $this->createMock(Client::class);
		$this->mediaRepositoryMock = $this->createMock(FilesRepository::class);
		$this->mimeTypeService = $this->createMock(MimeTypeService::class);
		$this->loggerMock = $this->createMock(LoggerInterface::class);

		$this->uploadService = new UploadService(
			$this->mediaHandlerFactoryMock,
			$this->nodeServiceMock,
			$this->clientMock,
			$this->mediaRepositoryMock,
			$this->mimeTypeService,
			$this->loggerMock
		);
	}

	/**
	 * @throws GuzzleException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testRequestApiSuccess(): void
	{
		$expectedData = ['key' => 'value'];
		$responseMock = $this->createMock(ResponseInterface::class);

		$this->clientMock
			->method('request')
			->with('GET', 'https://api.example.com')
			->willReturn($responseMock);

		$resource = fopen('php://temp', 'r+');
		static::assertNotFalse($resource, 'Failed to resource for testing.');
		$str = json_encode($expectedData);
		static::assertNotFalse($str, 'Failed to json for testing.');
		$i = fwrite($resource, $str);
		static::assertNotFalse($i, 'Failed to resource for testing.');

		rewind($resource);

		$stream = new Stream($resource);
		$responseMock->method('getBody')->willReturn($stream);

		$result = $this->uploadService->requestApi('https://api.example.com');
		static::assertEquals($expectedData, $result);
	}

	/**
	 * @throws GuzzleException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testRequestApiSuccessWithHeaders(): void
	{
		$expectedData = ['key' => 'value'];
		$responseMock = $this->createMock(ResponseInterface::class);

		$options = ['headers' => ['Auth' => 'Authorization: Bearer']];
		$this->clientMock
			->method('request')
			->with('GET', 'https://api.example.com', $options)
			->willReturn($responseMock);

		$resource = fopen('php://temp', 'r+');
		static::assertNotFalse($resource, 'Failed to resource for testing.');

		$str = json_encode($expectedData);
		static::assertNotFalse($str, 'Failed to json for testing.');
		$i = fwrite($resource, $str);
		static::assertNotFalse($i, 'Failed to resource for testing.');
		rewind($resource);

		$stream = new Stream($resource);
		$responseMock->method('getBody')->willReturn($stream);

		$result = $this->uploadService->requestApi('https://api.example.com', ['Auth' => 'Authorization: Bearer']);
		static::assertEquals($expectedData, $result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUploadMediaFilesSuccess(): void
	{
		$nodeId = 1;
		$uid = 1;
		$metadata = ['description' => 'Test file'];

		$uploadedFile = $this->createMock(UploadedFile::class);
		$mediaHandler = $this->createMock(AbstractMediaHandler::class);
		$this->nodeServiceMock->method('isNodeUploadable')->willReturn(true);

		// Mock uploaded file methods
		$uploadedFile->method('getError')
			->willReturn(UPLOAD_ERR_OK);
		$uploadedFile->method('getClientMediaType')
			->willReturn('image/jpeg');
		$uploadedFile->method('getSize')
			->willReturn(1024);

		// Mock media handler
		$this->mediaHandlerFactoryMock->expects($this->once())->method('createMediaHandler')
			->with('image/jpeg')
			->willReturn($mediaHandler);

		$mediaHandler->expects($this->once())->method('uploadFromLocal')
			->willReturn('/tmp/test.jpg');

		$mediaHandler->method('determineNewFilename')
			->willReturn('abc123');

		$mediaHandler->method('getMetadata')
			->willReturn([]);

		$this->mediaRepositoryMock->method('findFirstBy')
			->willReturn([]);

		$this->mimeTypeService
			->method('detectFromFile')
			->willReturn('image/jpeg');

		$result = $this->uploadService->uploadMediaFiles($nodeId, $uid, $uploadedFile, $metadata);

		static::assertTrue($result[0]['success']);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUploadMediaFilesSuccessWithMetaDataDuration(): void
	{
		$nodeId = 1;
		$uid = 1;

		$uploadedFileMock = $this->createMock(UploadedFile::class);
		$mediaHandlerMock = $this->createMock(AbstractMediaHandler::class);
		$this->nodeServiceMock->method('isNodeUploadable')->willReturn(true);

		// Mock uploaded file methods
		$uploadedFileMock->method('getError')->willReturn(UPLOAD_ERR_OK);
		$uploadedFileMock->method('getClientMediaType')->willReturn('video/mp4');
		$uploadedFileMock->method('getSize')->willReturn(1024);

		// Mock media handler
		$this->mediaHandlerFactoryMock->expects($this->once())->method('createMediaHandler')
			->with('video/mp4')
			->willReturn($mediaHandlerMock);

		$mediaHandlerMock->expects($this->once())->method('uploadFromLocal')
			->willReturn('/tmp/video.mp4');

		$mediaHandlerMock->method('determineNewFilename')->willReturn('abc123');

		$mediaHandlerMock->method('getMetadata')->willReturn(['title' => 'Test video']);

		$this->mediaRepositoryMock->method('findFirstBy')->willReturn([]);

		$this->mimeTypeService->method('detectFromFile')->willReturn('video/mp4');

		$extMetadata = ['duration' => 4050, 'description' => 'Test file'];
		$result = $this->uploadService->uploadMediaFiles($nodeId, $uid, $uploadedFileMock, $extMetadata);

		static::assertTrue($result[0]['success']);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUploadMediaFilesWithErrorNull(): void
	{
		$uploadedFile = $this->createMock(UploadedFile::class);
		$this->nodeServiceMock->method('isNodeUploadable')->willReturn(true);
		$uploadedFile->method('getClientMediaType')->willReturn(null);
		$this->loggerMock->expects($this->once())->method('error');
		$result = $this->uploadService->uploadMediaFiles(1, 1, $uploadedFile, []);

		static::assertFalse($result[0]['success']);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUploadMediaFilesWithErrorIniSize(): void
	{
		$uploadedFile = $this->createMock(UploadedFile::class);
		$uploadedFile->method('getError')->willReturn(UPLOAD_ERR_INI_SIZE);
		$uploadedFile->method('getClientMediaType')->willReturn('image/jpeg');
		$this->loggerMock->expects($this->once())->method('error');
		$result = $this->uploadService->uploadMediaFiles(1, 1, $uploadedFile, []);

		static::assertFalse($result[0]['success']);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUploadMediaFilesWithErrorFormSize(): void
	{
		$uploadedFile = $this->createMock(UploadedFile::class);
		$uploadedFile->method('getError')->willReturn(UPLOAD_ERR_FORM_SIZE);
		$uploadedFile->method('getClientMediaType')->willReturn('image/jpeg');
		$this->loggerMock->expects($this->once())->method('error');
		$result = $this->uploadService->uploadMediaFiles(1, 1, $uploadedFile, []);

		static::assertFalse($result[0]['success']);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUploadMediaFilesWithErrorPartial(): void
	{
		$uploadedFile = $this->createMock(UploadedFile::class);
		$uploadedFile->method('getError')->willReturn(UPLOAD_ERR_PARTIAL);
		$uploadedFile->method('getClientMediaType')->willReturn('image/jpeg');
		$this->loggerMock->expects($this->once())->method('error');
		$result = $this->uploadService->uploadMediaFiles(1, 1, $uploadedFile, []);

		static::assertFalse($result[0]['success']);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUploadMediaFilesWithErrorNoFile(): void
	{
		$uploadedFile = $this->createMock(UploadedFile::class);
		$uploadedFile->method('getError')->willReturn(UPLOAD_ERR_NO_FILE);
		$uploadedFile->method('getClientMediaType')->willReturn('image/jpeg');
		$this->loggerMock->expects($this->once())->method('error');
		$result = $this->uploadService->uploadMediaFiles(1, 1, $uploadedFile, []);

		static::assertFalse($result[0]['success']);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUploadMediaFilesWithErrorNoFTmpDir(): void
	{
		$uploadedFile = $this->createMock(UploadedFile::class);
		$uploadedFile->method('getError')->willReturn(UPLOAD_ERR_NO_TMP_DIR);
		$uploadedFile->method('getClientMediaType')->willReturn('image/jpeg');
		$this->loggerMock->expects($this->once())->method('error');
		$result = $this->uploadService->uploadMediaFiles(1, 1, $uploadedFile, []);

		static::assertFalse($result[0]['success']);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUploadMediaFilesWithErrorCantWrite(): void
	{
		$uploadedFile = $this->createMock(UploadedFile::class);
		$uploadedFile->method('getError')->willReturn(UPLOAD_ERR_CANT_WRITE);
		$uploadedFile->method('getClientMediaType')->willReturn('image/jpeg');
		$this->loggerMock->expects($this->once())->method('error');
		$result = $this->uploadService->uploadMediaFiles(1, 1, $uploadedFile, []);

		static::assertFalse($result[0]['success']);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUploadMediaFilesWithErrorByExtension(): void
	{
		$uploadedFile = $this->createMock(UploadedFile::class);
		$uploadedFile->method('getError')->willReturn(UPLOAD_ERR_EXTENSION);
		$uploadedFile->method('getClientMediaType')->willReturn('image/jpeg');
		$this->loggerMock->expects($this->once())->method('error');
		$result = $this->uploadService->uploadMediaFiles(1, 1, $uploadedFile, []);

		static::assertFalse($result[0]['success']);
	}


	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUploadMediaFilesWithErrorGetSizeNull(): void
	{
		$uploadedFileMock = $this->createMock(UploadedFile::class);
		$this->nodeServiceMock->method('isNodeUploadable')->willReturn(true);
		$uploadedFileMock->method('getClientMediaType')->willReturn('video/mp4');
		$uploadedFileMock->method('getError')->willReturn(UPLOAD_ERR_OK);

		$mediaHandlerMock = $this->createMock(AbstractMediaHandler::class);
		$this->mediaHandlerFactoryMock->expects($this->once())->method('createMediaHandler')
			->with('video/mp4')
			->willReturn($mediaHandlerMock);

		$uploadedFileMock->method('getSize')->willReturn(null);
		$this->loggerMock->expects($this->once())->method('error')
			->with('UploadService Error: Not able to detect size.');

		$expected = ['success' => false, 'error_message' => 'Not able to detect size.'];
		$result = $this->uploadService->uploadMediaFiles(1, 1, $uploadedFileMock, []);

		static::assertSame($expected, $result[0]);
	}


	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUploadExternalMediaSuccess(): void
	{
		$nodeId = 1;
		$uid = 1;
		$externalLink = 'https://example.com/image.jpg';
		$extMetadata = [];

		$responseMock = $this->createMock(ResponseInterface::class);
		$mediaHandler = $this->createMock(AbstractMediaHandler::class);
		$this->nodeServiceMock->method('isNodeUploadable')->willReturn(true);

		$responseMock->method('getHeaderLine')
			->willReturnOnConsecutiveCalls('image/jpeg', '1234');

		$this->clientMock->method('head')->willReturn($responseMock);
		$this->mediaHandlerFactoryMock->method('createMediaHandler')->willReturn($mediaHandler);
		$mediaHandler->method('uploadFromExternal')->willReturn('/tmp/test.jpg');
		$mediaHandler->method('determineNewFilename')->willReturn('abc123');

		$this->mediaRepositoryMock->method('findFirstBy')->willReturn([]);

		$result = $this->uploadService->uploadExternalMedia($nodeId, $uid, $externalLink, $extMetadata);

		static::assertTrue($result['success']);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUploadExternalMediaSuccessWithMetadata(): void
	{
		$nodeId = 1;
		$uid = 1;
		$externalLink = 'https://example.com/image.jpg';
		$extMetadata = ['title' => 'a title', 'description' => 'Test file'];
		$this->nodeServiceMock->method('isNodeUploadable')->willReturn(true);

		$responseMock = $this->createMock(ResponseInterface::class);
		$mediaHandler = $this->createMock(AbstractMediaHandler::class);

		$responseMock->method('getHeaderLine')
			->willReturnOnConsecutiveCalls('image/jpeg', '1234');

		$this->clientMock->method('head')->willReturn($responseMock);
		$this->mediaHandlerFactoryMock->method('createMediaHandler')->willReturn($mediaHandler);
		$mediaHandler->method('uploadFromExternal')->willReturn('/tmp/test.jpg');
		$mediaHandler->method('determineNewFilename')->willReturn('abc123');

		$this->mediaRepositoryMock->method('findFirstBy')->willReturn([]);

		$result = $this->uploadService->uploadExternalMedia($nodeId, $uid, $externalLink, $extMetadata);

		static::assertTrue($result['success']);
	}


	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUploadExternalMediaSuccessWithNoMimetype(): void
	{
		$nodeId = 1;
		$uid = 1;
		$externalLink = 'https://example.com/video.webm';
		$extMetadata = [];
		$this->nodeServiceMock->method('isNodeUploadable')->willReturn(true);

		$responseMock = $this->createMock(ResponseInterface::class);
		$mediaHandler = $this->createMock(AbstractMediaHandler::class);

		$responseMock->method('getHeaderLine')
			->willReturnOnConsecutiveCalls('', '1234');

		$this->clientMock->method('head')->willReturn($responseMock);
		$this->mediaHandlerFactoryMock->method('createMediaHandler')->willReturn($mediaHandler);
		$mediaHandler->method('uploadFromExternal')->willReturn('/tmp/video.webm');
		$mediaHandler->method('determineNewFilename')->willReturn('abc123');

		$this->mediaRepositoryMock->method('findFirstBy')->willReturn([]);

		$result = $this->uploadService->uploadExternalMedia($nodeId, $uid, $externalLink, $extMetadata);

		static::assertTrue($result['success']);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUploadExternalMediaSuccessWithAlreadyRegisteredFile(): void
	{
		$nodeId = 1;
		$uid = 1;
		$externalLink = 'https://example.com/video.webm';
		$extMetadata = [];
		$this->nodeServiceMock->method('isNodeUploadable')->willReturn(true);

		$responseMock = $this->createMock(ResponseInterface::class);
		$mediaHandler = $this->createMock(AbstractMediaHandler::class);

		$responseMock->method('getHeaderLine')
			->willReturnOnConsecutiveCalls('video/webm', '1234');

		$this->clientMock->method('head')->willReturn($responseMock);
		$this->mediaHandlerFactoryMock->method('createMediaHandler')->willReturn($mediaHandler);
		$mediaHandler->method('uploadFromExternal')->willReturn('/tmp/video.webm');
		$mediaHandler->method('determineNewFilename')->willReturn('abc123');
		$mediaHandler->method('removeUploadedFile')->with('/tmp/video.webm');
		$dataSet = ['mimetype' => 'video/webm', 'metadata' => [], 'extension' => 'webm', 'media_description' => '', 'thumb_extension' => 'jpg', 'config_data' => ''];

		$this->mediaRepositoryMock->method('findFirstBy')->willReturn($dataSet);

		$result = $this->uploadService->uploadExternalMedia($nodeId, $uid, $externalLink, $extMetadata);

		static::assertTrue($result['success']);
	}


	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUploadExternalMediaFailure(): void
	{
		$nodeId = 1;
		$uid = 1;
		$externalLink = 'https://example.com/image.jpg';
		$extMetadata = [];
		$responseMock = $this->createMock(ResponseInterface::class);

		$this->clientMock->method('head')
			->willThrowException(new ClientException(
				'Not found',
				$this->createMock(RequestInterface::class),
				$responseMock
			));

		$this->loggerMock->expects($this->once())->method('error');

		$result = $this->uploadService->uploadExternalMedia($nodeId, $uid, $externalLink, $extMetadata);

		static::assertFalse($result['success']);
	}
}
