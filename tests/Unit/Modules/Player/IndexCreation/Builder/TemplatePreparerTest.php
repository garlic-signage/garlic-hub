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

namespace Tests\Unit\Modules\Player\IndexCreation\Builder;

use App\Modules\Player\Entities\PlayerEntity;
use App\Modules\Player\Enums\IndexSections;
use App\Modules\Player\Enums\TemplateIndexFiles;
use App\Modules\Player\IndexCreation\Builder\Preparers\LayoutPreparer;
use App\Modules\Player\IndexCreation\Builder\Preparers\MetaPreparer;
use App\Modules\Player\IndexCreation\Builder\Preparers\PlaylistPreparer;
use App\Modules\Player\IndexCreation\Builder\Preparers\PreparerFactory;
use App\Modules\Player\IndexCreation\Builder\Preparers\ScreenTimesPreparer;
use App\Modules\Player\IndexCreation\Builder\Preparers\SubscriptionPreparer;
use App\Modules\Player\IndexCreation\Builder\TemplatePreparer;
use App\Modules\Playlists\Collector\Contracts\PlaylistStructureInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class TemplatePreparerTest extends TestCase
{
	private PlayerEntity&Stub $playerEntityStub;
	private PlaylistStructureInterface&Stub $playlistStructureStub;
	private PreparerFactory&MockObject $preparerFactoryMock;
	private TemplatePreparer $templatePreparer;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->playerEntityStub = $this->createStub(PlayerEntity::class);
		$this->playlistStructureStub = $this->createStub(PlaylistStructureInterface::class);
		$this->preparerFactoryMock = $this->createMock(PreparerFactory::class);

		$this->templatePreparer = new TemplatePreparer($this->preparerFactoryMock);
		$this->templatePreparer->setPlayerEntity($this->playerEntityStub);
		$this->templatePreparer->setPlaylistStructure($this->playlistStructureStub);
	}

	/**
	 * @throws Exception
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testPrepareForGarlic(): void
	{
		parent::setUp();
		$metaMock         = $this->createMock(MetaPreparer::class);
		$subscriptionMock = $this->createMock(SubscriptionPreparer::class);
		$layoutMock       = $this->createMock(LayoutPreparer::class);
		$screenTimesMock  = $this->createMock(ScreenTimesPreparer::class);
		$playlistMock     = $this->createMock(PlaylistPreparer::class);

		$this->preparerFactoryMock->expects($this->atLeastOnce())
			->method('create')
			->willReturnMap([
				[IndexSections::META, $this->playerEntityStub, $metaMock],
				[IndexSections::SUBSCRIPTIONS, $this->playerEntityStub, $subscriptionMock],
				[IndexSections::LAYOUT, $this->playerEntityStub, $layoutMock],
				[IndexSections::STANDBY_TIMES, $this->playerEntityStub, $screenTimesMock],
				[IndexSections::PLAYLIST, $this->playerEntityStub, $playlistMock],
			]);
		$metaMock->expects($this->once())->method('prepare')->willReturn(['meta']);
		$subscriptionMock->expects($this->once())->method('prepare')->willReturn(['subscriptions']);
		$layoutMock->expects($this->once())->method('prepare')->willReturn(['layout']);
		$screenTimesMock->expects($this->once())->method('prepare')->willReturn(['standby']);
		$playlistMock->expects($this->once())->method('setPlaylistStructure')
			->with($this->playlistStructureStub)
			->willReturnSelf();
		$playlistMock->expects($this->once())->method('prepare')->willReturn(['playlist']);

		$this->templatePreparer->prepare(TemplateIndexFiles::GARLIC);

		$templateData = $this->templatePreparer->getTemplateData();
		static::assertEquals([
			'meta' => ['meta'],
			'subscriptions' => ['subscriptions'],
			'layout' => ['layout'],
			'standby_times' => ['standby'],
			'playlist' => ['playlist'],
		], $templateData);
	}

	/**
	 * @throws Exception
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testPrepareForIAdea(): void
	{
		$metaMock         = $this->createMock(MetaPreparer::class);
		$subscriptionMock = $this->createMock(SubscriptionPreparer::class);
		$layoutMock       = $this->createMock(LayoutPreparer::class);
		$screenTimesMock  = $this->createMock(ScreenTimesPreparer::class);
		$playlistMock     = $this->createMock(PlaylistPreparer::class);

		$this->preparerFactoryMock->expects($this->atLeastOnce())
			->method('create')
			->willReturnMap([
				[IndexSections::META, $this->playerEntityStub, $metaMock],
				[IndexSections::SUBSCRIPTIONS, $this->playerEntityStub, $subscriptionMock],
				[IndexSections::LAYOUT, $this->playerEntityStub, $layoutMock],
				[IndexSections::STANDBY_TIMES, $this->playerEntityStub, $screenTimesMock],
				[IndexSections::PLAYLIST, $this->playerEntityStub, $playlistMock],
			]);
		$metaMock->expects($this->once())->method('prepare')->willReturn(['meta']);
		$subscriptionMock->expects($this->once())->method('prepare')->willReturn(['subscriptions']);
		$layoutMock->expects($this->once())->method('prepare')->willReturn(['layout']);
		$screenTimesMock->expects($this->once())->method('prepare')->willReturn(['standby']);
		$playlistMock->expects($this->once())->method('setPlaylistStructure')
			->with($this->playlistStructureStub)
			->willReturnSelf();
		$playlistMock->expects($this->once())->method('prepare')->willReturn(['playlist']);

		$this->templatePreparer->prepare(TemplateIndexFiles::XMP2XXX);

		$templateData = $this->templatePreparer->getTemplateData();
		static::assertEquals([
			'meta' => ['meta'],
			'subscriptions' => ['subscriptions'],
			'layout' => ['layout'],
			'standby_times' => ['standby'],
			'playlist' => ['playlist'],
		], $templateData);
	}

	/**
	 * @throws Exception
	 * @throws \Exception
	 */
	#[Group('units')]
	public function testPrepareForCompatible(): void
	{
		$metaMock         = $this->createMock(MetaPreparer::class);
		$layoutMock       = $this->createMock(LayoutPreparer::class);
		$playlistMock     = $this->createMock(PlaylistPreparer::class);

		$this->preparerFactoryMock->expects($this->atLeastOnce())
			->method('create')
			->willReturnMap([
				[IndexSections::META, $this->playerEntityStub, $metaMock],
				[IndexSections::LAYOUT, $this->playerEntityStub, $layoutMock],
				[IndexSections::PLAYLIST, $this->playerEntityStub, $playlistMock],
			]);
		$metaMock->expects($this->once())->method('prepare')->willReturn(['meta']);
		$layoutMock->expects($this->once())->method('prepare')->willReturn(['layout']);
		$playlistMock->expects($this->once())->method('setPlaylistStructure')
			->with($this->playlistStructureStub)
			->willReturnSelf();
		$playlistMock->expects($this->once())->method('prepare')->willReturn(['playlist']);

		$this->templatePreparer->prepare(TemplateIndexFiles::SIMPLE);

		$templateData = $this->templatePreparer->getTemplateData();
		static::assertEquals([
			'meta' => ['meta'],
			'layout' => ['layout'],
			'playlist' => ['playlist'],
		], $templateData);
	}


}
