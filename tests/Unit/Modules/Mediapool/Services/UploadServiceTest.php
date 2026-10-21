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

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Media\MimeTypeService;
use App\Modules\Mediapool\Repositories\FilesRepository;
use App\Modules\Mediapool\Services\NodesService;
use App\Modules\Mediapool\Services\UploadService;
use App\Modules\Mediapool\Utils\AbstractMediaHandler;
use App\Modules\Mediapool\Utils\MediaHandlerFactory;
use App\Modules\Mediapool\Utils\SsrfValidator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Stream;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\UploadedFile;

class UploadServiceTest extends TestCase
{
	private UploadService $uploadService;
	private MediaHandlerFactory&MockObject $mediaHandlerFactoryMock;
	private NodesService&Stub $nodeServiceStub;
	private Client&MockObject $clientMock;
	private FilesRepository&Stub $mediaRepositoryStub;
	private MimeTypeService&Stub $mimeTypeService;
	private SsrfValidator&Stub $ssrfValidatorStub;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->mediaHandlerFactoryMock = $this->createMock(MediaHandlerFactory::class);
		$this->nodeServiceStub = $this->createStub(NodesService::class);
		$this->clientMock = $this->createMock(Client::class);
		$this->mediaRepositoryStub = $this->createStub(FilesRepository::class);
		$this->mimeTypeService = $this->createStub(MimeTypeService::class);
		$this->ssrfValidatorStub = $this->createStub(SsrfValidator::class);

