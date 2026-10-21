<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace App\Modules\Mediapool\Services;

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Media\MimeTypeService;
use App\Modules\Mediapool\Repositories\FilesRepository;
use App\Modules\Mediapool\Utils\AbstractMediaHandler;
use App\Modules\Mediapool\Utils\MediaHandlerFactory;
use App\Modules\Mediapool\Utils\SsrfValidator;
use Doctrine\DBAL\Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\TransferException;
use League\Flysystem\FilesystemException;
use Ramsey\Uuid\Uuid;
use Slim\Psr7\UploadedFile;

/**
 * UploadService class provides functionality for managing media uploads, including file uploads, external media handling,
 * and integration with metadata and database operations. This class leverages dependencies such as media factories,
 * services, and repositories to handle media processing efficiently.
 */
readonly class UploadService
{
	public function __construct(
		private MediaHandlerFactory $mediaHandlerFactory,
		private NodesService        $nodeService,
		private Client              $client,
		private FilesRepository     $mediaRepository,
		private MimeTypeService     $mimeTypeDetector,
		private SsrfValidator       $ssrfValidator)
	{
	}

	/**
	 * @param array<string, mixed> $headers
	 * @return array<string,mixed>
	 * @throws GuzzleException
	 */
	public function requestApi(string $apiUrl, array $headers = []): array
	{
		$options = [];
		if (!empty($headers))
			$options['headers'] = $headers;

		$response = $this->client->request('GET', $apiUrl, $options);

		return (array) json_decode($response->getBody()->getContents(), true);
	}

	/**
	 * Upload Media and insert them into a database. The filename is a sh256 hash of the file
	 *
	 * if this method finds a media already uploaded, it will create only an entry into the database
	 * and delete the new uploaded file.
	 *
	 * media_id is a UUID to make it more difficult to guess
	 *
	 * @param array<string,int|string> $metadata
	 * @return array<string,bool|string>
	 * @throws CoreException
	 * @throws Exception
	 * @throws FilesystemException
	 * @throws ModuleException
	 */
	public function uploadMediaFile(int $nodeId, int $UID, UploadedFile $uploadedFile, array $metadata): array
	{
		$this->checkNodeUploadable($nodeId);

		if ($uploadedFile->getError() !== UPLOAD_ERR_OK)
			throw $this->createUploadError($uploadedFile->getError());

		$preMimeType = $uploadedFile->getClientMediaType();
		if ($preMimeType === null)
			throw new ModuleException('mediapool', 'Not able to detect mime type.');

		$mediaHandler = $this->mediaHandlerFactory->createMediaHandler($preMimeType);
		$mediaHandler->setMetadata($metadata);
		$size = $uploadedFile->getSize();
		if ($size === null)
			throw new ModuleException('mediapool', 'Not able to detect size.');

		$mediaHandler->checkFileBeforeUpload($size);
		$uploadPath   = $mediaHandler->uploadFromLocal($uploadedFile);

		return $this->insertDataset($mediaHandler, $uploadPath, $nodeId, $UID);
	}

	/**
	 * @param array<string,string> $extMetadata
	 * @return array<string,bool|string>
	 * @throws CoreException
	 * @throws Exception
	 * @throws FilesystemException
	 * @throws ModuleException
	 */
	public function uploadExternalMedia(int $nodeId, int $UID, string $externalLink, array $extMetadata): array
	{
		$this->checkNodeUploadable($nodeId);
		$resolved = $this->ssrfValidator->validateAndResolveUrl($externalLink);
		$curlOpts = ['curl' => [CURLOPT_RESOLVE => ["{$resolved['host']}:{$resolved['port']}:{$resolved['ip']}"]]];

		try
		{
			$response    = $this->client->head($externalLink, $curlOpts);
			$preMimeType = $response->getHeaderLine('Content-Type');
			// workaround if no content-type sent
			if ($preMimeType === '' && (str_contains($externalLink, 'mp4') ||
					str_contains($externalLink, 'webm') || str_contains($externalLink, 'video')))
				$preMimeType = 'video/mp4';

			$contentLength = $response->getHeaderLine('Content-Length');
			$mediaHandler  = $this->mediaHandlerFactory->createMediaHandler($preMimeType);
			$mediaHandler->checkFileBeforeUpload((int) $contentLength);
			$uploadPath    = $mediaHandler->uploadFromExternal($this->client, $externalLink, $curlOpts);
		}
		catch (TransferException $e)
		{
			// a broken link or an unreachable host is a user error, not a server error
			throw new TranslatableModuleException('mediapool', 'external_media_not_loadable', ['url' => $externalLink], $e);
		}

		return $this->insertDataset($mediaHandler, $uploadPath, $nodeId, $UID, $extMetadata);
	}

	/**
	 * @param array<string,string> $extMetadata
	 * @return array<string,bool|string>
	 * @throws ModuleException
	 * @throws FilesystemException
	 * @throws Exception
	 */
	private function insertDataset(AbstractMediaHandler $mediaHandler, string $uploadedPath, int $nodeId, int $UID,
								   array $extMetadata = []): array
	{
		$fileHash = $mediaHandler->determineNewFilename($uploadedPath);
		$dataSet  = $this->mediaRepository->findFirstBy(['checksum' => ['value' => $fileHash, 'operator' => '=']]);
		$pathInfo = pathinfo($uploadedPath);

		if (empty($dataSet))
		{
			$mimetype    = $this->mimeTypeDetector->detectFromFile($mediaHandler->getAbsolutePath($uploadedPath));
			// extensions can be wrong we get from mimetype
			$ext         = $this->mimeTypeDetector->determineExtensionByMimeType($mimetype);
			$newFilePath = $mediaHandler->determineNewFilePath($uploadedPath, $fileHash, $ext);
			$mediaHandler->rename($uploadedPath, $newFilePath);
			$mediaHandler->validateStoredFile($newFilePath);
			$mediaHandler->createThumbnail($newFilePath);
			$configData  = $mediaHandler->getConfigData();
			$metadata    =  $this->combineMetaData($mediaHandler, $extMetadata);
			$description = '';

			if (array_key_exists('description', $metadata))
				$description = $metadata['description'];

			$metadata  = json_encode($metadata);
			$extension = pathinfo($newFilePath, PATHINFO_EXTENSION);
			$thumbExtension = $mediaHandler->getThumbExtension();
		}
		else
		{
			$mediaHandler->removeUploadedFile($uploadedPath);
			$mimetype       = $dataSet['mimetype'];
			$configData     = $dataSet['config_data'];
			$metadata       = $dataSet['metadata'];
			$extension      = $dataSet['extension'];
			$description    = $dataSet['media_description'];
			$thumbExtension = $dataSet['thumb_extension'];
		}

		$fileData = [
			'media_id'  => Uuid::uuid4()->toString(),
			'node_id'   => $nodeId,
			'UID'       => $UID,
			'checksum'  => $fileHash,
			'mimetype'  => $mimetype,
			'metadata'  => $metadata,
			'filename'  => $pathInfo['basename'],
			'extension' => $extension,
			'thumb_extension' => $thumbExtension,
			'config_data' => $configData,
			'media_description' => $description
		];
		$this->mediaRepository->insert($fileData);

		return ['success' => true, 'message' => $pathInfo['basename'].' successful uploaded'];
	}

	/**
	 * Errors the user can fix get a translation, server problems stay technical.
	 */
	private function createUploadError(int $code): ModuleException
	{
		return match ($code)
		{
			UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => new TranslatableModuleException('mediapool', 'upload_file_too_big'),
			UPLOAD_ERR_PARTIAL    => new TranslatableModuleException('mediapool', 'upload_partial'),
			UPLOAD_ERR_NO_FILE    => new TranslatableModuleException('mediapool', 'upload_no_file'),
			UPLOAD_ERR_NO_TMP_DIR => new ModuleException('mediapool', 'Missing a temporary folder'),
			UPLOAD_ERR_CANT_WRITE => new ModuleException('mediapool', 'Failed to write file to disk'),
			UPLOAD_ERR_EXTENSION  => new ModuleException('mediapool', 'File upload stopped by extension'),
			default               => new ModuleException('mediapool', 'Unknown upload error'),
		};
	}

	/**
	 * We need this bullshit because webapi media recorder do not include durations.
	 * To workaround, we use duration from metadata for ffmpeg lib
	 *
	 * Because we have different of metadata e.g. from stock pools / file uploads etc
	 *  we must combine them and prevent the double handling of duration
	 *
	 * Todo: Fix this when we have a better solution
	 * @param array<string,string> $extMetadata
	 * @return array<string,string>
	 */
	private function combineMetaData(AbstractMediaHandler$mediaHandler, array $extMetadata): array
	{
		// base metadata
		$metadata         = [
			'size'       => $mediaHandler->getFileSize(),
			'dimensions' => $mediaHandler->getDimensions(),
			'duration'   => $mediaHandler->getDuration()
		];

		// metadata from external sources
		foreach ($extMetadata as $key => $value)
		{
			$metadata[$key] = $value;
		}

		foreach ($mediaHandler->getMetadata() as $key => $value)
		{
			if ($key !== 'duration') // we have already duration from ffmpeg or metadata
				$metadata[$key] = $value;
		}

		return $metadata;
	}

	/**
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 */
	private function checkNodeUploadable(int $nodeId): void
	{
		if (!$this->nodeService->isNodeUploadable($nodeId))
			throw new AccessDeniedException('mediapool', 'node_not_uploadable', ['node_id' => $nodeId]);
	}

}