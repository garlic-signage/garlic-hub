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

namespace Tests\Unit\Modules\Player\IndexCreation;

use App\Framework\Core\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\TemplateEngine\AdapterInterface;
use App\Modules\Player\Entities\PlayerEntity;
use App\Modules\Player\Enums\TemplateIndexFiles;
use App\Modules\Player\IndexCreation\Builder\TemplatePreparer;
use App\Modules\Player\IndexCreation\IndexCreator;
use App\Modules\Player\IndexCreation\IndexFile;
use App\Modules\Player\IndexCreation\IndexTemplateSelector;
use App\Modules\Playlists\Collector\Builder\PlaylistBuilderFactory;
use App\Modules\Playlists\Collector\Contracts\PlaylistBuilderInterface;
use App\Modules\Playlists\Collector\Contracts\PlaylistStructureInterface;
use League\Flysystem\FilesystemException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class IndexCreatorTest extends TestCase
{
	private PlaylistBuilderFactory&MockObject $playlistBuilderFactoryMock;
	private IndexTemplateSelector&MockObject $templateSelectorMock;
	private IndexFile&MockObject $indexFileMock;
	private TemplatePreparer&MockObject $templatePreparerMock;
	private AdapterInterface&MockObject $templateServiceMock;
	private IndexCreator $indexCreator;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->playlistBuilderFactoryMock = $this->createMock(PlaylistBuilderFactory::class);
		$this->templateSelectorMock       = $this->createMock(IndexTemplateSelector::class);
		$this->indexFileMock              = $this->createMock(IndexFile::class);
		$this->templatePreparerMock       = $this->createMock(TemplatePreparer::class);
		$this->templateServiceMock        = $this->createMock(AdapterInterface::class);
		$this->indexCreator               = new IndexCreator(
			$this->playlistBuilderFactoryMock,
			$this->templateSelectorMock,
			$this->indexFileMock,
			$this->templatePreparerMock,
			$this->templateServiceMock
		);
	}

	/**
	 * @throws Exception
	 * @throws CoreException
	 * @throws ModuleException
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testCreateForReleasedPlayerProcessesCorrectly(): void
	{
		parent::setUp();
		$playerEntityStub      = $this->createStub(PlayerEntity::class);
		$configMock            = $this->createMock(Config::class);
		$playlistStructureStub = $this->createStub(PlaylistStructureInterface::class);
		$playlistBuilderMock   = $this->createMock(PlaylistBuilderInterface::class);

		$configMock->method('getConfigValue')
			->with('path_smil_index', 'player')
			->willReturn('/path/to/index');
		$playerEntityStub->method('getUuid')->willReturn('uuid123');
		$playerEntityStub->method('getPlaylistId')->willReturn(456);

		$this->playlistBuilderFactoryMock->expects($this->once())->method('createBuilder')
			->with($playerEntityStub)
			->willReturn($playlistBuilderMock);

		$playlistBuilderMock->expects($this->once())->method('buildPlaylist')
			->willReturn($playlistStructureStub);

		$this->templateSelectorMock->expects($this->once())->method('select')
			->with($playerEntityStub)
			->willReturn(TemplateIndexFiles::GARLIC);

		$this->templatePreparerMock->expects($this->once())->method('setPlayerEntity')
			->with($playerEntityStub)
			->willReturnSelf();
		$this->templatePreparerMock->expects($this->once())->method('setPlaylistStructure')
			->with($playlistStructureStub)
			->willReturnSelf();
		$this->templatePreparerMock->expects($this->once())->method('prepare')
			->with(TemplateIndexFiles::GARLIC)
			->willReturnSelf();
		$this->templatePreparerMock->expects($this->once())->method('getTemplateData')
			->willReturn(['key' => 'value']);

		$this->templateServiceMock->expects($this->once())->method('render')
			->with('player/index/' . TemplateIndexFiles::GARLIC->value, ['key' => 'value'])
			->willReturn('smil_content');

		$filepath = '/path/to/index/uuid123/456.smil';
		$this->indexFileMock->expects($this->once())
			->method('setIndexFilePath')
			->with($filepath)
			->willReturnSelf();

		$this->indexFileMock->expects($this->once())
			->method('handleIndexFile')
			->with('smil_content');

		$this->indexCreator->createForReleasedPlayer($playerEntityStub, $configMock);

		static::assertSame($filepath, $this->indexCreator->getIndexFilePath());
	}
}
