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

namespace Tests\Unit\Modules\Playlists\Helper\Compose;

use App\Framework\Localization\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Modules\Playlists\Helper\Compose\RightsChecker;
use App\Modules\Playlists\Helper\Compose\UiTemplatesPreparer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UiTemplatesPreparerTest extends TestCase
{
	private Translator&MockObject $translatorMock;
	private RightsChecker&MockObject $rightsCheckerMock;
	private UiTemplatesPreparer $preparer;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->translatorMock   = $this->createMock(Translator::class);
		$this->rightsCheckerMock = $this->createMock(RightsChecker::class);

		$this->preparer = new UiTemplatesPreparer($this->translatorMock, $this->rightsCheckerMock);
	}


	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildExternalEditor(): void
	{
		$this->rightsCheckerMock->expects($this->never())->method($this->anything());
		$playlist = ['playlist_id' => 123, 'playlist_name' => 'My Playlist'];

		$this->translatorMock->expects($this->exactly(4))
			->method('translate')
			->willReturnMap([
				['external_edit', UiTemplatesPreparer::MODULE_NAME, [], 'External Editor'],
				['url_to_playlist', UiTemplatesPreparer::MODULE_NAME, [], 'URL to Playlist'],
				['save', 'main', [], 'Save'],
				['close', 'main', [], 'Close']
			]);

		$result = $this->preparer->buildExternalEditor($playlist);

		$expected = [
			'main_layout' => [
				'LANG_PAGE_TITLE' => 'External Editor (My Playlist)',
				'additional_css' => ['/css/playlists/external.css'],
				'footer_modules' => ['/js/playlists/compose/external/init.js'],
			],
			'this_layout' => [
				'template' => 'playlists/external',
				'data' => [
					'LANG_PAGE_HEADER' => 'External Editor (My Playlist)',
					'PLAYLIST_ID' => 123,
					'LANG_URL_TO_PLAYLIST' => 'URL to Playlist',
					'LANG_SAVE' => 'Save',
					'LANG_CLOSE' => 'Close',
				],
			],
		];

		static::assertSame($expected, $result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildMultizoneEditor(): void
	{
		$this->rightsCheckerMock->expects($this->never())->method($this->anything());
		$playlist = ['playlist_id' => 456, 'playlist_name' => 'Multizone Playlist'];

		$this->translatorMock->expects($this->exactly(29))
			->method('translate')
			->willReturnMap([
				['zone_edit', UiTemplatesPreparer::MODULE_NAME, [], 'Zone Editor'],
				['duplicate', 'templates', [], 'Duplicate'],
				['delete', 'main', [], 'Delete'],
				['move_background', 'templates', [], 'Move Background'],
				['move_back', 'templates', [], 'Move Back'],
				['move_front', 'templates', [], 'Move Front'],
				['move_foreground', 'templates', [], 'Move Foreground'],
				['add_zone', UiTemplatesPreparer::MODULE_NAME, [], 'Add Zone'],
				['multizone_export_unit', UiTemplatesPreparer::MODULE_NAME, [], 'Export Unit'],
				['screen_resolution', UiTemplatesPreparer::MODULE_NAME, [], 'Screen Resolution'],
				['zoom', 'main', [], 'Zoom'],
				['zone_width', UiTemplatesPreparer::MODULE_NAME, [], 'Width'],
				['zone_height', UiTemplatesPreparer::MODULE_NAME, [], 'Height'],
				['insert', 'main', [], 'Insert'],
				['save', 'main', [], 'Save'],
				['close', 'main', [], 'Close'],
				['cancel', 'main', [], 'Cancel'],
				['transfer', 'main', [], 'Transfer'],
				['playlist_name', UiTemplatesPreparer::MODULE_NAME, [], 'Playlist Name'],
				['zone_properties', UiTemplatesPreparer::MODULE_NAME, [], 'Zone Properties'],
				['zones_select', UiTemplatesPreparer::MODULE_NAME, [], 'Zones Select'],
				['zone_name', UiTemplatesPreparer::MODULE_NAME, [], 'Zone Name'],
				['zone_left', UiTemplatesPreparer::MODULE_NAME, [], 'Zone Left'],
				['zone_top', UiTemplatesPreparer::MODULE_NAME, [], 'Zone Top'],
				['zone_bgcolor', UiTemplatesPreparer::MODULE_NAME, [], 'Background Color'],
				['zone_transparent', UiTemplatesPreparer::MODULE_NAME, [], 'Transparent'],
				['confirm_close_editor', UiTemplatesPreparer::MODULE_NAME, [], 'Confirm Close Editor']
			]);

		$this->translatorMock->expects($this->once())
			->method('translateArrayForOptions')
			->with('export_unit_selects', UiTemplatesPreparer::MODULE_NAME)
			->willReturn([
				'unit_one' => 'Unit One',
				'unit_two' => 'Unit Two',
			]);

		$this->preparer->buildMultizoneEditor($playlist);

		$expected = [
			'main_layout' => [
				'LANG_PAGE_TITLE' => 'Zone Editor (Multizone Playlist)',
				'additional_css' => ['/css/playlists/multizone.css'],
				'footer_scripts' => ['/js/external/fabric.min.js'],
				'footer_modules' => ['/js/playlists/compose/multizone/init.js'],
			],
			'this_layout' => [
				'template' => 'playlists/multizone',
				'data' => [
					'LANG_PAGE_HEADER' => 'Zone Editor (Multizone Playlist)',
					'LANG_DUPLICATE' => 'Duplicate',
					'LANG_DELETE' => 'Delete',
					'LANG_MOVE_BACKGROUND' => 'Move Background',
					'LANG_MOVE_BACK' => 'Move Back',
					'LANG_MOVE_FRONT' => 'Move Front',
					'LANG_MOVE_FOREGROUND' => 'Move Foreground',
					'PLAYLIST_ID' => 456,
					'LANG_ADD_ZONE' => 'Add Zone',
					'LANG_MULTIZONE_EXPORT_UNIT' => 'Export Unit',
					'export_units' => [
						['LANG_OPTION' => 'Unit One', 'VALUE_OPTION' => 'unit_one'],
						['LANG_OPTION' => 'Unit Two', 'VALUE_OPTION' => 'unit_two'],
					],
					'LANG_SCREEN_RESOLUTION' => 'Screen Resolution',
					'LANG_ZOOM' => 'Zoom',
					'LANG_WIDTH' => 'Width',
					'LANG_HEIGHT' => 'Height',
					'LANG_INSERT' => 'Insert',
					'LANG_SAVE' => 'Save',
					'LANG_PLAYER_EXPORT' => 'Player Export',
					'LANG_CLOSE' => 'Close',
					'LANG_CANCEL' => 'Cancel',
					'LANG_TRANSFER' => 'Transfer',
					'LANG_PLAYLIST_NAME' => 'Playlist Name',
					'LANG_ZONE_PROPERTIES' => 'Zone Properties',
					'LANG_ZONES_SELECTS' => 'Zones Select',
					'LANG_ZONE_NAME' => 'Zone Name',
					'LANG_ZONE_LEFT' => 'Zone Left',
					'LANG_ZONE_TOP' => 'Zone Top',
					'LANG_ZONE_WIDTH' => 'Width',
					'LANG_ZONE_HEIGHT' => 'Height',
					'LANG_ZONE_BGCOLOR' => 'Background Color',
					'LANG_ZONE_TRANSPARENT' => 'Transparent',
					'LANG_JS' => '{"confirm_close":"Confirm Close Editor"}'
				]
			]
		];
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildCircularEditor(): void
	{
		$playlist = ['playlist_id' => 789, 'playlist_name' => 'Circular Playlist', 'time_limit' => 3600];

		$this->translatorMock->expects($this->exactly(14))
			->method('translate')
			->willReturnMap([
				['composer', UiTemplatesPreparer::MODULE_NAME, [], 'Composer'],
				['insert', UiTemplatesPreparer::MODULE_NAME, [], 'Insert'],
				['insert_media', UiTemplatesPreparer::MODULE_NAME, [], 'Insert Media'],
				['duration', UiTemplatesPreparer::MODULE_NAME, [], 'Duration'],
				['total_media', UiTemplatesPreparer::MODULE_NAME, [], 'Total Media'],
				['total_filesize', UiTemplatesPreparer::MODULE_NAME, [], 'Total Filesize'],
				['shuffle', UiTemplatesPreparer::MODULE_NAME, [], 'Shuffle'],
				['all', UiTemplatesPreparer::MODULE_NAME, [], 'All'],
				['picking_media_per_cycle', UiTemplatesPreparer::MODULE_NAME, [], 'Picking Media Per Cycle'],
				['player_export', UiTemplatesPreparer::MODULE_NAME, [], 'Player Export'],
				['item_name', UiTemplatesPreparer::MODULE_NAME, [], 'Item Name'],
				['preview', UiTemplatesPreparer::MODULE_NAME, [], 'Playlist Preview'],
				['save', 'main', [], 'Save'],
				['cancel', 'main', [], 'Cancel']
			]);

		$this->rightsCheckerMock->expects($this->once())
			->method('getEdition')
			->willReturn('Pro Edition');

		$this->rightsCheckerMock->expects($this->once())
			->method('checkInsertExternalMedia')
			->willReturn([]);

		$this->rightsCheckerMock->expects($this->once())
			->method('checkInsertPlaylist')
			->with($playlist['time_limit'])
			->willReturn([]);

		$this->rightsCheckerMock->expects($this->once())
			->method('checkInsertExternalPlaylist')
			->with($playlist['time_limit'])
			->willReturn([]);

		$this->rightsCheckerMock->expects($this->once())
			->method('checkInsertTemplates')
			->willReturn([]);

		$this->rightsCheckerMock->expects($this->once())
			->method('checkInsertChannels')
			->willReturn([]);

		$this->rightsCheckerMock->expects($this->once())
			->method('checkTimeLimit')
			->with($playlist['time_limit'])
			->willReturn([]);

		$result = $this->preparer->buildCircularEditor($playlist);

		$expected = [
			'main_layout' => [
				'LANG_PAGE_TITLE' => 'Composer (Circular Playlist)',
				'additional_css' => [
					'/css/external/wunderbaum.css',
					'/css/external/dragula.min.css',
					'/css/mediapool/selector.css',
					'/css/external/nouislider.min.css',
					'/css/playlists/composer.css',
					'/css/playlists/trigger.css',
					'/css/playlists/conditional-play.css'
				],
				'footer_modules' => ['/js/external/dragula.min.js', '/js/playlists/compose/standard/init.js'],
			],
			'this_layout' => [
				'template' => 'playlists/compose',
				'data' => [
					'LANG_PAGE_HEADER' => 'Composer (Circular Playlist)',
					'PLAYLIST_ID' => 789,
					'CMS_EDITION' => 'Pro Edition',
					'LANG_INSERT' => 'Insert',
					'LANG_INSERT_MEDIA' => 'Insert Media',
					'can_external_media' => [],
					'can_playlists' => [],
					'can_external_playlists' => [],
					'can_templates' => [],
					'can_channels' => [],
					'LANG_PLAYLIST_DURATION' => 'Duration',
					'LANG_TOTAL' => 'Total Media',
					'LANG_TOTAL_FILESIZE' => 'Total Filesize',
					'has_time_limit' => [],
					'LANG_SHUFFLE' => 'Shuffle',
					'LANG_JS' => '{"picking_all":"All"}',
					'LANG_PICKING_MEDIA_PER_CYCLE' => 'Picking Media Per Cycle',
					'LANG_PLAYER_EXPORT' => 'Player Export',
					'LANG_PLAYLIST_PREVIEW' => 'Playlist Preview',
					'LANG_ITEM_NAME' => 'Item Name',
					'LANG_SAVE' => 'Save',
					'LANG_CANCEL' => 'Cancel'
				],
			],
		];

		static::assertSame($expected, $result);
	}


}
