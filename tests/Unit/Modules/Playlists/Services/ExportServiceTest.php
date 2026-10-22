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

namespace Tests\Unit\Modules\Playlists\Services;

use App\Framework\Config\Config;
use App\Framework\Database\BaseRepositories\Transactions;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Modules\Playlists\Helper\ExportSmil\LocalWriter;
use App\Modules\Playlists\Helper\ExportSmil\PlaylistContent;
use App\Modules\Playlists\Helper\PlaylistMetrics;
use App\Modules\Playlists\Services\ExportService;
use App\Modules\Playlists\Services\ExportItemsLoader;
use App\Modules\Playlists\Services\PlaylistMetricsUpdater;
use App\Modules\Playlists\Services\PlaylistsService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ExportServiceTest extends TestCase
{
	private Config&Stub $configStub;
	private PlaylistsService&MockObject $playlistsServiceMock;
	private ExportItemsLoader&MockObject $exportItemsLoaderMock;
	private PlaylistMetricsUpdater&MockObject $playlistMetricsUpdaterMock;
	private LocalWriter&MockObject $localSmilWriterMock;
	private PlaylistContent&MockObject $playlistContentMock;
	private LoggerInterface&MockObject $loggerMock;
	private Transactions&MockObject $transactionsMock;
	private ExportService $service;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->configStub           = $this->createStub(Config::class);
		$this->playlistsServiceMock = $this->createMock(PlaylistsService::class);
		$this->exportItemsLoaderMock = $this->createMock(ExportItemsLoader::class);
		$this->playlistMetricsUpdaterMock = $this->createMock(PlaylistMetricsUpdater::class);
		$this->localSmilWriterMock  = $this->createMock(LocalWriter::class);
		$this->playlistContentMock  = $this->createMock(PlaylistContent::class);
		$this->loggerMock           = $this->createMock(LoggerInterface::class);
		$this->transactionsMock     = $this->createMock(Transactions::class);

		$this->service = new ExportService(
			$this->configStub,
			$this->playlistsServiceMock,
			$this->exportItemsLoaderMock,
			$this->playlistMetricsUpdaterMock,
			$this->transactionsMock,
			$this->localSmilWriterMock,
			$this->playlistContentMock,
			$this->loggerMock);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testExportToSmilSingleMode(): void
	{
		$playlistId = 123;
		$playlist = ['playlist_id' => 123, 'playlist_mode' => 'master'];
		$metrics = new PlaylistMetrics(fileSize: 100, duration: 60, ownerDuration: 60);
		$result = ['items' => [], 'playlist_metrics' => $metrics];
		$this->transactionsMock->expects($this->once())->method('begin');

		$this->playlistsServiceMock->method('loadPureById')
			->with($playlistId)
			->willReturn($playlist);

		$this->configStub->method('getEdition')->willReturn(Config::PLATFORM_EDITION_EDGE);
		$this->exportItemsLoaderMock->expects($this->once())->method('loadByPlaylist')
			->with($playlist, Config::PLATFORM_EDITION_EDGE)
			->willReturn($result);
		$this->playlistMetricsUpdaterMock->expects($this->once())->method('updateItemsMetrics')
			->with($playlist['playlist_id'], $metrics);
		$this->playlistMetricsUpdaterMock->expects($this->once())->method('updateMetricsRecursively')
			->with($playlist['playlist_id']);

		$this->playlistContentMock->method('init')->with($playlist, $result['items'])->willReturnSelf();
		$this->playlistContentMock->expects($this->once())->method('build');

		$this->localSmilWriterMock->method('initExport')
			->with($playlist['playlist_id']);
		$this->localSmilWriterMock->method('writeSMILFiles')->with($this->playlistContentMock);
		$this->playlistsServiceMock->method('updateExport')
			->with($playlist['playlist_id'], $metrics->toPlaylistTableArray())
			->willReturn(1);

		$this->transactionsMock->expects($this->once())->method('commit');
		$this->transactionsMock->expects($this->never())->method('rollBack');
		$this->loggerMock->expects($this->never())->method('error');
		$result = $this->service->exportToSmil($playlistId);


		static::assertSame(1, $result);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testExportToSmilSingleModeFailed(): void
	{
		$this->loggerMock->expects($this->never())->method($this->anything());
		$playlistId = 123;
		$playlist = ['playlist_id' => 123, 'playlist_mode' => 'master'];
		$metrics = new PlaylistMetrics(fileSize: 100, duration: 60, ownerDuration: 60);
		$result = ['items' => [], 'playlist_metrics' => $metrics];
		$this->transactionsMock->expects($this->once())->method('begin');

		$this->playlistsServiceMock->method('loadPureById')
			->with($playlistId)
			->willReturn($playlist);

		$this->configStub->method('getEdition')->willReturn(Config::PLATFORM_EDITION_EDGE);
		$this->exportItemsLoaderMock->expects($this->once())->method('loadByPlaylist')
			->with($playlist, Config::PLATFORM_EDITION_EDGE)
			->willReturn($result);
		$this->playlistMetricsUpdaterMock->expects($this->once())->method('updateItemsMetrics')
			->with($playlist['playlist_id'], $metrics);
		$this->playlistMetricsUpdaterMock->expects($this->once())->method('updateMetricsRecursively')
			->with($playlist['playlist_id']);

		$this->playlistContentMock->method('init')->with($playlist, $result['items'])->willReturnSelf();
		$this->playlistContentMock->expects($this->once())->method('build');

		$this->localSmilWriterMock->method('initExport')
			->with($playlist['playlist_id']);
		$this->localSmilWriterMock->method('writeSMILFiles')->with($this->playlistContentMock);

		$this->playlistsServiceMock->method('updateExport')
			->with($playlist['playlist_id'], $metrics->toPlaylistTableArray())
			->willReturn(0);

		$this->transactionsMock->expects($this->never())->method('commit');
		$this->transactionsMock->expects($this->once())->method('rollBack');

		$this->expectException(TranslatableModuleException::class);
		$this->expectExceptionMessage('playlist_export_failed');
		$this->service->exportToSmil($playlistId);
	}

	/**
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testExportToSmilMultizoneMode(): void
	{
		$playlistId = 123;

		$multizone = [
			['zones' => ['zone_playlist_id' => 11]],
			['zones' =>	['zone_playlist_id' => 12]]
		];
		$playlist  = ['playlist_id' => 123, 'playlist_mode' => 'multizone', 'multizone' => serialize($multizone)];
		$playlistSub11  = ['playlist_id' => 11, 'playlist_mode' => 'master'];
		$playlistSub12  = ['playlist_id' => 12, 'playlist_mode' => 'master'];

		$metrics   = new PlaylistMetrics(fileSize: 100, duration: 60, ownerDuration: 60);
		$results   = ['items' => [], 'playlist_metrics' => $metrics];

		$this->transactionsMock->expects($this->once())->method('begin');

		$this->playlistsServiceMock->expects($this->exactly(3))->method('loadPureById')
			->willReturnMap([
				[123, $playlist],
				[11, $playlistSub11],
				[12, $playlistSub12]
			]);

		$this->configStub->method('getEdition')->willReturn(Config::PLATFORM_EDITION_EDGE);
		$this->exportItemsLoaderMock->expects($this->exactly(2))->method('loadByPlaylist')
			->willReturnMap([
				[$playlistSub11, Config::PLATFORM_EDITION_EDGE, $results],
				[$playlistSub12, Config::PLATFORM_EDITION_EDGE, $results]
			]);
		$updatedItems = [];
		$this->playlistMetricsUpdaterMock->expects($this->exactly(2))->method('updateItemsMetrics')
			->willReturnCallback(function (int $playlistId, PlaylistMetrics $itemMetrics) use (&$updatedItems, $metrics): void
			{
				static::assertSame($metrics, $itemMetrics);
				$updatedItems[] = $playlistId;
			});
		$recursivelyUpdated = [];
		$this->playlistMetricsUpdaterMock->expects($this->exactly(2))->method('updateMetricsRecursively')
			->willReturnCallback(function (int $playlistId) use (&$recursivelyUpdated): void
			{
				$recursivelyUpdated[] = $playlistId;
			});

		$this->playlistContentMock->expects($this->exactly(2))->method('init')
			->willReturnMap([
				[$playlistSub11, $results['items'], $this->playlistContentMock],
				[$playlistSub12, $results['items'], $this->playlistContentMock],
			]);
		$this->playlistContentMock->expects($this->exactly(2))->method('build');

		$this->localSmilWriterMock->expects($this->exactly(2))->method('initExport')
			->willReturnMap([
				[11, null],
				[12, null]
			]);
		$this->localSmilWriterMock->expects($this->exactly(2))->method('writeSMILFiles')
			->with($this->playlistContentMock);

		$this->playlistsServiceMock->method('updateExport')
			->with($playlist['playlist_id'], $metrics->toPlaylistTableArray())
			->willReturn(1);

		$this->transactionsMock->expects($this->once())->method('commit');
		$this->transactionsMock->expects($this->never())->method('rollBack');
		$this->loggerMock->expects($this->never())->method('error');
		$result = $this->service->exportToSmil($playlistId);

		static::assertSame(2, $result);
		static::assertSame([11, 12], $updatedItems);
		static::assertSame([11, 12], $recursivelyUpdated);
	}


}

