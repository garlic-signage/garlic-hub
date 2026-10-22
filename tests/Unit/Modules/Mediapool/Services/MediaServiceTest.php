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

use App\Framework\Config\Config;
use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Media\MediaUrlSigner;
use App\Modules\Auth\UserSession;
use App\Modules\Mediapool\Repositories\FilesRepository;
use App\Modules\Mediapool\Repositories\NodesRepository;
use App\Modules\Mediapool\Services\AclValidator;
use App\Modules\Mediapool\Services\MediaService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class MediaServiceTest extends TestCase
{

	private MediaService $mediaService;
	private FilesRepository&MockObject $mediaRepositoryMock;
	private NodesRepository&Stub $nodesRepositoryStub;
	private AclValidator&MockObject $aclValidatorMock;

	/**
	 * @throws Exception
	 * @throws CoreException
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->mediaRepositoryMock = $this->createMock(FilesRepository::class);
		$this->nodesRepositoryStub = $this->createStub(NodesRepository::class);
		$this->aclValidatorMock    = $this->createMock(AclValidator::class);
		$configStub = $this->createStub(Config::class);
		$userSessionStub = $this->createStub(UserSession::class);
		$userSessionStub->method('getUID')->willReturn(1);
		$this->aclValidatorMock->method('getConfig')->willReturn($configStub);
		$configStub->method('getConfigValue')->willReturnMap([
				['originals', 'mediapool', 'directories', 'original_directory'],
				['previews', 'mediapool', 'directories', 'preview_directory'],
				['thumbnails', 'mediapool', 'directories', 'thumbnail_directory'],
			]
		);

		$this->mediaService = new MediaService(
			$this->mediaRepositoryMock,
			$this->nodesRepositoryStub,
			$this->aclValidatorMock,
			new MediaUrlSigner('unit-test-secret'),
			$userSessionStub
		);
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

		$this->nodesRepositoryStub->method('findNodeOwner')->willReturn($node);
		$this->aclValidatorMock->expects($this->atLeastOnce())->method('checkDirectoryPermissions')->willReturn(['read' => true]);
		$this->mediaRepositoryMock->expects($this->atLeastOnce())->method('findAllByNodeId')->willReturn($mediaList);

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
		$this->mediaRepositoryMock->expects($this->never())->method($this->anything());
		$nodeId = 1;
		$node = ['id' => $nodeId, 'name' => 'Test Node'];

		$this->nodesRepositoryStub->method('findNodeOwner')->willReturn($node);
		$this->aclValidatorMock->expects($this->atLeastOnce())->method('checkDirectoryPermissions')->willReturn(['read' => false]);
		$this->expectException(AccessDeniedException::class);
		$this->expectExceptionMessage('directory_not_readable');

		$this->mediaService->listMedia($nodeId);
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

		$this->mediaRepositoryMock->expects($this->atLeastOnce())->method('findAllWithOwnerById')->willReturn($media);
		$this->aclValidatorMock->expects($this->atLeastOnce())->method('checkDirectoryPermissions')->willReturn(['read' => true]);

		$result = $this->mediaService->fetchMedia($mediaId);

		static::assertEquals(['key' => 'value'], $result['metadata']);
		static::assertStringStartsWith('/media.php/thumb/checksum.thumb_extension?exp=', $result['urls']['thumbnail']);
	}

	#[Group('units')]
	public function testFetchMediaNoReadPermissions(): void
	{
		$mediaId = 'media-1';
		$media = ['id' => $mediaId, 'metadata' => json_encode(['key' => 'value']), 'node_id' => 1];

		$this->mediaRepositoryMock->expects($this->atLeastOnce())->method('findAllWithOwnerById')->willReturn($media);
		$this->aclValidatorMock->expects($this->atLeastOnce())->method('checkDirectoryPermissions')->willReturn(['read' => false]);
		$this->expectException(AccessDeniedException::class);
		$this->expectExceptionMessage('directory_not_readable');

		$this->mediaService->fetchMedia($mediaId);
	}

	#[Group('units')]
	public function testUpdateMediaUpdatesMedia(): void
	{
		$mediaId = 'media-1';
		$filename = 'new-filename';
		$description = 'new-description';
		$media = ['id' => $mediaId, 'node_id' => 1];

		$this->mediaRepositoryMock->expects($this->atLeastOnce())->method('findAllWithOwnerById')->willReturn($media);
		$this->aclValidatorMock->expects($this->atLeastOnce())->method('checkDirectoryPermissions')->willReturn(['edit' => true]);
		$this->mediaRepositoryMock->expects($this->atLeastOnce())->method('update')->willReturn(1);

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

		$this->mediaRepositoryMock->expects($this->atLeastOnce())->method('findAllWithOwnerById')->willReturn($media);
		$this->aclValidatorMock->expects($this->atLeastOnce())->method('checkDirectoryPermissions')->willReturn(['edit' => false]);

		$this->expectException(AccessDeniedException::class);
		$this->expectExceptionMessage('directory_not_editable');

		$this->mediaService->updateMedia($mediaId, $filename, $description);
	}

	#[Group('units')]
	public function testMarkDeleteMediaByNodeIdUpdatesMedia(): void
	{
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
		$nodeId = 1;

		$this->mediaRepositoryMock->expects($this->once())->method('updateWithWhere')
			->with(['deleted' => 1], ['node_id' => $nodeId])
			->willReturn(1);

		$this->mediaService->markDeleteMediaByNodeId($nodeId);
	}

	#[Group('units')]
	public function testMarkDeleteMediaByNodeIdHandlesNoMedia(): void
	{
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
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
	public function testDeleteMediaNotFound(): void
	{
		$mediaId = 'media-1';
		$this->mediaRepositoryMock->expects($this->once())->method('findAllWithOwnerById')
			->willReturn([]);
		$this->aclValidatorMock->expects($this->never())->method('checkDirectoryPermissions');
		$this->mediaRepositoryMock->expects($this->never())->method('updateWithWhere');
		$this->expectException(NotFoundException::class);
		$this->expectExceptionMessage('media_not_found');

		$this->mediaService->deleteMedia($mediaId);
	}

	#[Group('units')]
	public function testDeleteMediaNoEditPermissionsThrowsException(): void
	{
		$mediaId = 'media-1';
		$media = ['id' => $mediaId, 'node_id' => 1];

		$this->mediaRepositoryMock->expects($this->atLeastOnce())->method('findAllWithOwnerById')->willReturn($media);
		$this->aclValidatorMock->expects($this->atLeastOnce())->method('checkDirectoryPermissions')->willReturn(['edit' => false]);

		$this->expectException(AccessDeniedException::class);
		$this->expectExceptionMessage('directory_not_editable');

		$this->mediaService->deleteMedia($mediaId);
	}

	#[Group('units')]
	public function testMoveMediaMovesMedia(): void
	{
		$mediaId = 'media-1';
		$nodeId = 2;
		$media = ['id' => $mediaId, 'node_id' => 1];
		$node = ['id' => $nodeId, 'name' => 'New Node'];

		$this->mediaRepositoryMock->expects($this->atLeastOnce())->method('findAllWithOwnerById')->willReturn($media);
		$this->nodesRepositoryStub->method('findNodeOwner')->willReturn($node);
		$this->aclValidatorMock->expects($this->atLeastOnce())->method('checkDirectoryPermissions')->willReturnOnConsecutiveCalls(['read' => true], ['edit' => true]);
		$this->mediaRepositoryMock->expects($this->atLeastOnce())->method('updateWithWhere')->willReturn(1);

		$result = $this->mediaService->moveMedia($mediaId, $nodeId);

		static::assertEquals(1, $result);
	}

	#[Group('units')]
	public function testMoveMediaNoReadPermissions(): void
	{
		$mediaId = 'media-1';
		$nodeId = 2;
		$media = ['id' => $mediaId, 'node_id' => 1];

		$this->mediaRepositoryMock->expects($this->atLeastOnce())->method('findAllWithOwnerById')->willReturn($media);
		$this->aclValidatorMock->expects($this->atLeastOnce())->method('checkDirectoryPermissions')->willReturn(['read' => false]);

		$this->expectException(AccessDeniedException::class);
		$this->expectExceptionMessage('directory_not_readable');

		$this->mediaService->moveMedia($mediaId, $nodeId);
	}

	#[Group('units')]
	public function testMoveMediaNoEditPermissions(): void
	{
		$mediaId = 'media-1';
		$nodeId = 2;
		$media = ['id' => $mediaId, 'node_id' => 1];
		$node = ['id' => $nodeId, 'name' => 'New Node'];

		$this->mediaRepositoryMock->expects($this->atLeastOnce())->method('findAllWithOwnerById')->willReturn($media);
		$this->nodesRepositoryStub->method('findNodeOwner')->willReturn($node);

		$this->aclValidatorMock->expects($this->atLeastOnce())->method('checkDirectoryPermissions')
			->willReturnOnConsecutiveCalls(['read' => true], ['edit' => false]);

		$this->expectException(AccessDeniedException::class);
		$this->expectExceptionMessage('directory_not_editable');

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

		$this->mediaRepositoryMock->expects($this->atLeastOnce())->method('findAllWithOwnerById')->willReturn($media);
		$this->aclValidatorMock->expects($this->atLeastOnce())->method('checkDirectoryPermissions')->willReturn(['read' => true, 'edit' => true]);
		$this->mediaRepositoryMock->expects($this->atLeastOnce())->method('findById')->willReturn([$dataSet]);
		$this->mediaRepositoryMock->expects($this->atLeastOnce())->method('getFirstDataSet')->willReturn($dataSet);
		$this->mediaRepositoryMock->expects($this->atLeastOnce())->method('insert')->willReturn('new-media-id');

		$result = $this->mediaService->cloneMedia($mediaId);

		static::assertEquals('new-media-id', $result['uuid']);
	}

	#[Group('units')]
	public function testCloneMediaNoPermissions(): void
	{
		$mediaId = 'media-1';
		$media = ['id' => $mediaId, 'node_id' => 1];

		$this->mediaRepositoryMock->expects($this->atLeastOnce())->method('findAllWithOwnerById')->willReturn($media);
		$this->aclValidatorMock->expects($this->atLeastOnce())->method('checkDirectoryPermissions')->willReturn(['read' => false, 'edit' => false]);

		$this->expectException(AccessDeniedException::class);
		$this->expectExceptionMessage('directory_not_editable');

		$this->mediaService->cloneMedia($mediaId);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFetchMediaByChecksumReturnsMedia(): void
	{
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
		$checksum = 'checksum-123';
		$media = [
			'id' => 'media-1',
			'metadata' => json_encode(['key' => 'value']),
			'checksum' => $checksum,
			'extension' => 'jpg'
		];

		$this->mediaRepositoryMock->expects($this->atLeastOnce())->method('findAllWithOwnerByCheckSum')->willReturn($media);

		$result = $this->mediaService->fetchMediaByChecksum($checksum);

		static::assertEquals(['key' => 'value'], $result['metadata']);
		static::assertEquals($checksum, $result['checksum']);
	}

	/**
	 * An unknown checksum must stay empty, otherwise callers treat the media as found.
	 *
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testFetchMediaByChecksumWithUnknownChecksum(): void
	{
		$this->aclValidatorMock->expects($this->never())->method($this->anything());
		$this->mediaRepositoryMock->expects($this->once())->method('findAllWithOwnerByCheckSum')
			->with('unknown')
			->willReturn([]);

		static::assertSame([], $this->mediaService->fetchMediaByChecksum('unknown'));
	}
}