		$this->uploadService = new UploadService(
			$this->mediaHandlerFactoryMock,
			$this->nodeServiceStub,
			$this->clientMock,
			$this->mediaRepositoryStub,
			$this->mimeTypeService,
			$this->ssrfValidatorStub
		);
	}

	/**
	 * @throws GuzzleException
	 * @throws Exception
	 */
	#[Group('units')]
	public function testRequestApiSuccess(): void
	{
		$this->mediaHandlerFactoryMock->expects($this->never())->method($this->anything());
		$expectedData = ['key' => 'value'];
		$responseStub = $this->createStub(ResponseInterface::class);

		$this->clientMock
			->method('request')
			->with('GET', 'https://api.example.com')
			->willReturn($responseStub);

		$resource = fopen('php://temp', 'r+');
		static::assertNotFalse($resource, 'Failed to resource for testing.');
		$str = json_encode($expectedData);
		static::assertNotFalse($str, 'Failed to json for testing.');
		$i = fwrite($resource, $str);
		static::assertNotFalse($i, 'Failed to resource for testing.');

		rewind($resource);

		$stream = new Stream($resource);
		$responseStub->method('getBody')->willReturn($stream);

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
		$this->mediaHandlerFactoryMock->expects($this->never())->method($this->anything());
		$expectedData = ['key' => 'value'];
		$responseStub = $this->createStub(ResponseInterface::class);

		$options = ['headers' => ['Auth' => 'Authorization: Bearer']];
		$this->clientMock
			->method('request')
			->with('GET', 'https://api.example.com', $options)
			->willReturn($responseStub);

		$resource = fopen('php://temp', 'r+');
		static::assertNotFalse($resource, 'Failed to resource for testing.');

		$str = json_encode($expectedData);
		static::assertNotFalse($str, 'Failed to json for testing.');
		$i = fwrite($resource, $str);
		static::assertNotFalse($i, 'Failed to resource for testing.');
		rewind($resource);

		$stream = new Stream($resource);
		$responseStub->method('getBody')->willReturn($stream);

		$result = $this->uploadService->requestApi('https://api.example.com', ['Auth' => 'Authorization: Bearer']);
		static::assertEquals($expectedData, $result);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUploadMediaFileSuccess(): void
	{
		$this->clientMock->expects($this->never())->method($this->anything());
		$nodeId = 1;
		$uid = 1;
		$metadata = ['description' => 'Test file'];

		$uploadedFile = $this->createStub(UploadedFile::class);
		$mediaHandler = $this->createMock(AbstractMediaHandler::class);
		$this->nodeServiceStub->method('isNodeUploadable')->willReturn(true);

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

		$this->mediaRepositoryStub->method('findFirstBy')
			->willReturn([]);

		$this->mimeTypeService
			->method('detectFromFile')
			->willReturn('image/jpeg');

		$result = $this->uploadService->uploadMediaFile($nodeId, $uid, $uploadedFile, $metadata);

		static::assertTrue($result['success']);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUploadMediaFileSuccessWithMetaDataDuration(): void
	{
		$this->clientMock->expects($this->never())->method($this->anything());
		$nodeId = 1;
		$uid = 1;

		$uploadedFileStub = $this->createStub(UploadedFile::class);
		$mediaHandlerMock = $this->createMock(AbstractMediaHandler::class);
		$this->nodeServiceStub->method('isNodeUploadable')->willReturn(true);

		// Mock uploaded file methods
		$uploadedFileStub->method('getError')->willReturn(UPLOAD_ERR_OK);
		$uploadedFileStub->method('getClientMediaType')->willReturn('video/mp4');
		$uploadedFileStub->method('getSize')->willReturn(1024);

		// Mock media handler
		$this->mediaHandlerFactoryMock->expects($this->once())->method('createMediaHandler')
			->with('video/mp4')
			->willReturn($mediaHandlerMock);

		$mediaHandlerMock->expects($this->once())->method('uploadFromLocal')
			->willReturn('/tmp/video.mp4');

		$mediaHandlerMock->method('determineNewFilename')->willReturn('abc123');

		$mediaHandlerMock->method('getMetadata')->willReturn(['title' => 'Test video']);

		$this->mediaRepositoryStub->method('findFirstBy')->willReturn([]);

		$this->mimeTypeService->method('detectFromFile')->willReturn('video/mp4');

		$extMetadata = ['duration' => 4050, 'description' => 'Test file'];
		$result = $this->uploadService->uploadMediaFile($nodeId, $uid, $uploadedFileStub, $extMetadata);

		static::assertTrue($result['success']);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUploadMediaFileWithoutUploadRights(): void
	{
		$this->mediaHandlerFactoryMock->expects($this->never())->method($this->anything());
		$this->clientMock->expects($this->never())->method($this->anything());
		$this->nodeServiceStub->method('isNodeUploadable')->willReturn(false);

		$this->expectException(AccessDeniedException::class);
		$this->expectExceptionMessage('node_not_uploadable {"node_id":7}');
		$this->uploadService->uploadMediaFile(7, 1, $this->createStub(UploadedFile::class), []);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUploadMediaFileWithErrorNull(): void
	{
		$this->mediaHandlerFactoryMock->expects($this->never())->method($this->anything());
		$this->clientMock->expects($this->never())->method($this->anything());
		$uploadedFile = $this->createStub(UploadedFile::class);
		$this->nodeServiceStub->method('isNodeUploadable')->willReturn(true);
		$uploadedFile->method('getClientMediaType')->willReturn(null);
		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Not able to detect mime type.');
		$this->uploadService->uploadMediaFile(1, 1, $uploadedFile, []);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUploadMediaFileWithErrorIniSize(): void
	{
		$this->mediaHandlerFactoryMock->expects($this->never())->method($this->anything());
		$this->clientMock->expects($this->never())->method($this->anything());
		$uploadedFile = $this->createStub(UploadedFile::class);
		$uploadedFile->method('getError')->willReturn(UPLOAD_ERR_INI_SIZE);
		$uploadedFile->method('getClientMediaType')->willReturn('image/jpeg');
		$this->nodeServiceStub->method('isNodeUploadable')->willReturn(true);
		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('upload_file_too_big');
		$this->uploadService->uploadMediaFile(1, 1, $uploadedFile, []);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUploadMediaFileWithErrorFormSize(): void
	{
		$this->mediaHandlerFactoryMock->expects($this->never())->method($this->anything());
		$this->clientMock->expects($this->never())->method($this->anything());
		$uploadedFile = $this->createStub(UploadedFile::class);
		$uploadedFile->method('getError')->willReturn(UPLOAD_ERR_FORM_SIZE);
		$uploadedFile->method('getClientMediaType')->willReturn('image/jpeg');
		$this->nodeServiceStub->method('isNodeUploadable')->willReturn(true);
		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('upload_file_too_big');
		$this->uploadService->uploadMediaFile(1, 1, $uploadedFile, []);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUploadMediaFileWithErrorPartial(): void
	{
		$this->mediaHandlerFactoryMock->expects($this->never())->method($this->anything());
		$this->clientMock->expects($this->never())->method($this->anything());
		$uploadedFile = $this->createStub(UploadedFile::class);
		$uploadedFile->method('getError')->willReturn(UPLOAD_ERR_PARTIAL);
		$uploadedFile->method('getClientMediaType')->willReturn('image/jpeg');
		$this->nodeServiceStub->method('isNodeUploadable')->willReturn(true);
		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('upload_partial');
		$this->uploadService->uploadMediaFile(1, 1, $uploadedFile, []);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUploadMediaFileWithErrorNoFile(): void
	{
		$this->mediaHandlerFactoryMock->expects($this->never())->method($this->anything());
		$this->clientMock->expects($this->never())->method($this->anything());
		$uploadedFile = $this->createStub(UploadedFile::class);
		$uploadedFile->method('getError')->willReturn(UPLOAD_ERR_NO_FILE);
		$uploadedFile->method('getClientMediaType')->willReturn('image/jpeg');
		$this->nodeServiceStub->method('isNodeUploadable')->willReturn(true);
		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('upload_no_file');
		$this->uploadService->uploadMediaFile(1, 1, $uploadedFile, []);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUploadMediaFileWithErrorNoFTmpDir(): void
	{
		$this->mediaHandlerFactoryMock->expects($this->never())->method($this->anything());
		$this->clientMock->expects($this->never())->method($this->anything());
		$uploadedFile = $this->createStub(UploadedFile::class);
		$uploadedFile->method('getError')->willReturn(UPLOAD_ERR_NO_TMP_DIR);
		$uploadedFile->method('getClientMediaType')->willReturn('image/jpeg');
		$this->nodeServiceStub->method('isNodeUploadable')->willReturn(true);
		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Missing a temporary folder');
		$this->uploadService->uploadMediaFile(1, 1, $uploadedFile, []);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUploadMediaFileWithErrorCantWrite(): void
	{
		$this->mediaHandlerFactoryMock->expects($this->never())->method($this->anything());
		$this->clientMock->expects($this->never())->method($this->anything());
		$uploadedFile = $this->createStub(UploadedFile::class);
		$uploadedFile->method('getError')->willReturn(UPLOAD_ERR_CANT_WRITE);
		$uploadedFile->method('getClientMediaType')->willReturn('image/jpeg');
		$this->nodeServiceStub->method('isNodeUploadable')->willReturn(true);
		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Failed to write file to disk');
		$this->uploadService->uploadMediaFile(1, 1, $uploadedFile, []);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUploadMediaFileWithErrorByExtension(): void
	{
		$this->mediaHandlerFactoryMock->expects($this->never())->method($this->anything());
		$this->clientMock->expects($this->never())->method($this->anything());
		$uploadedFile = $this->createStub(UploadedFile::class);
		$uploadedFile->method('getError')->willReturn(UPLOAD_ERR_EXTENSION);
		$uploadedFile->method('getClientMediaType')->willReturn('image/jpeg');
		$this->nodeServiceStub->method('isNodeUploadable')->willReturn(true);
		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('File upload stopped by extension');
		$this->uploadService->uploadMediaFile(1, 1, $uploadedFile, []);
	}


	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testUploadMediaFileWithErrorGetSizeNull(): void
	{
		$this->clientMock->expects($this->never())->method($this->anything());
		$uploadedFileStub = $this->createStub(UploadedFile::class);
		$this->nodeServiceStub->method('isNodeUploadable')->willReturn(true);
		$uploadedFileStub->method('getClientMediaType')->willReturn('video/mp4');
		$uploadedFileStub->method('getError')->willReturn(UPLOAD_ERR_OK);

		$mediaHandlerMock = $this->createStub(AbstractMediaHandler::class);
		$this->mediaHandlerFactoryMock->expects($this->once())->method('createMediaHandler')
			->with('video/mp4')
			->willReturn($mediaHandlerMock);

		$uploadedFileStub->method('getSize')->willReturn(null);
		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Not able to detect size.');

		$this->uploadService->uploadMediaFile(1, 1, $uploadedFileStub, []);
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

		$responseStub = $this->createStub(ResponseInterface::class);
		$mediaHandler = $this->createStub(AbstractMediaHandler::class);
		$this->nodeServiceStub->method('isNodeUploadable')->willReturn(true);
		$resolved = ['host' => 'example.com', 'port' => 80, 'ip' => '127.0.0.1',];
		$this->ssrfValidatorStub->method('validateAndResolveUrl')->willReturn($resolved);

		$responseStub->method('getHeaderLine')
			->willReturnOnConsecutiveCalls('image/jpeg', '1234');

		$this->clientMock->expects($this->atLeastOnce())->method('head')->willReturn($responseStub);
		$this->mediaHandlerFactoryMock->expects($this->atLeastOnce())->method('createMediaHandler')->willReturn($mediaHandler);
		$mediaHandler->method('uploadFromExternal')->willReturn('/tmp/test.jpg');
		$mediaHandler->method('determineNewFilename')->willReturn('abc123');

		$this->mediaRepositoryStub->method('findFirstBy')->willReturn([]);

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
		$this->nodeServiceStub->method('isNodeUploadable')->willReturn(true);
		$resolved = ['host' => 'example.com', 'port' => 80, 'ip' => '127.0.0.1',];
		$this->ssrfValidatorStub->method('validateAndResolveUrl')->willReturn($resolved);

		$responseStub = $this->createStub(ResponseInterface::class);
		$mediaHandler = $this->createStub(AbstractMediaHandler::class);

		$responseStub->method('getHeaderLine')
			->willReturnOnConsecutiveCalls('image/jpeg', '1234');

		$this->clientMock->expects($this->atLeastOnce())->method('head')->willReturn($responseStub);
		$this->mediaHandlerFactoryMock->expects($this->atLeastOnce())->method('createMediaHandler')->willReturn($mediaHandler);
		$mediaHandler->method('uploadFromExternal')->willReturn('/tmp/test.jpg');
		$mediaHandler->method('determineNewFilename')->willReturn('abc123');

		$this->mediaRepositoryStub->method('findFirstBy')->willReturn([]);

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
		$this->nodeServiceStub->method('isNodeUploadable')->willReturn(true);
		$resolved = ['host' => $externalLink, 'port' => 80, 'ip' => '127.0.0.1',];
		$this->ssrfValidatorStub->method('validateAndResolveUrl')->willReturn($resolved);

		$responseStub = $this->createStub(ResponseInterface::class);
		$mediaHandler = $this->createStub(AbstractMediaHandler::class);

		$responseStub->method('getHeaderLine')
			->willReturnOnConsecutiveCalls('', '1234');

		$this->clientMock->expects($this->atLeastOnce())->method('head')->willReturn($responseStub);
		$this->mediaHandlerFactoryMock->expects($this->atLeastOnce())->method('createMediaHandler')->willReturn($mediaHandler);
		$mediaHandler->method('uploadFromExternal')->willReturn('/tmp/video.webm');
		$mediaHandler->method('determineNewFilename')->willReturn('abc123');

		$this->mediaRepositoryStub->method('findFirstBy')->willReturn([]);

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
		$this->nodeServiceStub->method('isNodeUploadable')->willReturn(true);
		$resolved = ['host' => $externalLink, 'port' => 80, 'ip' => '127.0.0.1',];
		$this->ssrfValidatorStub->method('validateAndResolveUrl')->willReturn($resolved);

		$responseStub = $this->createStub(ResponseInterface::class);
		$mediaHandler = $this->createMock(AbstractMediaHandler::class);

		$responseStub->method('getHeaderLine')
			->willReturnOnConsecutiveCalls('video/webm', '1234');

		$this->clientMock->expects($this->atLeastOnce())->method('head')->willReturn($responseStub);
		$this->mediaHandlerFactoryMock->expects($this->atLeastOnce())->method('createMediaHandler')->willReturn($mediaHandler);
		$mediaHandler->method('uploadFromExternal')->willReturn('/tmp/video.webm');
		$mediaHandler->method('determineNewFilename')->willReturn('abc123');
		$mediaHandler->method('removeUploadedFile')->with('/tmp/video.webm');
		$dataSet = ['mimetype' => 'video/webm', 'metadata' => [], 'extension' => 'webm', 'media_description' => '', 'thumb_extension' => 'jpg', 'config_data' => ''];

		$this->mediaRepositoryStub->method('findFirstBy')->willReturn($dataSet);

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
		$responseStub = $this->createStub(ResponseInterface::class);

		$this->nodeServiceStub->method('isNodeUploadable')->willReturn(true);
		$this->ssrfValidatorStub->method('validateAndResolveUrl')->willReturn(['host' => 'example.com', 'port' => 443, 'ip' => '93.184.216.34']);
		$this->mediaHandlerFactoryMock->expects($this->never())->method($this->anything());
		$this->clientMock->expects($this->never())->method('request');
		$this->clientMock->expects($this->once())->method('head')
			->with($externalLink)
			->willThrowException(new ClientException(
				'Not found',
				$this->createStub(RequestInterface::class),
				$responseStub
			));

		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('external_media_not_loadable');

		$this->uploadService->uploadExternalMedia($nodeId, $uid, $externalLink, $extMetadata);
	}
}
