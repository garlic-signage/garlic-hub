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

use App\Modules\Player\Enums\TemplateIndexFiles;
use App\Modules\Player\IndexCreation\Builder\Preparers\PreparerFactory;
use App\Modules\Player\IndexCreation\Builder\TemplatePreparer;
use App\Modules\Playlists\Collector\Contracts\PlaylistStructureInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\PlayerEntities;

/**
 * The data for the template of an index: the sections which a template of a player knows. The preparers are the real ones.
 */
class TemplatePreparerTest extends TestCase
{
	private function preparer(): TemplatePreparer
	{
		$structure = new class implements PlaylistStructureInterface {
			public function getItems(): string { return '<items/>'; }
			public function getPrefetch(): string { return '<prefetch/>'; }
			public function getExclusive(): string { return '<exclusive/>'; }
		};

		return (new TemplatePreparer(new PreparerFactory()))
			->setPlayerEntity(PlayerEntities::create(['playlist_name' => 'Lobby', 'commands' => ['reboot'], 'playlist_mode' => 'master']))
			->setPlaylistStructure($structure);
	}

	/**
	 * @return array<string,array{TemplateIndexFiles,list<string>}>
	 */
	public static function templates(): array
	{
		return [
			'garlic'  => [TemplateIndexFiles::GARLIC, ['meta', 'subscriptions', 'layout', 'standby_times', 'playlist']],
			'iadea'   => [TemplateIndexFiles::XMP2XXX, ['meta', 'subscriptions', 'layout', 'standby_times', 'playlist']],
			'simple'  => [TemplateIndexFiles::SIMPLE, ['meta', 'layout', 'playlist']],
		];
	}

	/**
	 * @param list<string> $sections
	 */
	#[Group('units')]
	#[DataProvider('templates')]
	public function testEveryTemplateGetsTheSectionsItKnows(TemplateIndexFiles $template, array $sections): void
	{
		$data = $this->preparer()->prepare($template)->getTemplateData();

		static::assertSame($sections, array_keys($data));
		static::assertSame('Eingang - Lobby', $data['meta'][0]['TITLE']);
	}

	#[Group('units')]
	public function testTheSimpleTemplateGetsTheItemsOnly(): void
	{
		$data = $this->preparer()->prepare(TemplateIndexFiles::SIMPLE)->getTemplateData();

		static::assertSame([['INSERT_ELEMENTS' => '<items/>']], $data['playlist']);
	}

	#[Group('units')]
	public function testTheOtherTemplatesGetItemsPrefetchAndPriorityClasses(): void
	{
		foreach ([TemplateIndexFiles::GARLIC, TemplateIndexFiles::XMP2XXX] as $template)
		{
			$playlist = $this->preparer()->prepare($template)->getTemplateData()['playlist'][0];

			static::assertSame(['<exclusive/>', '<items/>', '<prefetch/>', 900], [$playlist['INSERT_PRIORITY_CLASSES'], $playlist['INSERT_ELEMENTS'], $playlist['INSERT_PREFETCH_ELEMENTS'], $playlist['PREFETCH_REFRESH_TIME']]);
		}
	}

	#[Group('units')]
	public function testTheCommandsOfThePlayerAreSubscribed(): void
	{
		$data = $this->preparer()->prepare(TemplateIndexFiles::GARLIC)->getTemplateData();

		static::assertSame(['TaskSchedule'], array_column($data['subscriptions'], 'SUBSCRIPTION_TYPE'));
	}
}
