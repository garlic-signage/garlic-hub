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

namespace Tests\Unit\Modules\Player\IndexCreation\Builder\Preparers;

use App\Modules\Player\Enums\IndexSections;
use App\Modules\Player\IndexCreation\Builder\Preparers\LayoutPreparer;
use App\Modules\Player\IndexCreation\Builder\Preparers\MetaPreparer;
use App\Modules\Player\IndexCreation\Builder\Preparers\PlaylistPreparer;
use App\Modules\Player\IndexCreation\Builder\Preparers\PreparerFactory;
use App\Modules\Player\IndexCreation\Builder\Preparers\ScreenTimesPreparer;
use App\Modules\Player\IndexCreation\Builder\Preparers\SubscriptionPreparer;
use App\Modules\Playlists\Collector\Contracts\PlaylistStructureInterface;
use DateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\PlayerEntities;

/**
 * The parts of the index: each preparer turns the entity of a player into the values of the template.
 * The entities are the real ones.
 */
class PreparersTest extends TestCase
{
	// ---- meta

	#[Group('units')]
	public function testTheTitleHasThePlayerAndThePlaylist(): void
	{
		$player = PlayerEntities::create(['playlist_name' => 'Lobby', 'refresh' => 600], 'Garlic', 'garlic/v1', 'Eingang');

		static::assertSame([['TITLE' => 'Eingang - Lobby', 'REFRESH_TIME' => 600]], (new MetaPreparer($player))->prepare());
	}

	#[Group('units')]
	public function testTheRefreshTimeHasADefault(): void
	{
		static::assertSame(300, (new MetaPreparer(PlayerEntities::create()))->prepare()[0]['REFRESH_TIME']);
	}

	// ---- layout

	/**
	 * @return array<string,mixed>
	 */
	private function region(string|int $id, string|int $top, string|int $left, string|int $width, string|int $height, string|int $z, string $color = 'transparent'): array
	{
		return ['SCREEN_ID' => $id, 'REGION_TOP' => $top, 'REGION_LEFT' => $left, 'REGION_WIDTH' => $width, 'REGION_HEIGHT' => $height, 'REGION_Z_INDEX' => $z, 'REGION_BGCOLOR' => $color];
	}

	#[Group('units')]
	public function testWithoutALayoutThePlayerGetsOneRegionOfFullHd(): void
	{
		$layout = (new LayoutPreparer(PlayerEntities::create(['playlist_mode' => 'master'])))->prepare();

		static::assertSame([['ROOT_LAYOUT_WIDTH' => '1920', 'ROOT_LAYOUT_HEIGHT' => '1080', 'regions' => [$this->region('', '0', '0', '1920', '1080', '0')]]], $layout);
	}

	#[Group('units')]
	public function testTheResolutionOfThePlaylistIsTheSizeOfTheRegion(): void
	{
		$layout = (new LayoutPreparer(PlayerEntities::create(['playlist_mode' => 'master', 'layout' => ['resolution' => '1280x720']])))->prepare();

		static::assertSame('1280', $layout[0]['ROOT_LAYOUT_WIDTH']);
		static::assertSame('720', $layout[0]['ROOT_LAYOUT_HEIGHT']);
		static::assertSame([$this->region('', '0', '0', '1280', '720', '0')], $layout[0]['regions']);
	}

	/**
	 * @return array<string,array{string,string}> the unit and the sign which the template gets behind a size
	 */
	public static function zoneUnits(): array
	{
		return ['pixel' => ['pixel', ''], 'percent' => ['percent', '%']];
	}

	#[Group('units')]
	#[DataProvider('zoneUnits')]
	public function testAMultizonePlaylistHasARegionForEveryZone(string $unit, string $suffix): void
	{
		$zones = ['1' => ['zone_top' => 0, 'zone_left' => 0, 'zone_width' => 70, 'zone_height' => 100, 'zone_z-index' => 1, 'zone_bgcolor' => '#ffffff'],
			'2' => ['zone_top' => 0, 'zone_left' => 70, 'zone_width' => 30, 'zone_height' => 50, 'zone_z-index' => 2, 'zone_bgcolor' => '#000000']];
		$player = PlayerEntities::create(['playlist_mode' => 'multizone', 'layout' => ['resolution' => '1920x1080', 'export_unit' => $unit, 'zones' => $zones]]);

		$regions = (new LayoutPreparer($player))->prepare()[0]['regions'];

		// a number as a key of the zones is a number in PHP; with percent the unit is a text
		$size = static fn(int $value): string|int => $suffix === '' ? $value : $value . $suffix;
		static::assertSame([
			$this->region(1, $size(0), $size(0), $size(70), $size(100), 1, '#ffffff'),
			$this->region(2, $size(0), $size(70), $size(30), $size(50), 2, '#000000'),
		], $regions);
	}

