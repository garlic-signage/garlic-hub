<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Unit\Modules\Playlists\Helper\Compose;

use App\Framework\Exceptions\CoreException;
use App\Modules\Playlists\Helper\Compose\MultizonePresets;
use App\Modules\Playlists\Helper\Playlists\ZonesSchema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class MultizonePresetsTest extends TestCase
{
	private string $file;

	protected function setUp(): void
	{
		parent::setUp();
		$this->file = (string) tempnam(sys_get_temp_dir(), 'presets');
	}

	protected function tearDown(): void
	{
		@unlink($this->file);
		parent::tearDown();
	}

	/**
	 * The shipped presets are valid and every one has a name in every language.
	 *
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testTheShippedPresetsAreValidAndTranslated(): void
	{
		$root    = dirname(__DIR__, 6);
		$presets = (new MultizonePresets($root . '/config/multizone_presets.json', new ZonesSchema()))->load();

		static::assertCount(8, $presets);
		foreach (glob($root . '/translations/*/playlists.ini') ?: [] as $translation)
		{
			$names = parse_ini_file($translation, true)['multizone_preset_selects'] ?? [];
			foreach ($presets as $preset)
			{
				static::assertArrayHasKey($preset['id'], $names, $translation);
			}
		}
	}

	/**
	 * @throws CoreException
	 */
	#[Group('units')]
	public function testAPresetIsReturnedWithItsZones(): void
	{
		$this->write([['id' => 'two_columns', 'zones' => [
			['left' => 0, 'top' => 0, 'width' => 50, 'height' => 100, 'bgcolor' => '#4a6fa5'],
			['left' => 50, 'top' => 0, 'width' => 49.5, 'height' => 100, 'bgcolor' => 'transparent']
		]]]);

		$expected = [['id' => 'two_columns', 'zones' => [
			['left' => 0, 'top' => 0, 'width' => 50, 'height' => 100, 'bgcolor' => '#4a6fa5'],
			['left' => 50, 'top' => 0, 'width' => 49.5, 'height' => 100, 'bgcolor' => 'transparent']
		]]];
		static::assertSame($expected, $this->load());
	}

	/**
	 * @return array<string,array{0:mixed}>
	 */
	public static function invalidPresets(): array
	{
		$zone = ['left' => 0, 'top' => 0, 'width' => 100, 'height' => 100, 'bgcolor' => '#000000'];
		return [
			'no list'             => [['id' => 'fullscreen', 'zones' => [$zone]]],
			'no id'               => [[['zones' => [$zone]]]],
			'id not a key'        => [[['id' => 'Full Screen', 'zones' => [$zone]]]],
			'no zones'            => [[['id' => 'empty', 'zones' => []]]],
			'zone not an object'  => [[['id' => 'broken', 'zones' => ['zone']]]],
			'wrong color'         => [[['id' => 'color', 'zones' => [['bgcolor' => 'red'] + $zone]]]],
			'missing value'       => [[['id' => 'missing', 'zones' => [['left' => 0, 'top' => 0, 'width' => 100, 'bgcolor' => '#000000']]]]],
			'outside on the left' => [[['id' => 'left', 'zones' => [['left' => -10] + $zone]]]],
			'wider than screen'   => [[['id' => 'wide', 'zones' => [['left' => 50, 'width' => 60] + $zone]]]],
			'higher than screen'  => [[['id' => 'high', 'zones' => [['top' => 50, 'height' => 60] + $zone]]]],
			'no width'            => [[['id' => 'thin', 'zones' => [['width' => 0] + $zone]]]],
			'no height'           => [[['id' => 'flat', 'zones' => [['height' => 0] + $zone]]]],
			'id with a space'     => [[['id' => 'full screen', 'zones' => [$zone]]]],
			'id with a line break' => [[['id' => "full\n", 'zones' => [$zone]]]],
		];
	}

	#[Group('units')]
	#[DataProvider('invalidPresets')]
	public function testAnInvalidPresetStopsTheLoad(mixed $presets): void
	{
		$this->write($presets);

		$this->expectException(CoreException::class);
		$this->load();
	}

	#[Group('units')]
	public function testTheExceptionNamesTheBrokenPreset(): void
	{
		$this->write([
			['id' => 'fine', 'zones' => [['left' => 0, 'top' => 0, 'width' => 100, 'height' => 100, 'bgcolor' => '#000000']]],
			['id' => 'broken', 'zones' => []]
		]);

		$this->expectException(CoreException::class);
		$this->expectExceptionMessage('Multizone preset not valid: broken');
		$this->load();
	}

	#[Group('units')]
	public function testAPresetWithoutIdIsNamedByItsPosition(): void
	{
		$this->write([['id' => 'fine', 'zones' => [['left' => 0, 'top' => 0, 'width' => 100, 'height' => 100, 'bgcolor' => '#000000']]], 'zone']);

		$this->expectException(CoreException::class);
		$this->expectExceptionMessage('Multizone preset not valid: #1');
		$this->load();
	}

	#[Group('units')]
	public function testAMissingFileStopsTheLoad(): void
	{
		$this->expectException(CoreException::class);
		$this->expectExceptionMessage('Multizone presets not readable: ' . $this->file . '.missing');
		(new MultizonePresets($this->file . '.missing', new ZonesSchema()))->load();
	}

	#[Group('units')]
	public function testBrokenJsonStopsTheLoad(): void
	{
		file_put_contents($this->file, '[{"id": ');

		$this->expectException(CoreException::class);
		$this->expectExceptionMessage('Multizone presets are not a list: ' . $this->file);
		$this->load();
	}

	private function write(mixed $presets): void
	{
		file_put_contents($this->file, json_encode($presets));
	}

	/**
	 * @return list<array{id:string, zones:list<array<string,int|float|string>>}>
	 * @throws CoreException
	 */
	private function load(): array
	{
		return (new MultizonePresets($this->file, new ZonesSchema()))->load();
	}
}
