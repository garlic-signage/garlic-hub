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
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\UserException;
use App\Framework\Media\MediaUrlSigner;
use App\Modules\Auth\UserSession;
use App\Modules\Mediapool\Repositories\FilesRepository;
use App\Modules\Mediapool\Repositories\NodesRepository;
use Ramsey\Uuid\Uuid;
use Doctrine\DBAL\Exception;

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
	private readonly MediaUrlSigner $mediaUrlSigner;
	private readonly UserSession $userSession;
	private string $pathOriginals;
	private string $pathPreviews;
	private string $pathThumbnails;

	/**
	 * @param FilesRepository $mediaRepository
	 * @param NodesRepository $nodesRepository
	 * @param AclValidator $aclValidator
	 * @param MediaUrlSigner $mediaUrlSigner
	 * @param UserSession $userSession
	 */
	public function __construct(FilesRepository $mediaRepository, NodesRepository $nodesRepository, AclValidator $aclValidator, MediaUrlSigner $mediaUrlSigner, UserSession $userSession)
	{
		$this->mediaRepository = $mediaRepository;
		$this->nodesRepository = $nodesRepository;
		$this->aclValidator    = $aclValidator;
		$this->mediaUrlSigner  = $mediaUrlSigner;
		$this->userSession     = $userSession;

		$this->pathOriginals = $aclValidator->getConfig()->getConfigValue('originals', 'mediapool', 'directories');
		$this->pathPreviews  = $aclValidator->getConfig()->getConfigValue('previews', 'mediapool', 'directories');
		$this->pathThumbnails = $aclValidator->getConfig()->getConfigValue('thumbnails', 'mediapool', 'directories');
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
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws UserException
	 */
	public function listMedia(int $nodeId, string $filter = ''): array
	{
		$node = $this->nodesRepository->findNodeOwner($nodeId);
		$this->checkPermission($node, 'read');

		$result = $this->mediaRepository->findAllByNodeId($nodeId, $filter);

		foreach ($result as &$media)
		{
			$media['metadata'] = json_decode($media['metadata'], true);
			$media = $this->addFileLocations($media);
		}

		return $result;
	}

	/**
	 * @return array<string,mixed>
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws NotFoundException
	 * @throws UserException
	 */
	public function fetchMedia(string $mediaId): array
	{
		$media = $this->fetchMediaById($mediaId);
		$this->checkPermission($this->nodesRepository->findNodeOwner($media['node_id']), 'read');

		$media['metadata'] = json_decode($media['metadata'], true);

		return $this->addFileLocations($media);
	}

	/**
	 * @return array<string,mixed>
	 * @throws Exception
	 */
	public function fetchMediaByChecksum(string $checksum): array
	{
		$media = $this->mediaRepository->findAllWithOwnerByCheckSum($checksum);
		if ($media === [])
			return [];

		/** @var array<string,mixed> $media */

		$media['metadata'] = json_decode((string) $media['metadata'], true);

		return $media;
	}

	/**
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws NotFoundException
	 * @throws UserException
	 */
	public function updateMedia(string $mediaId, string $filename, string $description): int
	{
		$media = $this->fetchMediaById($mediaId);
		$this->checkPermission($this->nodesRepository->findNodeOwner($media['node_id']), 'edit');

		return $this->mediaRepository->update($mediaId, ['filename' => $filename, 'media_description' => $description]);
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

	/**
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws NotFoundException
	 * @throws UserException
	 */
	public function deleteMedia(string $mediaId): int
	{
		$media = $this->fetchMediaById($mediaId);
		$this->checkPermission($this->nodesRepository->findNodeOwner($media['node_id']), 'edit');

		$field     = ['deleted' => 1];
		$condition = ['media_id' => $mediaId];

		return $this->mediaRepository->updateWithWhere($field, $condition);
	}

	/**
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws NotFoundException
	 * @throws UserException
	 */
	public function moveMedia(string $mediaId, int $nodeId): int
	{
		$media = $this->fetchMediaById($mediaId);
		// moving takes the media away from its directory: reading it there is not enough
		$this->checkPermission($this->nodesRepository->findNodeOwner($media['node_id']), 'edit');
		$this->checkPermission($this->nodesRepository->findNodeOwner($nodeId), 'edit');

		$condition = ['media_id' => $mediaId];
		$field     = ['node_id'  => $nodeId];

		return $this->mediaRepository->updateWithWhere($field, $condition);
	}

	/**
	 * @return array<string,mixed>
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws NotFoundException
	 * @throws UserException
	 */
	public function cloneMedia(string $mediaId): array
	{
		$media = $this->fetchMediaById($mediaId);
		$this->checkPermission($this->nodesRepository->findNodeOwner($media['node_id']), 'edit');

		// no need to check this as it is only the complete dataset which we checked with fetchMediaById
		/** @var array<string,mixed> $dataSet */
		$dataSet = $this->mediaRepository->getFirstDataSet($this->mediaRepository->findById($mediaId));

		$dataSet['media_id'] = Uuid::uuid4()->toString();
		$dataSet['UID']      = $this->userSession->getUID();

		$dataSet['uuid']     = $this->mediaRepository->insert($dataSet);

		return $this->addFileLocations($dataSet);
	}

	/**
	 * Adds the file system paths (server side use) and the signed media.php urls
	 * (frontend use) of a media data set. The files are stored outside the document
	 * root, so they are never reachable by their path.
	 *
	 * @param array<string,mixed> $media
	 * @return array<string,mixed>
	 */
	private function addFileLocations(array $media): array
	{
		$checksum       = (string) $media['checksum'];
		$extension      = (string) $media['extension'];
		$thumbExtension = (string) $media['thumb_extension'];

		$media['paths']['original']  = $this->pathOriginals.'/'.$checksum.'.'.$extension;
		$media['paths']['preview']   = $this->pathPreviews.'/'.$checksum.'.'.$extension;
		$media['paths']['thumbnail'] = $this->pathThumbnails.'/'.$checksum.'.'.$thumbExtension;

		$media['urls']['original']  = $this->mediaUrlSigner->originalUrl($checksum, $extension);
		$media['urls']['thumbnail'] = $this->mediaUrlSigner->thumbUrl($checksum, $thumbExtension);

		return $media;
	}

	/**
	 * @return array{
	 *  username: string, company_id:int, media_id:string, UID:int, node_id:int,
	 *  upload_time:string, checksum:string, mimetype:string, metadata:string, tags:string,
	 *  filename:string, extension:string, thumb_extension:string, media_description:string,
	 *  config_data:string}
	 * @throws Exception
	 * @throws NotFoundException
	 */
	private function fetchMediaById(string $mediaId): array
	{
		$media = $this->mediaRepository->findAllWithOwnerById($mediaId);
		if ($media === [])
			throw new NotFoundException('mediapool', 'media_not_found', ['media_id' => $mediaId]);

		return $media;
	}

	/**
	 * @param array<string,mixed> $node
	 * @param 'read'|'edit' $permission
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws UserException
	 */
	private function checkPermission(array $node, string $permission): void
	{
		$permissions = $this->aclValidator->checkDirectoryPermissions($this->userSession->getUID(), $node);
		if (!$permissions[$permission])
			throw new AccessDeniedException('mediapool', 'directory_not_' . ($permission === 'read' ? 'readable' : 'editable'), [
				'node_id' => (int) ($node['node_id'] ?? 0)
			]);
	}
}