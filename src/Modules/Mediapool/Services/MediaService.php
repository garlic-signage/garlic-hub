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

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\DatabaseException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Mediapool\Repositories\FilesRepository;
use App\Modules\Mediapool\Repositories\NodesRepository;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Ramsey\Uuid\Uuid;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Class MediaService
 *
 * Handles functionality related to media, including listing, fetching, updating,
 * moving, deleting, and cloning media records. It also manages media file paths
 * and integrates with ACL for permission management.
 */
class MediaService
{
	private readonly FilesRepository $mediaRepository;
	private readonly NodesRepository $nodesRepository;
	private readonly AclValidator $aclValidator;
	private readonly LoggerInterface $logger;
	private int $UID;
	private string $pathOriginals;
	private string $pathPreviews;
	private string $pathThumbnails;

	/**
	 * @param FilesRepository $mediaRepository
	 * @param NodesRepository $nodesRepository
	 * @param AclValidator $aclValidator
	 * @param LoggerInterface $logger
	 */
	public function __construct(FilesRepository $mediaRepository, NodesRepository $nodesRepository, AclValidator $aclValidator, LoggerInterface $logger)
	{
		$this->mediaRepository = $mediaRepository;
		$this->nodesRepository = $nodesRepository;
		$this->aclValidator    = $aclValidator;
		$this->logger          = $logger;

		$this->pathOriginals = $aclValidator->getConfig()->getConfigValue('originals', 'mediapool', 'directories');
		$this->pathPreviews  = $aclValidator->getConfig()->getConfigValue('previews', 'mediapool', 'directories');
		$this->pathThumbnails = $aclValidator->getConfig()->getConfigValue('thumbnails', 'mediapool', 'directories');
	}

	public function setUID(int $UID): void
	{
		$this->UID = $UID;
	}

	public function getPathThumbnails(): string
	{
		return $this->pathThumbnails;
	}

	public function getPathOriginals(): string
	{
		return $this->pathOriginals;
	}

	/**
	 * @return list<array<string,mixed>>
	 */
	public function listMedia(int $nodeId): array
	{
		try
		{
			$node = $this->nodesRepository->findNodeOwner($nodeId);

			$permissions = $this->aclValidator->checkDirectoryPermissions($this->UID, $node);
			if (!$permissions['read'])
				throw new ModuleException('mediapool', 'No read permissions on directory:'. $node['name']);

			$result = $this->mediaRepository->findAllByNodeId($nodeId);

			foreach ($result as &$media)
			{
				$media['metadata'] = json_decode($media['metadata'], true);

				$media['paths']['original']  = $this->getPathOriginals().'/'.$media['checksum'].'.'.$media['extension'];
				$media['paths']['preview']   = $this->pathPreviews.'/'.$media['checksum'].'.'.$media['extension'];
				$media['paths']['thumbnail'] = $this->getPathThumbnails().'/'.$media['checksum'].'.'.$media['thumb_extension'];
			}

			return $result;
		}
		catch (Throwable $e)
		{
			$this->logger->error('Error listing media: ' . $e->getMessage());
			return [];
		}
	}

	/**
	 * @param string $mediaId
	 * @return array<string,mixed>
	 * @throws DatabaseException
	 */
	public function fetchMedia(string $mediaId): array
	{
		try
		{
			$media       = $this->fetchMediaById($mediaId);
			$node        = $this->nodesRepository->findNodeOwner($media['node_id']);
			$permissions = $this->aclValidator->checkDirectoryPermissions($this->UID, $node);
			if (!$permissions['read'])
				throw new ModuleException('mediapool', 'No read permissions in this directory: '. $media['node_id']);

			$media['metadata'] = json_decode($media['metadata'], true);

			$media['paths']['original']  = $this->pathOriginals.'/'.$media['checksum'].'.'.$media['extension'];
			$media['paths']['preview']   = $this->pathPreviews.'/'.$media['checksum'].'.'.$media['extension'];
			$media['paths']['thumbnail'] = $this->pathThumbnails.'/'.$media['checksum'].'.'.$media['thumb_extension'];
			return $media;
		}
		catch (Exception | CoreException | ModuleException | PhpfastcacheSimpleCacheException $e)
		{
			$this->logger->error('Error fetch media: ' . $e->getMessage());
			return [];
		}
	}

	/**
	 * @param string $checksum
	 * @return array<string,mixed>
	 * @throws Exception
	 */
	public function fetchMediaByChecksum(string $checksum): array
	{
		$media = $this->mediaRepository->findAllWithOwnerByCheckSum($checksum);
		$media['metadata'] = json_decode($media['metadata'], true);

		return $media;
	}

