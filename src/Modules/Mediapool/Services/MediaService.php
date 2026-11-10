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
use App\Framework\Database\Where;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\UserException;
use App\Framework\Media\MediaUrlSigner;
use App\Modules\Auth\UserSession;
use App\Modules\Mediapool\Repositories\FilesRepository;
use App\Modules\Mediapool\Repositories\NodesRepository;
use App\Shared\Acl\AclSections;
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
	public const int MAX_TAG_LENGTH = 50;
	public const int MAX_SEARCH_RESULTS = 100;

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
			$media['tags']     = self::decodeTags($media['tags']);
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
		$media['tags']     = self::decodeTags($media['tags']);

		return $this->addFileLocations($media);
	}

	/**
	 * The media of all directories the user may read whose tags, file name or description contain the term.
	 * Each one knows the path of its directory and whether the user may edit it.
	 *
	 * @return list<array<string,mixed>>
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function searchMedia(string $term, string $filter = ''): array
	{
		$UID    = $this->userSession->getUID();
		$result = $this->mediaRepository->search($term, $filter, $this->readableDirectories($UID), self::MAX_SEARCH_RESULTS);

		// @infection-ignore-all the cast is for databases which give text; unique and values only save queries
		$nodeIds  = array_values(array_unique(array_map(static fn(array $media): int => (int) $media['node_id'], $result)));
		$paths    = $this->nodesRepository->findPaths($nodeIds);
		$editable = [];
		foreach ($nodeIds as $nodeId)
			$editable[$nodeId] = $this->aclValidator->checkDirectoryPermissions($UID, $this->nodesRepository->findNodeOwner($nodeId))['edit'];

		foreach ($result as &$media)
		{
			$media['metadata'] = json_decode((string) $media['metadata'], true);
			$media['tags']     = self::decodeTags(is_string($media['tags']) ? $media['tags'] : null);
			// @infection-ignore-all a key of digits is a number in PHP: the cast is for the type
			$media['path']     = '/ ' . implode(' / ', $paths[(string) $media['node_id']] ?? []);
			// @infection-ignore-all the cast is for databases which give text, ours gives numbers
			$media['editable'] = $editable[(int) $media['node_id']];
			$media = $this->addFileLocations($media);
		}

		return $result;
	}

	/**
	 * Whether the user may read a file of the mediapool, given as <checksum>.<extension> like in a template.
	 *
	 * @throws CoreException
	 * @throws Exception
	 * @throws UserException
	 */
	public function isReadableFile(string $fileName): bool
	{
		$checksum  = pathinfo($fileName, PATHINFO_FILENAME);
		$extension = pathinfo($fileName, PATHINFO_EXTENSION);
		// @infection-ignore-all a file without checksum or extension is never stored: the query would not find it either
		if ($checksum === '' || $extension === '' || basename($fileName) !== $fileName)
			return false;

		return $this->mediaRepository->existsReadable($checksum, $extension, $this->readableDirectories($this->userSession->getUID()));
	}

	/**
	 * A medium which the user may change, e.g. for the suggestion of its tags.
	 *
	 * @return array<string,mixed>
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws NotFoundException
	 * @throws UserException
	 */
	public function fetchEditableMedia(string $mediaId): array
	{
		$media = $this->fetchMediaById($mediaId);
		$this->checkPermission($this->nodesRepository->findNodeOwner($media['node_id']), 'edit');

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
	 * @param list<string>|null $tags null keeps the tags
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws NotFoundException
	 * @throws UserException
	 */
	public function updateMedia(string $mediaId, string $filename, string $description, ?array $tags = null): int
	{
		$media = $this->fetchMediaById($mediaId);
		$this->checkPermission($this->nodesRepository->findNodeOwner($media['node_id']), 'edit');

		$fields = ['filename' => $filename, 'media_description' => $description];
		if ($tags !== null)
			$fields['tags'] = self::encodeTags($tags);

		return $this->mediaRepository->update($mediaId, $fields);
	}

	/**
	 * The tags are stored with commas around each one, ",canteen,product,": a search for ",canteen," finds the
	 * tag and not a longer word which contains it. A tag has no comma, the same tag is stored once.
	 *
	 * @param list<string> $tags
	 */
	public static function encodeTags(array $tags): string
	{
		$unique = [];
		foreach ($tags as $tag)
		{
			$tag = trim((string) preg_replace('/\s+/u', ' ', str_replace(',', ' ', $tag)));
			if ($tag !== '' && !isset($unique[mb_strtolower($tag)]))
				$unique[mb_strtolower($tag)] = mb_substr($tag, 0, self::MAX_TAG_LENGTH);
		}

		return $unique === [] ? '' : ',' . implode(',', $unique) . ',';
	}

	/**
	 * @return list<string>
	 */
	private static function decodeTags(?string $tags): array
	{
		return array_values(array_filter(explode(',', (string) $tags), static fn(string $tag): bool => $tag !== ''));
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
		$dataSet['tags']     = self::decodeTags(is_string($dataSet['tags'] ?? null) ? $dataSet['tags'] : null);

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
		// @infection-ignore-all the casts are for the type, the columns are text
		$checksum       = (string) $media['checksum'];
		// @infection-ignore-all the casts are for the type, the columns are text
		$extension      = (string) $media['extension'];
		$thumbExtension = (string) $media['thumb_extension'];

		$media['paths']['original']  = $this->pathOriginals.'/'.$checksum.'.'.$extension;
		$media['paths']['preview']   = $this->pathPreviews.'/'.$checksum.'.'.$extension;
		$media['paths']['thumbnail'] = $this->pathThumbnails.'/'.$checksum.'.'.$thumbExtension;

		$media['urls']['original']  = $this->mediaUrlSigner->originalUrl($checksum, $extension);
		$media['urls']['thumbnail'] = $this->mediaUrlSigner->thumbUrl($checksum, $thumbExtension);
		// the preview runs the widget from its archive, with the values of config.xml (see widget.php)
		if (($media['mimetype'] ?? '') === 'application/widget')
			$media['urls']['preview'] = $this->mediaUrlSigner->widgetUrl($checksum);

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
	 * The directories which the user may read, as condition on mediapool_nodes, like checkDirectoryPermissions():
	 * his own ones, the public ones and the ones shared with him. Rights are not inherited by subdirectories.
	 * null for the module admin, who reads all. The sub admin of the Enterprise edition is not part of it yet:
	 * he finds less, never more.
	 *
	 * @throws CoreException
	 * @throws Exception
	 */
	private function readableDirectories(int $UID): ?Where
	{
		if ($this->aclValidator->isModuleAdmin($UID))
			return null;

		$roles = [];
		if ($this->aclValidator->isEditor($UID))
			$roles[] = AclSections::EDITOR;
		if ($this->aclValidator->isViewer($UID))
			$roles[] = AclSections::VIEWER;
		// @infection-ignore-all the ids are numbers as text, intval is for the type
		$shared = $roles === [] ? [] : array_map(intval(...), $this->aclValidator->findSharedUnitIds($UID, ...$roles));

		return Where::anyOf(
			Where::equals('mediapool_nodes.UID', $UID),
			Where::raw('(mediapool_nodes.visibility & ' . AclValidator::VISIBILITY_PUBLIC . ') > 0'),
			Where::in('mediapool_nodes.node_id', $shared)
		);
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