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

use App\Framework\Core\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Media\MediaUrlSigner;
use App\Modules\Mediapool\Repositories\FilesRepository;
use App\Modules\Mediapool\Repositories\NodesRepository;
use App\Modules\Mediapool\Services\AclValidator;
use App\Modules\Mediapool\Services\MediaService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MediaServiceTest extends TestCase
{

	private MediaService $mediaService;
	private FilesRepository&MockObject $mediaRepositoryMock;
	private NodesRepository&MockObject $nodesRepositoryMock;
	private AclValidator&MockObject $aclValidatorMock;
	private LoggerInterface&MockObject $loggerMock;

	/**
	 * @throws Exception
	 * @throws CoreException
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->mediaRepositoryMock = $this->createMock(FilesRepository::class);
		$this->nodesRepositoryMock = $this->createMock(NodesRepository::class);
		$this->aclValidatorMock    = $this->createMock(AclValidator::class);
		$this->loggerMock          = $this->createMock(LoggerInterface::class);
		$configMock = $this->createMock(Config::class);
		$this->aclValidatorMock->method('getConfig')->willReturn($configMock);
		$configMock->method('getConfigValue')->willReturnMap([
				['originals', 'mediapool', 'directories', 'original_directory'],
				['previews', 'mediapool', 'directories', 'preview_directory'],
				['thumbnails', 'mediapool', 'directories', 'thumbnail_directory'],
			]
		);

		$this->mediaService = new MediaService(
			$this->mediaRepositoryMock,
			$this->nodesRepositoryMock,
			$this->aclValidatorMock,
			new MediaUrlSigner('unit-test-secret'),
			$this->loggerMock
		);
		$this->mediaService->setUID(1);
	}

	#[Group('units')]
	public function testListMediaReturnsMediaList(): void
	{
		$nodeId = 1;
		$node = ['id' => $nodeId, 'name' => 'Test Node'];
		$mediaList = [
			['id' => 1, 'metadata' => json_encode(['key' => 'value']), 'checksum' => 'checksum', 'extension' => 'extension', 'thumb_extension' => 'thumb_extension'],
			['id' => 2, 'metadata' => json_encode(['key' => 'value']),'checksum' => 'checksum', 'extension' => 'extension', 'thumb_extension' => 'thumb_extension']
		];

		$this->nodesRepositoryMock->method('findNodeOwner')->willReturn($node);
		$this->aclValidatorMock->method('checkDirectoryPermissions')->willReturn(['read' => true]);
		$this->mediaRepositoryMock->method('findAllByNodeId')->willReturn($mediaList);
		$this->loggerMock->expects($this->never())->method('error');

		$result = $this->mediaService->listMedia($nodeId);

		static::assertCount(2, $result);
		static::assertEquals(['key' => 'value'], $result[0]['metadata']);
		static::assertSame('original_directory/checksum.extension', $result[0]['paths']['original']);
		static::assertSame('thumbnail_directory/checksum.thumb_extension', $result[0]['paths']['thumbnail']);
		static::assertStringStartsWith('/media.php/orig/checksum.extension?exp=', $result[0]['urls']['original']);
		static::assertStringStartsWith('/media.php/thumb/checksum.thumb_extension?exp=', $result[0]['urls']['thumbnail']);
	}

	#[Group('units')]
	public function testListMediaNoReadPermissions(): void
	{
		$nodeId = 1;
		$node = ['id' => $nodeId, 'name' => 'Test Node'];

		$this->nodesRepositoryMock->method('findNodeOwner')->willReturn($node);
		$this->aclValidatorMock->method('checkDirectoryPermissions')->willReturn(['read' => false]);
		$this->loggerMock->expects($this->once())->method('error')
		->with('Error listing media: No read permissions on directory:Test Node');

		static::assertEmpty($this->mediaService->listMedia($nodeId));
	}

	#[Group('units')]
	public function testFetchMediaReturnsMedia(): void
	{
		$mediaId = 'media-1';
		$media = [
			'id' => $mediaId,
			'metadata' => json_encode(['key' => 'value']),
			'node_id' => 1,
			'checksum' => 'checksum',
			'extension' => 'extension',
			'thumb_extension' => 'thumb_extension'
		];

		$this->mediaRepositoryMock->method('findAllWithOwnerById')->willReturn($media);
		$this->aclValidatorMock->method('checkDirectoryPermissions')->willReturn(['read' => true]);
		$this->loggerMock->expects($this->never())->method('error');

		$result = $this->mediaService->fetchMedia($mediaId);

		static::assertEquals(['key' => 'value'], $result['metadata']);
		static::assertStringStartsWith('/media.php/thumb/checksum.thumb_extension?exp=', $result['urls']['thumbnail']);
	}

	#[Group('units')]
	public function testFetchMediaNoReadPermissions(): void
	{
		$mediaId = 'media-1';
		$media = ['id' => $mediaId, 'metadata' => json_encode(['key' => 'value']), 'node_id' => 1];

		$this->mediaRepositoryMock->method('findAllWithOwnerById')->willReturn($media);
		$this->aclValidatorMock->method('checkDirectoryPermissions')->willReturn(['read' => false]);
		$this->loggerMock->expects($this->once())->method('error')
			->with('Error fetch media: No read permissions in this directory: 1');

		static::assertEmpty($this->mediaService->fetchMedia($mediaId));
	}

	#[Group('units')]
	public function testUpdateMediaUpdatesMedia(): void
	{
		$mediaId = 'media-1';
		$filename = 'new-filename';
		$description = 'new-description';
		$media = ['id' => $mediaId, 'node_id' => 1];

		$this->mediaRepositoryMock->method('findAllWithOwnerById')->willReturn($media);
		$this->aclValidatorMock->method('checkDirectoryPermissions')->willReturn(['edit' => true]);
		$this->mediaRepositoryMock->method('update')->willReturn(1);
		$this->loggerMock->expects($this->never())->method('error');

		$result = $this->mediaService->updateMedia($mediaId, $filename, $description);

		static::assertEquals(1, $result);
	}

	#[Group('units')]
	public function testUpdateMediaNoEditPermissions(): void
	{
		$mediaId = 'media-1';
		$filename = 'new-filename';
		$description = 'new-description';
		$media = ['id' => $mediaId, 'node_id' => 2];

		$this->mediaRepositoryMock->method('findAllWithOwnerById')->willReturn($media);
		$this->aclValidatorMock->method('checkDirectoryPermissions')->willReturn(['edit' => false]);

		$this->loggerMock->expects($this->once())->method('error')
			->with('Error updating media: No edit permissions in this directory: 2');

		static::assertEquals(0, $this->mediaService->updateMedia($mediaId, $filename, $description));
	}

	#[Group('units')]
	public function testMarkDeleteMediaByNodeIdUpdatesMedia(): void
	{
		$nodeId = 1;

		$this->mediaRepositoryMock->expects($this->once())->method('updateWithWhere')
			->with(['deleted' => 1], ['node_id' => $nodeId])
			->willReturn(1);

		$this->mediaService->markDeleteMediaByNodeId($nodeId);
	}

	#[Group('units')]
	public function testMarkDeleteMediaByNodeIdHandlesNoMedia(): void
	{
		$nodeId = 1;

		$this->mediaRepositoryMock->expects($this->once())->method('updateWithWhere')
			->with(['deleted' => 1], ['node_id' => $nodeId])
			->willReturn(0);

		$this->mediaService->markDeleteMediaByNodeId($nodeId);
	}

	#[Group('units')]
	public function testDeleteMedia(): void
	{
		$mediaId = 'media-1';
		$media = ['id' => $mediaId, 'node_id' => 1];

		$this->mediaRepositoryMock->expects($this->once())->method('findAllWithOwnerById')
			->willReturn($media);
		$this->aclValidatorMock->expects($this->once())->method('checkDirectoryPermissions')
			->willReturn(['edit' => true]);
		$this->mediaRepositoryMock->expects($this->once())->method('updateWithWhere')
			->willReturn(1);

		$result = $this->mediaService->deleteMedia($mediaId);

		static::assertEquals(1, $result);
	}

	#[Group('units')]
	public function testDeleteMediaEmpty(): void
	{
		$mediaId = 'media-1';
		$this->mediaRepositoryMock->expects($this->once())->method('findAllWithOwnerById')
			->willReturn([]);
		$this->aclValidatorMock->expects($this->never())->method('checkDirectoryPermissions');
		$this->mediaRepositoryMock->expects($this->never())->method('updateWithWhere');
		$this->loggerMock->expects($this->once())->method('error')
			->with('Error deleting media: No media found.');

		$result = $this->mediaService->deleteMedia($mediaId);

		static::assertEquals(0, $result);
	}

	#[Group('units')]
	public function testDeleteMediaNoEditPermissionsThrowsException(): void
	{
		$mediaId = 'media-1';
		$media = ['id' => $mediaId, 'node_id' => 1];

		$this->mediaRepositoryMock->method('findAllWithOwnerById')->willReturn($media);
		$this->aclValidatorMock->method('checkDirectoryPermissions')->willReturn(['edit' => false]);

		$this->loggerMock->expects($this->once())->method('error')
			->with('Error deleting media: No edit permissions in this directory: 1');

		static::assertEquals(0, $this->mediaService->deleteMedia($mediaId));
	}

	#[Group('units')]
	public function testMoveMediaMovesMedia(): void
	{
		$mediaId = 'media-1';
		$nodeId = 2;
		$media = ['id' => $mediaId, 'node_id' => 1];
		$node = ['id' => $nodeId, 'name' => 'New Node'];

		$this->mediaRepositoryMock->method('findAllWithOwnerById')->willReturn($media);
		$this->nodesRepositoryMock->method('findNodeOwner')->willReturn($node);
		$this->aclValidatorMock->method('checkDirectoryPermissions')->willReturnOnConsecutiveCalls(['read' => true], ['edit' => true]);
		$this->mediaRepositoryMock->method('updateWithWhere')->willReturn(1);
		$this->loggerMock->expects($this->never())->method('error');

		$result = $this->mediaService->moveMedia($mediaId, $nodeId);

		static::assertEquals(1, $result);
	}

	#[Group('units')]
	public function testMoveMediaNoReadPermissions(): void
	{
		$mediaId = 'media-1';
		$nodeId = 2;
		$media = ['id' => $mediaId, 'node_id' => 1];

		$this->mediaRepositoryMock->method('findAllWithOwnerById')->willReturn($media);
		$this->aclValidatorMock->method('checkDirectoryPermissions')->willReturn(['read' => false]);

		$this->loggerMock->expects($this->once())->method('error')
			->with('Error moving media: No read permissions in this directory: 1');

		$this->mediaService->moveMedia($mediaId, $nodeId);
	}

	#[Group('units')]
	public function testMoveMediaNoEditPermissions(): void
	{
		$mediaId = 'media-1';
		$nodeId = 2;
		$media = ['id' => $mediaId, 'node_id' => 1];
		$node = ['id' => $nodeId, 'name' => 'New Node'];

		$this->mediaRepositoryMock->method('findAllWithOwnerById')->willReturn($media);
		$this->nodesRepositoryMock->method('findNodeOwner')->willReturn($node);

		$this->aclValidatorMock->method('checkDirectoryPermissions')
			->willReturnOnConsecutiveCalls(['read' => true], ['edit' => false]);

		$this->loggerMock->expects($this->once())->method('error')
			->with('Error moving media: No edit permissions in this directory: New Node');

		$this->mediaService->moveMedia($mediaId, $nodeId);
	}

	#[Group('units')]
	public function testCloneMediaClonesMedia(): void
	{
		$mediaId = 'media-1';
		$media = ['id' => $mediaId, 'node_id' => 1];
		$dataSet = [
			'id' => $mediaId, 'node_id' => 1, 'media_id' => 'new-media-id', 'UID' => 1,
			'checksum' => 'checksum', 'extension' => 'jpg', 'thumb_extension' => 'jpg'
		];

		$this->mediaRepositoryMock->method('findAllWithOwnerById')->willReturn($media);
		$this->aclValidatorMock->method('checkDirectoryPermissions')->willReturn(['read' => true, 'edit' => true]);
		$this->mediaRepositoryMock->method('findById')->willReturn([$dataSet]);
		$this->mediaRepositoryMock->method('getFirstDataSet')->willReturn($dataSet);
		$this->mediaRepositoryMock->method('insert')->willReturn('new-media-id');
		$this->loggerMock->expects($this->never())->method('error');
		$this->loggerMock->expects($this->never())->method('error');

		$result = $this->mediaService->cloneMedia($mediaId);

		static::assertEquals('new-media-id', $result['uuid']);
	}

	#[Group('units')]
	public function testCloneMediaNoPermissions(): void
	{
		$mediaId = 'media-1';
		$media = ['id' => $mediaId, 'node_id' => 1];

		$this->mediaRepositoryMock->method('findAllWithOwnerById')->willReturn($media);
		$this->aclValidatorMock->method('checkDirectoryPermissions')->willReturn(['read' => false, 'edit' => false]);

		$this->loggerMock->expects($this->once())->method('error')
			->with('Error cloning media: No permissions on this directory: 1');

		$this->mediaService->cloneMedia($mediaId);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFetchMediaByChecksumReturnsMedia(): void
	{
		$checksum = 'checksum-123';
		$media = [
			'id' => 'media-1',
			'metadata' => json_encode(['key' => 'value']),
			'checksum' => $checksum,
			'extension' => 'jpg'
		];

		$this->mediaRepositoryMock->method('findAllWithOwnerByCheckSum')->willReturn($media);

		$result = $this->mediaService->fetchMediaByChecksum($checksum);

		static::assertEquals(['key' => 'value'], $result['metadata']);
		static::assertEquals($checksum, $result['checksum']);
	}

}
