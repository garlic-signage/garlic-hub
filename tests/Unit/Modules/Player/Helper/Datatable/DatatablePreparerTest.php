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

namespace Tests\Unit\Modules\Player\Helper\Datatable;

use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Datatable\PrepareService;
use App\Framework\Utils\Datatable\Results\BodyPreparer;
use App\Framework\Utils\Datatable\Results\HeaderField;
use App\Framework\Utils\Datatable\TimeUnitsCalculator;
use App\Modules\Player\Enums\PlayerModel;
use App\Modules\Player\Enums\PlayerStatus;
use App\Modules\Player\Helper\Datatable\DatatablePreparer;
use App\Modules\Player\Helper\Datatable\Parameters;
use DateMalformedStringException;
use Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\InvalidArgumentException;

class DatatablePreparerTest extends TestCase
{
	private PrepareService&MockObject $prepareServiceMock;
	private BodyPreparer&MockObject $bodyPreparerMock;
	private Translator&MockObject $translatorMock;
	private TimeUnitsCalculator&MockObject $timeUnitsCalculatorMock;
	private DatatablePreparer $datatablePreparer;

	/**
	 * @throws Exception
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->prepareServiceMock = $this->createMock(PrepareService::class);
		$parametersMock           = $this->createMock(Parameters::class);
		$this->translatorMock     = $this->createMock(Translator::class);
		$this->bodyPreparerMock = $this->createMock(BodyPreparer::class);
		$this->timeUnitsCalculatorMock = $this->createMock(TimeUnitsCalculator::class);

		$this->datatablePreparer = new DatatablePreparer(
			$this->prepareServiceMock,
			$parametersMock,
			$this->timeUnitsCalculatorMock
		);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws DateMalformedStringException
	 */
	#[Group('units')]
	public function testPrepareTableBodyWithEmptyData(): void
	{
		$result = $this->datatablePreparer->prepareTableBody([], [], 123);

		$this->assertEmpty($result);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 * @throws \Doctrine\DBAL\Exception
	 * @throws DateMalformedStringException
	 */
	#[Group('units')]
	public function testPrepareTableBodyWithLastAccessPlayerActive(): void
	{
		$this->datatablePreparer->setTranslator($this->translatorMock);
		$fields = [$this->createMock(HeaderField::class)];
		$fields[0]->method('getName')->willReturn('last_access');
		$fields[0]->method('isSortable')->willReturn(true);

		$this->prepareServiceMock->method('getBodyPreparer')
			->willReturn($this->bodyPreparerMock);

		$this->translatorMock->method('translate')
			->willReturnMap([
				['select_playlist', 'player', [], 'Select playlist'],
				['remove_playlist', 'player', [], 'Remove playlist'],
				['goto_playlist', 'player', [], 'Goto playlist']
			]);

		$this->timeUnitsCalculatorMock->expects($this->once())->method('calculateLastAccess')
			->willReturnSelf();
		$this->timeUnitsCalculatorMock->expects($this->once())->method('getLastAccessTimeStamp')
			->willReturn(1600);

		$this->timeUnitsCalculatorMock->expects($this->once())->method('printDistance')
			->willReturn('some string');

		$this->bodyPreparerMock->expects($this->once())->method('formatSpan')
			->with(
				'some string',
				'2025-01-01 00:00:00',
				'last_access',
				'player-active'
			);

		$this->bodyPreparerMock->expects($this->exactly(3))->method('formatAction')
			->willReturnMap([
				['Select playlist', '#', 'edit', '123', 'pencil select-playlist', []],
				['Remove playlist', '#', 'playlist', '123', 'x-circle remove-playlist', []],
				['Goto playlist', '/playlists/compose/123', 'playlist', '123', 'music-note-list playlist-link', []]
			]);

		$result = $this->datatablePreparer->prepareTableBody(
			[
				[
					'player_id' => 1,
					'UID' => 13,
					'last_access' => '2025-01-01 00:00:00',
					'status' => PlayerStatus::RELEASED->value,
					'refresh' => '900',
					'model' => PlayerModel::GARLIC->value,
					'playlist_id' => 123,
				]
			],
			$fields,
			123
		);

		$this->assertCount(1, $result);
		$this->assertArrayHasKey('UNIT_ID', $result[0]);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 * @throws \Doctrine\DBAL\Exception
	 * @throws DateMalformedStringException
	 */
	#[Group('units')]
	public function testPrepareTableBodyWithLastAccessPlayerPending(): void
	{
		$this->datatablePreparer->setTranslator($this->translatorMock);
		$fields = [$this->createMock(HeaderField::class)];
		$fields[0]->method('getName')->willReturn('last_access');
		$fields[0]->method('isSortable')->willReturn(true);

		$this->prepareServiceMock->method('getBodyPreparer')
			->willReturn($this->bodyPreparerMock);

		$this->translatorMock->method('translate')
			->willReturnMap([
				['select_playlist', 'player', [], 'Select playlist'],
				['remove_playlist', 'player', [], 'Remove playlist'],
				['goto_playlist', 'player', [], 'Goto playlist']
			]);

		$this->timeUnitsCalculatorMock->expects($this->once())->method('calculateLastAccess')
			->willReturnSelf();
		$this->timeUnitsCalculatorMock->expects($this->once())->method('getLastAccessTimeStamp')
			->willReturn(2400);

		$this->timeUnitsCalculatorMock->expects($this->once())->method('printDistance')
			->willReturn('some string');

		$this->bodyPreparerMock->expects($this->once())->method('formatSpan')
			->with(
				'some string',
				'2025-01-01 00:00:00',
				'last_access',
				'player-pending'
			);

		$this->bodyPreparerMock->expects($this->exactly(3))->method('formatAction')
			->willReturnMap([
				['Select playlist', '#', 'edit', '123', 'pencil select-playlist', []],
				['Remove playlist', '#', 'playlist', '123', 'x-circle remove-playlist', []],
				['Goto playlist', '/playlists/compose/123', 'playlist', '123', 'music-note-list playlist-link', []]
			]);

		$result = $this->datatablePreparer->prepareTableBody(
			[
				[
					'player_id' => 1,
					'UID' => 13,
					'last_access' => '2025-01-01 00:00:00',
					'status' => PlayerStatus::RELEASED->value,
					'refresh' => '900',
					'model' => PlayerModel::GARLIC->value,
					'playlist_id' => 123,
				]
			],
			$fields,
			123
		);

		$this->assertCount(1, $result);
		$this->assertArrayHasKey('UNIT_ID', $result[0]);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 * @throws \Doctrine\DBAL\Exception
	 * @throws DateMalformedStringException
	 */
	#[Group('units')]
	public function testPrepareTableBodyWithLastAccessPlayerInactive(): void
	{
		$this->datatablePreparer->setTranslator($this->translatorMock);
		$fields = [$this->createMock(HeaderField::class)];
		$fields[0]->method('getName')->willReturn('last_access');
		$fields[0]->method('isSortable')->willReturn(true);

		$this->prepareServiceMock->method('getBodyPreparer')
			->willReturn($this->bodyPreparerMock);

		$this->translatorMock->method('translate')
			->willReturnMap([
				['select_playlist', 'player', [], 'Select playlist'],
				['remove_playlist', 'player', [], 'Remove playlist'],
				['goto_playlist', 'player', [], 'Goto playlist']
			]);

		$this->timeUnitsCalculatorMock->expects($this->once())->method('calculateLastAccess')
			->willReturnSelf();
		$this->timeUnitsCalculatorMock->expects($this->once())->method('getLastAccessTimeStamp')
			->willReturn(24000);

		$this->timeUnitsCalculatorMock->expects($this->once())->method('printDistance')
			->willReturn('some string');

		$this->bodyPreparerMock->expects($this->once())->method('formatSpan')
			->with(
				'some string',
				'2025-01-01 00:00:00',
				'last_access',
				'player-inactive'
			);

		$this->bodyPreparerMock->expects($this->exactly(3))->method('formatAction')
			->willReturnMap([
				['Select playlist', '#', 'edit', '123', 'pencil select-playlist', []],
				['Remove playlist', '#', 'playlist', '123', 'x-circle remove-playlist', []],
				['Goto playlist', '/playlists/compose/123', 'playlist', '123', 'music-note-list playlist-link', []]
			]);

		$result = $this->datatablePreparer->prepareTableBody(
			[
				[
					'player_id' => 1,
					'UID' => 13,
					'last_access' => '2025-01-01 00:00:00',
					'status' => PlayerStatus::RELEASED->value,
					'refresh' => '900',
					'model' => PlayerModel::GARLIC->value,
					'playlist_id' => 123,
				]
			],
			$fields,
			123
		);

		$this->assertCount(1, $result);
		$this->assertArrayHasKey('UNIT_ID', $result[0]);
	}

	/**
	 * @throws CoreException
	 * @throws DateMalformedStringException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	#[Group('units')]
	public function testPrepareTableBodyWithPlayerNameReleased(): void
	{
		$this->datatablePreparer->setTranslator($this->translatorMock);
		$fields = [$this->createMock(HeaderField::class)];
		$fields[0]->method('getName')->willReturn('player_name');
		$fields[0]->method('isSortable')->willReturn(true);

		$this->prepareServiceMock->method('getBodyPreparer')
			->willReturn($this->bodyPreparerMock);

		$this->translatorMock->method('translate')
			->willReturnMap([
				['select_playlist', 'player', [], 'Select playlist'],
				['remove_playlist', 'player', [], 'Remove playlist'],
				['goto_playlist', 'player', [], 'Goto playlist']
			]);

		$this->bodyPreparerMock->expects($this->once())->method('formatText')
			->with('Player name');

		$this->bodyPreparerMock->expects($this->exactly(3))->method('formatAction')
			->willReturnMap([
				['Select playlist', '#', 'edit', '123', 'pencil select-playlist', []],
				['Remove playlist', '#', 'playlist', '123', 'x-circle remove-playlist', []],
				['Goto playlist', '/playlists/compose/123', 'playlist', '123', 'music-note-list playlist-link', []]
			]);

		$result = $this->datatablePreparer->prepareTableBody(
			[
				[
					'player_id' => 1,
					'UID' => 13,
					'status' => PlayerStatus::RELEASED->value,
					'player_name' => 'Player name',
					'model' => PlayerModel::GARLIC->value,
					'playlist_id' => 123,
				]
			],
			$fields,
			123
		);

		$this->assertCount(1, $result);
		$this->assertArrayHasKey('UNIT_ID', $result[0]);
	}

	/**
	 * @throws CoreException
	 * @throws DateMalformedStringException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	#[Group('units')]
	public function testPrepareTableBodyWithUsername(): void
	{
		$this->datatablePreparer->setTranslator($this->translatorMock);
		$fields = [$this->createMock(HeaderField::class)];
		$fields[0]->method('getName')->willReturn('UID');
		$fields[0]->method('isSortable')->willReturn(true);

		$this->prepareServiceMock->method('getBodyPreparer')
			->willReturn($this->bodyPreparerMock);
		$this->bodyPreparerMock->expects($this->once())->method('formatUID')
			->with('15', 'Zapappalas');

		$result = $this->datatablePreparer->prepareTableBody(
			[
				[
					'player_id' => 1,
					'UID' => 15,
					'username' => 'Zapappalas',
					'status' => PlayerStatus::UNRELEASED->value,
					'player_name' => 'Player name',
					'model' => PlayerModel::GARLIC->value,
					'playlist_id' => 0,
				]
			],
			$fields,
			123
		);

		$this->assertCount(1, $result);
		$this->assertArrayHasKey('UNIT_ID', $result[0]);
	}

	/**
	 * @throws CoreException
	 * @throws DateMalformedStringException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	#[Group('units')]
	public function testPrepareTableBodyWithStatusUnreleased(): void
	{
		$this->datatablePreparer->setTranslator($this->translatorMock);
		$fields = [$this->createMock(HeaderField::class)];
		$fields[0]->method('getName')->willReturn('status');
		$fields[0]->method('isSortable')->willReturn(true);

		$this->prepareServiceMock->method('getBodyPreparer')
			->willReturn($this->bodyPreparerMock);
		$this->translatorMock->method('translateArrayForOptions')
			->with('status_selects', 'player')
			->willReturn([PlayerStatus::UNRELEASED->value => 'unreleased']);

		$this->bodyPreparerMock->expects($this->once())->method('formatIcon')
			->with('bi bi-x', 'unreleased');

		$result = $this->datatablePreparer->prepareTableBody(
			[
				[
					'player_id' => 1,
					'UID' => 15,
					'username' => 'Zapappalas',
					'status' => PlayerStatus::UNRELEASED->value,
					'playlist_id' => 0,
				]
			],
			$fields,
			123
		);

		$this->assertCount(1, $result);
		$this->assertArrayHasKey('UNIT_ID', $result[0]);
	}

	/**
	 * @throws CoreException
	 * @throws DateMalformedStringException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	#[Group('units')]
	public function testPrepareTableBodyWithStatusReleased(): void
	{
		$this->datatablePreparer->setTranslator($this->translatorMock);
		$fields = [$this->createMock(HeaderField::class)];
		$fields[0]->method('getName')->willReturn('status');
		$fields[0]->method('isSortable')->willReturn(true);

		$this->prepareServiceMock->method('getBodyPreparer')
			->willReturn($this->bodyPreparerMock);
		$this->translatorMock->method('translateArrayForOptions')
			->with('status_selects', 'player')
			->willReturn([PlayerStatus::RELEASED->value => 'released']);

		$this->bodyPreparerMock->expects($this->once())->method('formatIcon')
			->with('bi bi-check', 'released');

		$result = $this->datatablePreparer->prepareTableBody(
			[
				[
					'player_id' => 1,
					'UID' => 15,
					'username' => 'Zapappalas',
					'status' => PlayerStatus::RELEASED->value,
					'player_name' => 'Player name',
					'model' => PlayerModel::GARLIC->value,
					'playlist_id' => 0,
				]
			],
			$fields,
			123
		);

		$this->assertCount(1, $result);
		$this->assertArrayHasKey('UNIT_ID', $result[0]);
	}

	/**
	 * @throws CoreException
	 * @throws DateMalformedStringException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	#[Group('units')]
	public function testPrepareTableBodyWithStatusDebug(): void
	{
		$this->datatablePreparer->setTranslator($this->translatorMock);
		$fields = [$this->createMock(HeaderField::class)];
		$fields[0]->method('getName')->willReturn('status');
		$fields[0]->method('isSortable')->willReturn(true);

		$this->prepareServiceMock->method('getBodyPreparer')
			->willReturn($this->bodyPreparerMock);
		$this->translatorMock->method('translateArrayForOptions')
			->with('status_selects', 'player')
			->willReturn([PlayerStatus::TEST_NO_PREFETCH->value => 'no-prefetch']);

		$this->bodyPreparerMock->expects($this->once())->method('formatIcon')
			->with('bi bi-bug', 'no-prefetch');

		$result = $this->datatablePreparer->prepareTableBody(
			[
				[
					'player_id' => 1,
					'UID' => 15,
					'username' => 'Zapappalas',
					'status' => PlayerStatus::TEST_NO_PREFETCH->value,
					'player_name' => 'Player name',
					'model' => PlayerModel::GARLIC->value,
					'playlist_id' => 0,
				]
			],
			$fields,
			123
		);

		$this->assertCount(1, $result);
		$this->assertArrayHasKey('UNIT_ID', $result[0]);
	}

	/**
	 * @throws CoreException
	 * @throws DateMalformedStringException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	#[Group('units')]
	public function testPrepareTableBodyWithModel(): void
	{
		$this->datatablePreparer->setTranslator($this->translatorMock);
		$fields = [$this->createMock(HeaderField::class)];
		$fields[0]->method('getName')->willReturn('model');
		$fields[0]->method('isSortable')->willReturn(true);

		$this->prepareServiceMock->method('getBodyPreparer')
			->willReturn($this->bodyPreparerMock);
		$this->translatorMock->method('translateArrayForOptions')
			->with('model_selects', 'player')
			->willReturn([PlayerModel::GARLIC->value => 'garlic-player']);

		$this->bodyPreparerMock->expects($this->once())->method('formatText')
			->with('garlic-player');

		$result = $this->datatablePreparer->prepareTableBody(
			[
				[
					'player_id' => 1,
					'UID' => 15,
					'username' => 'Zapappalas',
					'status' => PlayerStatus::UNRELEASED->value,
					'player_name' => 'Player name',
					'model' => PlayerModel::GARLIC->value,
					'playlist_id' => 0,
				]
			],
			$fields,
			123
		);

		$this->assertCount(1, $result);
		$this->assertArrayHasKey('UNIT_ID', $result[0]);
	}

	/**
	 * @throws CoreException
	 * @throws DateMalformedStringException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	#[Group('units')]
	public function testPrepareTableBodyWithPlaylistIdReleased(): void
	{
		$this->datatablePreparer->setTranslator($this->translatorMock);
		$fields = [$this->createMock(HeaderField::class)];
		$fields[0]->method('getName')->willReturn('playlist_id');
		$fields[0]->method('isSortable')->willReturn(false);

		$this->prepareServiceMock->method('getBodyPreparer')
			->willReturn($this->bodyPreparerMock);

		$this->translatorMock->method('translate')
			->willReturnMap([
				['select_playlist', 'player', [], 'Select playlist'],
				['remove_playlist', 'player', [], 'Remove playlist'],
				['goto_playlist', 'player', [], 'Goto playlist']
			]);

		$this->bodyPreparerMock->expects($this->once())->method('formatText')
			->with('Playlist name');

		$this->bodyPreparerMock->expects($this->exactly(3))->method('formatAction')
			->willReturnMap([
				['Select playlist', '#', 'edit', '123', 'pencil select-playlist', []],
				['Remove playlist', '#', 'playlist', '123', 'x-circle remove-playlist', []],
				['Goto playlist', '/playlists/compose/123', 'playlist', '123', 'music-note-list playlist-link', []]
			]);

		$result = $this->datatablePreparer->prepareTableBody(
			[
				[
					'player_id' => 1,
					'UID' => 13,
					'status' => PlayerStatus::RELEASED->value,
					'player_name' => 'Player name',
					'model' => PlayerModel::GARLIC->value,
					'playlist_id' => 123,
					'playlist_name' => 'Playlist name',
				]
			],
			$fields,
			123
		);

		$this->assertCount(1, $result);
		$this->assertArrayHasKey('UNIT_ID', $result[0]);
	}

	/**
	 * @throws CoreException
	 * @throws DateMalformedStringException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	#[Group('units')]
	public function testPrepareTableBodyWithPlaylistIdUnReleased(): void
	{
		$this->datatablePreparer->setTranslator($this->translatorMock);
		$fields = [$this->createMock(HeaderField::class)];
		$fields[0]->method('getName')->willReturn('playlist_id');
		$fields[0]->method('isSortable')->willReturn(false);

		$this->prepareServiceMock->method('getBodyPreparer')
			->willReturn($this->bodyPreparerMock);

		$this->translatorMock->method('translate')
			->willReturnMap([
				['select_playlist', 'player', [], 'Select playlist'],
				['remove_playlist', 'player', [], 'Remove playlist'],
				['goto_playlist', 'player', [], 'Goto playlist']
			]);

		$this->bodyPreparerMock->expects($this->never())->method('formatText');

		$result = $this->datatablePreparer->prepareTableBody(
			[
				[
					'player_id' => 1,
					'UID' => 13,
					'status' => PlayerStatus::UNRELEASED->value,
					'player_name' => 'Player name',
					'model' => PlayerModel::GARLIC->value,
					'playlist_id' => 123,
					'playlist_name' => 'Playlist name',
				]
			],
			$fields,
			123
		);

		$this->assertCount(1, $result);
		$this->assertArrayHasKey('UNIT_ID', $result[0]);
	}


	/**
	 */
	#[Group('units')]
	public function testFormatPlaylistContextMenu(): void
	{
		$this->datatablePreparer->setTranslator($this->translatorMock);
//		$configMock = $this->createMock(Config::class);
//		$this->aclValidatorMock->method('getConfig')->willReturn($configMock);
//		$configMock->method('getEdition')->willReturn(Config::PLATFORM_EDITION_EDGE);
		$this->translatorMock->method('translateArrayForOptions')
			->with('settings_selects', 'player')
			->willReturn(['edit' => 'Edit', 'delete' => 'Delete']);

		$result = $this->datatablePreparer->formatPlayerContextMenu();

		$this->assertCount(2, $result);
		$this->assertEquals(
			[
				['PLAYER_SETTINGS' => 'edit', 'LANG_PLAYER_SETTINGS' => 'Edit'],
				['PLAYER_SETTINGS' => 'delete', 'LANG_PLAYER_SETTINGS' => 'Delete']
			],
			$result
		);
	}

}
