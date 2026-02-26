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

namespace Tests\Unit\Modules\Playlists\Helper\Datatable;

use App\Framework\Core\Config\Config;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Datatable\PrepareService;
use App\Framework\Utils\Datatable\Results\BodyPreparer;
use App\Framework\Utils\Datatable\Results\HeaderField;
use App\Modules\Playlists\Helper\Datatable\DatatablePreparer;
use App\Modules\Playlists\Helper\Datatable\Parameters;
use App\Modules\Playlists\Helper\PlaylistMode;
use App\Modules\Playlists\Services\AclValidator;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\InvalidArgumentException;

class DatatablePreparerTest extends TestCase
{
	private PrepareService&MockObject $prepareServiceMock;
	private AclValidator&MockObject $aclValidatorMock;
	private BodyPreparer&MockObject $bodyPreparerMock;
	private Translator&MockObject $translatorMock;
	private DatatablePreparer $datatablePreparer;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->prepareServiceMock = $this->createMock(PrepareService::class);
		$this->aclValidatorMock   = $this->createMock(AclValidator::class);
		$parametersMock           = $this->createMock(Parameters::class);
		$this->translatorMock     = $this->createMock(Translator::class);
		$this->bodyPreparerMock = $this->createMock(BodyPreparer::class);

		$this->datatablePreparer = new DatatablePreparer(
			$this->prepareServiceMock,
			$this->aclValidatorMock,
			$parametersMock
		);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testPrepareTableBodyWithEmptyData(): void
	{
		$result = $this->datatablePreparer->prepareTableBody([], [], 123);

		static::assertEmpty($result);
	}


	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testPrepareTableBodyWithPlaylistName(): void
	{
		$this->datatablePreparer->setTranslator($this->translatorMock);
		$usedPlaylists = [];
		$this->datatablePreparer->setUsedPlaylists($usedPlaylists);
		$fields = [$this->createMock(HeaderField::class)];
		$fields[0]->method('getName')->willReturn('playlist_name');
		$fields[0]->method('isSortable')->willReturn(true);

		$this->aclValidatorMock->method('isSimpleAdmin')
			->with(123)
			->willReturn(true);
		$this->prepareServiceMock->method('getBodyPreparer')
			->willReturn($this->bodyPreparerMock);

		$this->translatorMock->method('translate')
			->willReturnMap([
				['composer', 'playlists', [], 'Compose'],
				['composer', 'playlists', [], 'Compose'],
				['copy_playlist', 'playlists', [], 'copy'],
				['edit_settings', 'playlists', [], 'Edit setting'],
				['delete', 'main', [], 'Delete'],
				['confirm_delete', 'playlists', [], 'Confirm delete']
			]);

		$this->aclValidatorMock->method('isAllowedToDeletePlaylist')->willReturn(true);

		$this->bodyPreparerMock->expects($this->once())->method('formatLink')
			->with('Playlist Name', 'Compose', 'playlists/compose/1', 'playlist_name_1');

		$result = $this->datatablePreparer->prepareTableBody(
			[
				['playlist_id' => 1,
					'UID' => 13,
					'company_id' => 1,
					'username' => 'Heidi',
					'duration' => 12,
					'playlist_name' => 'Playlist Name', 'playlist_mode' => 'master']],
			$fields,
			123
		);

		static::assertCount(1, $result);
		static::assertArrayHasKey('UNIT_ID', $result[0]);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testPrepareTableBodyWithUID(): void
	{
		$this->datatablePreparer->setTranslator($this->translatorMock);
		$usedPlaylists = [];
		$this->datatablePreparer->setUsedPlaylists($usedPlaylists);

		$fields = [$this->createMock(HeaderField::class)];
		$fields[0]->method('getName')->willReturn('UID');
		$fields[0]->method('isSortable')->willReturn(true);

		$this->prepareServiceMock->method('getBodyPreparer')
			->willReturn($this->bodyPreparerMock);
		$this->bodyPreparerMock->expects($this->once())->method('formatUID')
			->with('13', 'Willi');

		$this->aclValidatorMock->method('isModuleAdmin')->willReturn(true);
		$this->aclValidatorMock->method('isSimpleAdmin')->willReturn(true);

		$result = $this->datatablePreparer->prepareTableBody(
			[
				['playlist_id' => 1,
					'UID' => 13,
					'company_id' => 1,
					'username' => 'Willi',
					'duration' => 12,
					'playlist_name' => 'Playlist Name', 'playlist_mode' => 'master']],
			$fields,
			123
		);

		static::assertNotEmpty($result[0]['has_action']);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testPrepareTableBodyWithDuration(): void
	{
		$this->datatablePreparer->setTranslator($this->translatorMock);
		$usedPlaylists = [];
		$this->datatablePreparer->setUsedPlaylists($usedPlaylists);

		$fields = [$this->createMock(HeaderField::class)];
		$fields[0]->method('getName')->willReturn('duration');
		$fields[0]->method('isSortable')->willReturn(false);

		$this->prepareServiceMock->method('getBodyPreparer')
			->willReturn($this->bodyPreparerMock);
		$this->bodyPreparerMock->expects($this->once())->method('formatText')
			->with('00:00:12');

		$this->aclValidatorMock->method('isModuleAdmin')->willReturn(true);
		$this->aclValidatorMock->method('isSimpleAdmin')->willReturn(true);

		$result = $this->datatablePreparer->prepareTableBody(
			[
				[
					'playlist_id' => 1,
					'UID' => 13,
					'company_id' => 1,
					'username' => 'Günther',
					'duration' => 12,
					'playlist_name' => 'Playlist Name',
					'playlist_mode' => 'master']],
			$fields,
			123
		);

		static::assertNotEmpty($result[0]['has_action']);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testPrepareTableBodyWithPlaylistModes(): void
	{
		$this->datatablePreparer->setTranslator($this->translatorMock);
		$usedPlaylists = [];
		$this->datatablePreparer->setUsedPlaylists($usedPlaylists);

		$fields = [$this->createMock(HeaderField::class)];
		$fields[0]->method('getName')->willReturn('playlist_mode');
		$fields[0]->method('isSortable')->willReturn(false);

		$this->translatorMock->method('translateArrayForOptions')
			->with('playlist_mode_selects', 'playlists')
			->willReturn(['master' => 'Master']);
		$this->prepareServiceMock->method('getBodyPreparer')
			->willReturn($this->bodyPreparerMock);
		$this->bodyPreparerMock->expects($this->once())->method('formatText')
			->with('Master');

		$this->aclValidatorMock->method('isModuleAdmin')->willReturn(true);
		$this->aclValidatorMock->method('isSimpleAdmin')->willReturn(true);

		$result = $this->datatablePreparer->prepareTableBody(
			[
				[
					'playlist_id' => 1,
					'UID' => 13,
					'company_id' => 1,
					'username' => 'Horst',
					'duration' => 14,
					'playlist_name' => 'Playlist Name',
					'playlist_mode' => 'master'
				]
			],
			$fields,
			123
		);

		static::assertNotEmpty($result[0]['has_action']);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws \Doctrine\DBAL\Exception
	 */
	#[Group('units')]
	public function testPrepareTableBodyWithUnknown(): void
	{
		$fields = [$this->createMock(HeaderField::class)];
		$fields[0]->method('getName')->willReturn('unknown_param');
		$fields[0]->method('isSortable')->willReturn(false);

		$this->datatablePreparer->setTranslator($this->translatorMock);

		$bodyPreparerMock = $this->createMock(BodyPreparer::class);
		$bodyPreparerMock->method('formatText')
			->with('some_value')
			->willReturn(['formattedText' => 'some_value']);

		$this->prepareServiceMock->method('getBodyPreparer')->willReturn($bodyPreparerMock);

		$result = $this->datatablePreparer->prepareTableBody(
			[
				['playlist_id' => 1,
					'UID' => 13,
					'company_id' => 1,
					'unknown_param' => 'some_value',
					'playlist_name' => 'Playlist Name',
					'playlist_mode' => 'master', 'username' => 'MorkFromOrk', 'duration' => 12]
			],
			$fields,
			123
		);

		static::assertEquals('some_value', $result[0]['elements_result_element'][0]['is_text']['formattedText']);
	}

	/**
	 */
	#[Group('units')]
	public function testFormatPlaylistContextMenu(): void
	{
		$this->datatablePreparer->setTranslator($this->translatorMock);

		$this->translatorMock->method('translateArrayForOptions')
			->with('playlist_mode_selects', 'playlists')
			->willReturn([PlaylistMode::MASTER->value => 'Master', 'slave' => 'Slave']);

		$result = $this->datatablePreparer->formatPlaylistContextMenu();

		static::assertCount(2, $result);
		static::assertEquals(
			[
				['CREATE_PLAYLIST_MODE' => 'master', 'LANG_CREATE_PLAYLIST_MODE' => 'Master'],
				['CREATE_PLAYLIST_MODE' => 'slave', 'LANG_CREATE_PLAYLIST_MODE' => 'Slave']
			],
			$result
		);
	}

	/**
	 * @throws Exception
	 */
	#[Group('units')]
	public function testFormatPlaylistContextMenuEdgeForbidden(): void
	{
		$this->datatablePreparer->setTranslator($this->translatorMock);
		$configMock = $this->createMock(Config::class);
		$this->aclValidatorMock->method('getConfig')->willReturn($configMock);
		$configMock->method('getEdition')->willReturn(Config::PLATFORM_EDITION_EDGE);

		$this->translatorMock->method('translateArrayForOptions')
			->with('playlist_mode_selects', 'playlists')
			->willReturn([PlaylistMode::MASTER->value => 'Master', PlaylistMode::CHANNEL->value => 'Channel', PlaylistMode::EXTERNAL->value => 'External']);

		$result = $this->datatablePreparer->formatPlaylistContextMenu();

		static::assertCount(1, $result);
		static::assertEquals(
			[
				['CREATE_PLAYLIST_MODE' => 'master', 'LANG_CREATE_PLAYLIST_MODE' => 'Master']
			],
			$result
		);
	}

}
