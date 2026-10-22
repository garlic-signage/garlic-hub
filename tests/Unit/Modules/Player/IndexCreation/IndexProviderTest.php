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

use App\Framework\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Player\Entities\PlayerEntity;
use App\Modules\Player\IndexCreation\IndexCreator;
use App\Modules\Player\IndexCreation\IndexProvider;
use League\Flysystem\FilesystemException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class IndexProviderTest extends TestCase
{
	private Config&Stub $configStub;
	private IndexCreator&MockObject $indexCreatorMock;
	private PlayerEntity&Stub $playerEntityStub;

	private IndexProvider $indexProvider;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->configStub       = $this->createStub(Config::class);
		$this->indexCreatorMock = $this->createMock(IndexCreator::class);

		$this->indexProvider = new IndexProvider($this->configStub, $this->indexCreatorMock);

		$this->playerEntityStub = $this->createStub(PlayerEntity::class);
		$this->configStub->method('getConfigValue')
			->willReturnMap([
				['defaults', 'player', 'SmilDirectories', 'var/www/defaults'],
				['tests', 'player', 'SmilDirectories', 'var/www/tests'],
				['simulations', 'player', 'SmilDirectories', 'var/www/simulate']
			]);

	}


	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testHandleForbidden(): void
	{
		$this->indexCreatorMock->expects($this->never())->method($this->anything());
		$this->indexProvider->handleForbidden();

		$expectedPath = '/var/www/defaults/forbidden.smil';
		static::assertSame($expectedPath, $this->indexProvider->getFilePath());
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testHandleNew(): void
	{
		$this->indexCreatorMock->expects($this->never())->method($this->anything());
		$this->indexProvider->handleNew();

		$expectedPath = '/var/www/defaults/unreleased.smil';
		static::assertSame($expectedPath, $this->indexProvider->getFilePath());
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testHandleUnreleased(): void
	{
		$this->indexCreatorMock->expects($this->never())->method($this->anything());
		$this->indexProvider->handleUnreleased();

		$expectedPath = '/var/www/defaults/unreleased.smil';
		static::assertSame($expectedPath, $this->indexProvider->getFilePath());
	}

	/**
	 * @throws CoreException
	 * @throws FilesystemException
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testHandleReleasedWithoutPlaylist(): void
	{
		$this->indexCreatorMock->expects($this->never())->method($this->anything());
		$this->playerEntityStub->method('getPlaylistId')->willReturn(0);

		$this->indexProvider->handleReleased($this->playerEntityStub);

		$expectedPath = '/var/www/defaults/released.smil';
		static::assertSame($expectedPath, $this->indexProvider->getFilePath());
	}

	/**
	 * @throws CoreException
	 * @throws ModuleException
	 * @throws FilesystemException
	 */
	#[Group('units')]
	public function testHandleReleasedWithPlaylist(): void
	{
		$this->playerEntityStub->method('getPlaylistId')->willReturn(1);
		$this->indexCreatorMock->expects($this->once())->method('createForReleasedPlayer')
			->with($this->playerEntityStub, $this->configStub);

		$this->indexCreatorMock->method('getIndexFilePath')->willReturn('var/www/tests/released.smil');

		$this->indexProvider->handleReleased($this->playerEntityStub);

		$expectedPath = '/var/www/tests/released.smil';
		static::assertSame($expectedPath, $this->indexProvider->getFilePath());
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testHandleTestSMil(): void
	{
		$this->indexCreatorMock->expects($this->never())->method($this->anything());
		$this->indexProvider->handleTestSMil();

		$expectedPath = '/var/www/tests/index.smil';
		static::assertSame($expectedPath, $this->indexProvider->getFilePath());
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testHandleCorrectSMil(): void
	{
		$this->indexCreatorMock->expects($this->never())->method($this->anything());
		$this->indexProvider->handleCorrectSMil();

		$expectedPath = '/var/www/simulate/without_errors.smil';
		static::assertSame($expectedPath, $this->indexProvider->getFilePath());
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testHandleCorruptSMil(): void
	{
		$this->indexCreatorMock->expects($this->never())->method($this->anything());
		$this->indexProvider->handleCorruptSMIL();

		$expectedPath = '/var/www/simulate/broken_index.smil';
		static::assertSame($expectedPath, $this->indexProvider->getFilePath());
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testHandleCorruptContent(): void
	{
		$this->indexCreatorMock->expects($this->never())->method($this->anything());
		$this->indexProvider->handleCorruptContent();

		$expectedPath = '/var/www/simulate/unreachable_content.smil';
		static::assertSame($expectedPath, $this->indexProvider->getFilePath());
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testHandleCorruptPrefetch(): void
	{
		$this->indexCreatorMock->expects($this->never())->method($this->anything());
		$this->indexProvider->handleCorruptPrefetchContent();

		$expectedPath = '/var/www/simulate/unreachable_prefetch_content.smil';
		static::assertSame($expectedPath, $this->indexProvider->getFilePath());
	}


}