	/**
	 * @throws DatabaseException
	 */
	public function updateMedia(string $mediaId, string $filename, string $description): int
	{
		try
		{
			$media = $this->fetchMediaById($mediaId);

			$node        = $this->nodesRepository->findNodeOwner($media['node_id']);
			$permissions = $this->aclValidator->checkDirectoryPermissions($this->UID, $node);
			if (!$permissions['edit'])
				throw new ModuleException('mediapool', 'No edit permissions in this directory: '. $media['node_id']);

			return $this->mediaRepository->update($mediaId, ['filename' => $filename, 'media_description' => $description]);
		}
		catch (Exception | CoreException | ModuleException | PhpfastcacheSimpleCacheException $e)
		{
			$this->logger->error('Error updating media: ' . $e->getMessage());
			return 0;
		}
	}

	/**
	 * @throws Exception
	 */
	public function markDeleteMediaByNodeId(int $nodeId): void
	{
		$fields = ['deleted' => 1];
		$condition = ['node_id' => $nodeId];

		$this->mediaRepository->updateWithWhere($fields, $condition);
	}

	public function deleteMedia(string $mediaId): int
	{
		try
		{
			$media = $this->fetchMediaById($mediaId);
			$node        = $this->nodesRepository->findNodeOwner($media['node_id']);
			$permissions = $this->aclValidator->checkDirectoryPermissions($this->UID, $node);
			if (!$permissions['edit'])
				throw new ModuleException('mediapool', 'No edit permissions in this directory: '. $media['node_id']);

			$field     = ['deleted' => 1];
			$condition = ['media_id' => $mediaId];

			return $this->mediaRepository->updateWithWhere($field, $condition);
		}
		catch (Throwable $e)
		{
			$this->logger->error('Error deleting media: ' . $e->getMessage());
			return 0;
		}
	}

	public function moveMedia(string $mediaId, int $nodeId): int
	{
		try
		{
			$media = $this->fetchMediaById($mediaId);

			$node = $this->nodesRepository->findNodeOwner($media['node_id']);
			$permissions = $this->aclValidator->checkDirectoryPermissions($this->UID, $node);

			if (!$permissions['read'])
				throw new ModuleException('mediapool', 'No read permissions in this directory: '. $media['node_id']);

			$node = $this->nodesRepository->findNodeOwner($nodeId);
			$permissions = $this->aclValidator->checkDirectoryPermissions($this->UID, $node);
			if (!$permissions['edit'])
				throw new ModuleException('mediapool', 'No edit permissions in this directory: '. $node['name']);

			$condition = ['media_id' => $mediaId];
			$field     = ['node_id'  => $nodeId];

			return $this->mediaRepository->updateWithWhere($field, $condition);
		}
		catch (Throwable $e)
		{
			$this->logger->error('Error moving media: ' . $e->getMessage());
			return 0;
		}
	}

	/**
	 * @param string $mediaId
	 * @return array<string,mixed>
	 */
	public function cloneMedia(string $mediaId): array
	{
		try
		{
			$media = $this->fetchMediaById($mediaId);
			$node  = $this->nodesRepository->findNodeOwner($media['node_id']);
			$permissions = $this->aclValidator->checkDirectoryPermissions($this->UID, $node);
			if (!$permissions['edit'])
				throw new ModuleException('mediapool', 'No permissions on this directory: '. $media['node_id']);

			// no need to check this as it is only the complete dataset which we checked with fetchMediaById
			$dataSet = $this->mediaRepository->getFirstDataSet($this->mediaRepository->findById($mediaId));

			$dataSet['media_id'] = Uuid::uuid4()->toString();
			$dataSet['UID']      = $this->UID;

			$dataSet['uuid']     = $this->mediaRepository->insert($dataSet);

			return $dataSet;
		}
		catch (Throwable $e)
		{
			$this->logger->error('Error cloning media: ' . $e->getMessage());
			return [];
		}
	}

	/**
	 * @return array{
	 *  username: string, company_id:int, media_id:string, UID:int, node_id:int,
	 *  upload_time:string, checksum:string, mimetype:string, metadata:string, tags:string,
	 *  filename:string, extension:string, thumb_extension:string, media_description:string,
	 *  config_data:string}
	 * @throws Exception
	 * @throws ModuleException
	 */
	private function fetchMediaById(string $mediaId): array
	{
		$media = $this->mediaRepository->findAllWithOwnerById($mediaId);
		if ($media === [])
			throw new ModuleException('mediapool', 'No media found.');

		return $media;

	}
}