	// ---- playlist

	private function structure(): PlaylistStructureInterface
	{
		return new class implements PlaylistStructureInterface {
			public function getItems(): string { return '<items/>'; }
			public function getPrefetch(): string { return '<prefetch/>'; }
			public function getExclusive(): string { return '<exclusive/>'; }
		};
	}

	#[Group('units')]
	public function testTheSimplePlaylistHasTheItemsOnly(): void
	{
		$preparer = (new PlaylistPreparer(PlayerEntities::create()))->setPlaylistStructure($this->structure())->setIsSimple(true);

		static::assertSame([['INSERT_ELEMENTS' => '<items/>']], $preparer->prepare());
	}

	#[Group('units')]
	public function testThePlaylistOfAGarlicPlayerHasEverything(): void
	{
		$preparer = (new PlaylistPreparer(PlayerEntities::create(['refresh' => 300, 'duration' => 0])))->setPlaylistStructure($this->structure())->setIsSimple(false);

		static::assertSame([[
			'INSERT_PRIORITY_CLASSES'  => '<exclusive/>',
			'INSERT_ELEMENTS'          => '<items/>',
			'INSERT_PREFETCH_ELEMENTS' => '<prefetch/>',
			'PREFETCH_REFRESH_TIME'    => 900,
		]], $preparer->prepare());
	}

	/**
	 * @return array<string,array{int,int,int}> the duration of the playlist, the refresh time, the time to prefetch
	 */
	public static function prefetchTimes(): array
	{
		return [
			'short playlist, default refresh'  => [0, 300, 900],
			'duration is the refresh'          => [300, 300, 900],
			'refresh just over the minimum'    => [0, 900, 1802],
			'long playlist'                    => [1000, 300, 2002],
			'duration just under the minimum'  => [898, 300, 900],
			'duration at the minimum'          => [899, 300, 1800],
		];
	}

	#[Group('units')]
	#[DataProvider('prefetchTimes')]
	public function testThePrefetchIsAskedForTwiceTheLongerOfDurationAndRefreshAtLeast15Minutes(int $duration, int $refresh, int $expected): void
	{
		$preparer = new PlaylistPreparer(PlayerEntities::create(['duration' => $duration, 'refresh' => $refresh]));

		static::assertSame($expected, $preparer->calculatePrefetchDuration());
	}

	// ---- standby times

	#[Group('units')]
	public function testOnlyGarlicPlayersGetStandbyTimes(): void
	{
		$times = [['day' => 0, 'periods' => [['start' => '08:00', 'end' => '18:00']]]];

		static::assertSame([], (new ScreenTimesPreparer(PlayerEntities::create(['screen_times' => $times], 'XMP-6250', 'ADAPI/1.0')))->prepare());
	}

	#[Group('units')]
	public function testWithoutTimesThereIsNoSection(): void
	{
		static::assertSame([], (new ScreenTimesPreparer(PlayerEntities::create(['screen_times' => []])))->prepare());
	}

	#[Group('units')]
	public function testThePeriodsBecomeWallclocksOfTheWeek(): void
	{
		$times = [
			['day' => 0, 'periods' => [['start' => '08:00', 'end' => '12:00'], ['start' => '13:00', 'end' => '18:00']]],
			['day' => 4, 'periods' => [['start' => '20:00', 'end' => '00:00']]],
		];
		$prefix = 'wallclock(R/' . (new DateTime('now'))->modify('-1 month')->format('Y-m-d');

		$result = (new ScreenTimesPreparer(PlayerEntities::create(['screen_times' => $times])))->prepare();

		static::assertSame([[
			'BEGIN_WALLCLOCKS' => $prefix . '+w1T12:00:00/P1W);' . $prefix . '+w1T18:00:00/P1W)',
			'END_WALLCLOCKS'   => $prefix . '+w1T08:00:00/P1W);' . $prefix . '+w1T13:00:00/P1W);' . $prefix . '+w5T20:00:00/P1W)',
		]], $result);
	}

	// ---- subscriptions

