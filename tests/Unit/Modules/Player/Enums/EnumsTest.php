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

namespace Tests\Unit\Modules\Player\Enums;

use App\Modules\Player\Enums\IndexSections;
use App\Modules\Player\Enums\PlayerActivity;
use App\Modules\Player\Enums\PlayerCommands;
use App\Modules\Player\Enums\PlayerModel;
use App\Modules\Player\Enums\PlayerReports;
use App\Modules\Player\Enums\PlayerStatus;
use App\Modules\Player\Enums\TemplateIndexFiles;
use BackedEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The values of these enums are stored in the database, sent to the players and used in files. A change of
 * a value breaks installations which exist: the tests name every value.
 */
class EnumsTest extends TestCase
{
	/**
	 * @return array<string,array{class-string<BackedEnum>,array<string,int|string>}>
	 */
	public static function enums(): array
	{
		return [
			'player status' => [PlayerStatus::class, [
				'UNREGISTERED' => 0, 'UNRELEASED' => 1, 'RELEASED' => 2, 'DEBUG_FTP' => 3, 'TEST_SMIL_OK' => 4, 'TEST_SMIL_ERROR' => 5,
				'TEST_USED_LATER1' => 6, 'TEST_USED_LATER2' => 7, 'TEST_NO_CONTENT' => 8, 'TEST_NO_PREFETCH' => 9,
			]],
			'player model' => [PlayerModel::class, [
				'UNKNOWN' => 0, 'IADEA_XMP1X0' => 1, 'IADEA_XMP3X0' => 2, 'IADEA_XMP3X50' => 3, 'COMPATIBLE' => 4,
				'IADEA_XMP2X00' => 5, 'GARLIC' => 6, 'IDS' => 7, 'QBIC' => 8, 'SCREENLITE' => 9,
			]],
			'player activity' => [PlayerActivity::class, ['ALL' => 'all', 'ACTIVE' => 'active', 'INACTIVE' => 'inactive']],
			'player commands' => [PlayerCommands::class, [
				'UPDATE_CONFIGURATION' => 'update_configuration', 'REBOOT' => 'reboot', 'UPDATE_FIRMWARE' => 'update_firmware',
				'CLEAR_CACHE' => 'clear_cache', 'CLEAR_WEBCACHE' => 'clear_webcache', 'UPDATE_URLS_LIST' => 'update_urls_list',
			]],
			'player reports' => [PlayerReports::class, [
				'INVENTORY' => 'inventory', 'PLAYED' => 'played', 'EVENTS' => 'events', 'SYSTEM_CONFIGURATION' => 'configuration', 'TASKS_EXECUTIONS' => 'executions',
			]],
			'index sections' => [IndexSections::class, [
				'META' => 'meta', 'SUBSCRIPTIONS' => 'subscriptions', 'LAYOUT' => 'layout', 'STANDBY_TIMES' => 'standby_times', 'PLAYLIST' => 'playlist', 'CATEGORIES' => 'categories',
			]],
			'template index files' => [TemplateIndexFiles::class, ['GARLIC' => 'garlic.smil', 'XMP2XXX' => 'xmp2x00.smil', 'SIMPLE' => 'simple.smil']],
		];
	}

	/**
	 * @param class-string<BackedEnum> $enum
	 * @param array<string,int|string> $values
	 */
	#[Group('units')]
	#[DataProvider('enums')]
	public function testEveryCaseHasItsValueAndNoCaseIsMissing(string $enum, array $values): void
	{
		$actual = [];
		foreach ($enum::cases() as $case)
			$actual[$case->name] = $case->value;

		static::assertSame($values, $actual);
	}

	/**
	 * @param class-string<BackedEnum> $enum
	 * @param array<string,int|string> $values
	 */
	#[Group('units')]
	#[DataProvider('enums')]
	public function testEveryValueLeadsBackToItsCaseAndAnythingElseToNone(string $enum, array $values): void
	{
		foreach ($values as $name => $value)
			static::assertSame($name, $enum::from($value)->name);

		static::assertNull($enum::tryFrom(is_int(reset($values)) ? 999 : 'not-a-value'));
	}
}
