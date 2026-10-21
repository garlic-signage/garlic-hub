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

namespace Tests\Unit\Modules\Playlists\Services\InsertItems;

use App\Framework\Core\Config\Config;
use App\Framework\Database\BaseRepositories\Transactions;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Media\MediaUrlSigner;
use App\Modules\Auth\UserSession;
use App\Modules\Playlists\Repositories\ItemsRepository;
use App\Modules\Playlists\Services\InsertItems\Template;
use App\Modules\Playlists\Services\PlaylistMetricsCalculator;
use App\Modules\Playlists\Services\PlaylistsService;
use App\Modules\Templates\Services\TemplatesService;
use League\Flysystem\Filesystem;
use League\Flysystem\UnableToCopyFile;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TemplateTest extends TestCase
{
	private ItemsRepository&MockObject $itemsRepositoryMock;
	private Transactions&MockObject $transactionsMock;
	private TemplatesService&MockObject $templatesServiceMock;
	private Filesystem&MockObject $fileSystemMock;
	private PlaylistsService&MockObject $playlistsServiceMock;
	private PlaylistMetricsCalculator&MockObject $playlistMetricsCalculatorMock;
	private Template $template;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->itemsRepositoryMock           = $this->createMock(ItemsRepository::class);
		$this->transactionsMock              = $this->createMock(Transactions::class);
		$this->templatesServiceMock          = $this->createMock(TemplatesService::class);
		$this->fileSystemMock                = $this->createMock(Filesystem::class);
		$this->playlistsServiceMock          = $this->createMock(PlaylistsService::class);
		$this->playlistMetricsCalculatorMock = $this->createMock(PlaylistMetricsCalculator::class);

		$configStub = $this->createStub(Config::class);
		$configStub->method('getConfigValue')->willReturn('/path');
		$userSessionStub = $this->createStub(UserSession::class);
		$userSessionStub->method('getUID')->willReturn(1);

		$this->template = new Template(
			$this->itemsRepositoryMock,
			$this->transactionsMock,
			$this->templatesServiceMock,
			$configStub,
			$this->fileSystemMock,
			new MediaUrlSigner('unit-test-secret'),
			$this->playlistsServiceMock,
			$this->playlistMetricsCalculatorMock,
			$userSessionStub,
			$this->createStub(LoggerInterface::class)
		);
	}

	/**
	 * The copy of the thumbnail happens inside the transaction, so a failing copy must roll back the insert.
	 */
	#[Group('units')]
	public function testInsertRollsBackWhenCopyFails(): void
	{
		$this->playlistsServiceMock->expects($this->once())->method('loadPlaylistForEdit')->with(1)
			->willReturn(['playlist_id' => 1, 'time_limit' => 0]);
		$this->templatesServiceMock->expects($this->once())->method('loadWithUserById')->with(7)
			->willReturn(['name' => 'Template', 'content' => '{}']);
		$this->playlistMetricsCalculatorMock->expects($this->once())->method('calculateRemainingDuration')->willReturn(10);
		$this->playlistMetricsCalculatorMock->expects($this->never())->method('calculateFromPlaylistData');
		$this->transactionsMock->expects($this->once())->method('begin');
		$this->itemsRepositoryMock->expects($this->once())->method('updatePositionsWhenInserted')->with(1, 2);
		$this->itemsRepositoryMock->expects($this->once())->method('insert')->willReturn(5);
		$this->fileSystemMock->expects($this->once())->method('copy')
			->willThrowException(UnableToCopyFile::fromLocationTo('/path/7.jpg', '/path/5.jpg'));
		$this->transactionsMock->expects($this->once())->method('rollBack');
		$this->transactionsMock->expects($this->never())->method('commit');

		$this->expectException(UnableToCopyFile::class);
		$this->template->insert(1, 7, 2);
	}

	#[Group('units')]
	public function testInsertRollsBackWhenTemplateNotAccessible(): void
	{
		$this->playlistsServiceMock->expects($this->once())->method('loadPlaylistForEdit')->with(1)
			->willReturn(['playlist_id' => 1, 'time_limit' => 0]);
		$this->templatesServiceMock->expects($this->once())->method('loadWithUserById')->with(7)
			->willThrowException(new ModuleException('templates', 'Template is not editable.'));
		$this->playlistMetricsCalculatorMock->expects($this->never())->method($this->anything());
		$this->fileSystemMock->expects($this->never())->method($this->anything());
		$this->transactionsMock->expects($this->once())->method('begin');
		$this->itemsRepositoryMock->expects($this->never())->method('insert');
		$this->transactionsMock->expects($this->once())->method('rollBack');

		$this->expectException(ModuleException::class);
		$this->template->insert(1, 7, 2);
	}
}