	#[Group('units')]
	public function testAPlayerWithoutCommandsAndReportsSubscribesToNothing(): void
	{
		static::assertSame([], (new SubscriptionPreparer(PlayerEntities::create()))->prepare());
	}

	#[Group('units')]
	public function testCommandsAreFetchedFromTheTaskScheduleOfThePlayer(): void
	{
		$subscriptions = (new SubscriptionPreparer(PlayerEntities::create(['commands' => ['reboot']])))->prepare();

		static::assertCount(1, $subscriptions);
		static::assertSame('TaskSchedule', $subscriptions[0]['SUBSCRIPTION_TYPE']);
		static::assertSame('https://hub.example.com/smil-index/' . PlayerEntities::UUID . '/task_scheduler.xml', $subscriptions[0]['SUBSCRIPTION_ACTION']);
		static::assertSame('get', $subscriptions[0]['SUBSCRIPTION_METHOD']);
		static::assertGreaterThanOrEqual(1000, $subscriptions[0]['SUBSCRIPTION_RANDOM']);
		static::assertLessThanOrEqual(9999, $subscriptions[0]['SUBSCRIPTION_RANDOM']);
	}

	/**
	 * @return array<string,array{string,string,string}> the report, the type of the subscription, the address after the report server
	 */
	public static function reports(): array
	{
		return [
			'inventory'     => ['inventory', 'InventoryReport', '/inventory-' . PlayerEntities::UUID . '.xml'],
			'played'        => ['played', 'PlaylogCollection', ''],
			'events'        => ['events', 'EventlogCollection', ''],
			'configuration' => ['configuration', 'SystemReport', '/system-' . PlayerEntities::UUID . '.xml'],
			'executions'    => ['executions', 'TaskExecutionReport', '/task_execution-' . PlayerEntities::UUID . '.xml'],
		];
	}

	/**
	 * The reports are a list of names, as the database holds them (reports = 'inventory,played').
	 */
	#[Group('units')]
	#[DataProvider('reports')]
	public function testEveryReportIsSubscribed(string $report, string $type, string $address): void
	{
		$subscriptions = (new SubscriptionPreparer(PlayerEntities::create(['reports' => [$report]])))->prepare();

		static::assertSame([['SUBSCRIPTION_TYPE' => $type, 'SUBSCRIPTION_ACTION' => 'https://reports.example.com' . $address, 'SUBSCRIPTION_METHOD' => 'put']], $subscriptions);
	}

	#[Group('units')]
	public function testSeveralReportsAndCommandsAreSubscribedTogether(): void
	{
		$player = PlayerEntities::create(['commands' => ['reboot'], 'reports' => ['inventory', 'played', 'events', 'configuration', 'executions']]);

		$types = array_column((new SubscriptionPreparer($player))->prepare(), 'SUBSCRIPTION_TYPE');

		static::assertSame(['TaskSchedule', 'InventoryReport', 'PlaylogCollection', 'EventlogCollection', 'SystemReport', 'TaskExecutionReport'], $types);
	}

	#[Group('units')]
	public function testAReportWhichIsNotKnownIsNotSubscribed(): void
	{
		static::assertSame([], (new SubscriptionPreparer(PlayerEntities::create(['reports' => ['screenshot', 'play', '']])))->prepare());
	}

	// ---- factory

	/**
	 * @return array<string,array{IndexSections,class-string}>
	 */
	public static function sections(): array
	{
		return [
			'meta'          => [IndexSections::META, MetaPreparer::class],
			'subscriptions' => [IndexSections::SUBSCRIPTIONS, SubscriptionPreparer::class],
			'layout'        => [IndexSections::LAYOUT, LayoutPreparer::class],
			'standby times' => [IndexSections::STANDBY_TIMES, ScreenTimesPreparer::class],
			'playlist'      => [IndexSections::PLAYLIST, PlaylistPreparer::class],
		];
	}

	/**
	 * @param class-string $class
	 */
	#[Group('units')]
	#[DataProvider('sections')]
	public function testTheFactoryMakesThePreparerOfASection(IndexSections $section, string $class): void
	{
		static::assertInstanceOf($class, (new PreparerFactory())->create($section, PlayerEntities::create()));
	}

	#[Group('units')]
	public function testASectionWithoutAPreparerIsAnError(): void
	{
		$this->expectExceptionMessage('Unknown index section');

		(new PreparerFactory())->create(IndexSections::CATEGORIES, PlayerEntities::create());
	}
}
