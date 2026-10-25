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

namespace Tests\Unit\Modules\Playlists\Helper\Playlists;

use App\Modules\Playlists\Helper\Playlists\ZonesSchema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class ZonesSchemaTest extends TestCase
{
	private ZonesSchema $schema;

	protected function setUp(): void
	{
		parent::setUp();
		$this->schema = new ZonesSchema();
	}

	/**
	 * @param array<string,mixed> $changes
	 * @return array<string,mixed> a zone as the editor sends it
	 */
	private static function zone(array $changes = []): array
	{
		return $changes + ['zone_name' => 'Haupt', 'zone_left' => 0, 'zone_top' => 10, 'zone_width' => 1280, 'zone_height' => 720, 'zone_z-index' => 1, 'zone_bgcolor' => '#ff0000', 'zone_playlist_id' => 5];
	}

	/**
	 * @param array<string,mixed> $changes
	 * @return array<string,mixed>
	 */
	private static function layout(array $changes = []): array
	{
		return $changes + ['resolution' => '1920x1080', 'zoom' => 70, 'export_unit' => 'pixel', 'zones' => [self::zone()]];
	}

	#[Group('units')]
	public function testAValidLayoutIsReturnedWithoutOtherFields(): void
	{
		$layout = self::layout() + ['csrf_token' => 'secret', 'playlist_id' => '1', 'unknown' => 'x'];
		$layout['zones'][0]['unknown'] = 'y';

		static::assertSame(self::layout(), $this->schema->parse($layout));
	}

	#[Group('units')]
	public function testTheDefaultsAreFilled(): void
	{
		static::assertSame(
			['resolution' => '800x600', 'zoom' => 70, 'export_unit' => 'pixel', 'zones' => []],
			$this->schema->parse(['resolution' => '800x600'])
		);
	}

	#[Group('units')]
	public function testNumbersAsTextAreNumbers(): void
	{
		$result = $this->schema->parse(self::layout(['zoom' => '50', 'zones' => [self::zone(['zone_left' => '5', 'zone_z-index' => '3', 'zone_playlist_id' => '9'])]]));

		static::assertSame(50, $result['zoom'] ?? null);
		static::assertSame([5, 3, 9], [$result['zones'][0]['zone_left'] ?? null, $result['zones'][0]['zone_z-index'] ?? null, $result['zones'][0]['zone_playlist_id'] ?? null]);
	}

	#[Group('units')]
	public function testThePercentUnitHasFractions(): void
	{
		$zone = self::zone(['zone_left' => 33.333333, 'zone_top' => '0.5', 'zone_width' => 100, 'zone_height' => '66.6']);

		$result = $this->schema->parse(self::layout(['export_unit' => 'percent', 'zones' => [$zone]]));

		static::assertSame([33.333333, 0.5, 100, 66.6], [$result['zones'][0]['zone_left'] ?? null, $result['zones'][0]['zone_top'] ?? null, $result['zones'][0]['zone_width'] ?? null, $result['zones'][0]['zone_height'] ?? null]);
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function validColors(): array
	{
		return ['transparent' => ['transparent'], 'hex3' => ['#f00'], 'hex4' => ['#f00a'], 'hex6' => ['#FF0000'], 'hex8' => ['#ff000080'], 'rgb' => ['rgb(255, 0, 0)'], 'rgba' => ['rgba(0,0,0,0.5)'], 'rgba one' => ['rgba(0,0,0,1)']];
	}

	#[DataProvider('validColors')]
	#[Group('units')]
	public function testValidColors(string $color): void
	{
		static::assertNotNull($this->schema->parse(self::layout(['zones' => [self::zone(['zone_bgcolor' => $color])]])));
	}

	#[Group('units')]
	public function testAZoneMovedOutOfTheScreenKeepsItsNegativePosition(): void
	{
		$result = $this->schema->parse(self::layout(['zones' => [self::zone(['zone_left' => -50, 'zone_top' => '-5'])]]));

		static::assertSame([-50, -5], [$result['zones'][0]['zone_left'] ?? null, $result['zones'][0]['zone_top'] ?? null]);
		static::assertNotNull($this->schema->parse(self::layout(['export_unit' => 'percent', 'zones' => [self::zone(['zone_left' => -12.5, 'zone_top' => '-3', 'zone_width' => 50, 'zone_height' => 50])]])));
	}

	#[Group('units')]
	public function testALimitIsStillValid(): void
	{
		static::assertNotNull($this->schema->parse(self::layout(['resolution' => '16384x16384', 'zoom' => 1000, 'zones' => array_fill(0, 100, self::zone(['zone_z-index' => 999, 'zone_width' => 16384])), ])));
	}

	/**
	 * @return array<string,array{array<string,mixed>}>
	 */
	public static function invalidLayouts(): array
	{
		return [
			'no resolution'                  => [['zones' => []]],
			'a resolution as array'          => [self::layout(['resolution' => ['1920', '1080']])],
			'a resolution without x'         => [self::layout(['resolution' => '1920'])],
			'a resolution with a text'       => [self::layout(['resolution' => '1920x1080px'])],
			'a width of 0'                   => [self::layout(['resolution' => '0x1080'])],
			'a height above the limit'       => [self::layout(['resolution' => '1920x16385'])],
			'a negative width'               => [self::layout(['resolution' => '-1x1080'])],
			'a zoom of 0'                    => [self::layout(['zoom' => 0])],
			'a zoom above the limit'         => [self::layout(['zoom' => 1001])],
			'a zoom as text'                 => [self::layout(['zoom' => 'big'])],
			'an unknown unit'                => [self::layout(['export_unit' => 'inch'])],
			'a unit as array'                => [self::layout(['export_unit' => ['pixel']])],
			'zones as text'                  => [self::layout(['zones' => 'all'])],
			'zones with keys'                => [self::layout(['zones' => ['a' => self::zone()]])],
			'101 zones'                      => [self::layout(['zones' => array_fill(0, 101, self::zone())])],
			'a zone as text'                 => [self::layout(['zones' => ['zone']])],
			'a zone without a name'          => [self::layout(['zones' => [self::zone(['zone_name' => null])]])],
			'a name of 101 characters'       => [self::layout(['zones' => [self::zone(['zone_name' => str_repeat('a', 101)])]])],
			'a name as array'                => [self::layout(['zones' => [self::zone(['zone_name' => ['x']])]])],
			'a left as text'                 => [self::layout(['zones' => [self::zone(['zone_left' => 'left'])]])],
			'a top below the limit'          => [self::layout(['zones' => [self::zone(['zone_top' => -16385])]])],
			'a negative zone width'          => [self::layout(['zones' => [self::zone(['zone_width' => -1])]])],
			'a left of -101 percent'         => [self::layout(['export_unit' => 'percent', 'zones' => [self::zone(['zone_left' => -101])]])],
			'a fraction in pixel'            => [self::layout(['zones' => [self::zone(['zone_width' => 12.5])]])],
			'a width above the limit'        => [self::layout(['zones' => [self::zone(['zone_width' => 16385])]])],
			'a percent above 100'            => [self::layout(['export_unit' => 'percent', 'zones' => [self::zone(['zone_width' => 100.5])]])],
			'a z-index above the limit'      => [self::layout(['zones' => [self::zone(['zone_z-index' => 1000])]])],
			'a negative z-index'             => [self::layout(['zones' => [self::zone(['zone_z-index' => -1])]])],
			'no color'                       => [self::layout(['zones' => [self::zone(['zone_bgcolor' => null])]])],
			'a color with a quote'           => [self::layout(['zones' => [self::zone(['zone_bgcolor' => 'red" onload="x'])]])],
			'a color name'                   => [self::layout(['zones' => [self::zone(['zone_bgcolor' => 'red'])]])],
			'a hex color of 5 digits'        => [self::layout(['zones' => [self::zone(['zone_bgcolor' => '#12345'])]])],
			'a hex color with a line break'  => [self::layout(['zones' => [self::zone(['zone_bgcolor' => "#ff0000\n"])]])],
			'an rgb color with 4 numbers'    => [self::layout(['zones' => [self::zone(['zone_bgcolor' => 'rgb(1,2,3,4)'])]])],
			'a playlist of -1'               => [self::layout(['zones' => [self::zone(['zone_playlist_id' => -1])]])],
			'a playlist as array'            => [self::layout(['zones' => [self::zone(['zone_playlist_id' => [5]])]])],
			'a playlist as text'             => [self::layout(['zones' => [self::zone(['zone_playlist_id' => '5a'])]])],
		];
	}

	/**
	 * @param array<string,mixed> $layout
	 */
	#[DataProvider('invalidLayouts')]
	#[Group('units')]
	public function testInvalidLayoutsAreRejected(array $layout): void
	{
		static::assertNull($this->schema->parse($layout));
	}
}
