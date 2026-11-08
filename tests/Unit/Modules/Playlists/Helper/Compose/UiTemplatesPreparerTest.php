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
use App\Modules\Playlists\Helper\Compose\MultizonePresets;
use App\Modules\Playlists\Helper\Compose\RightsChecker;
use App\Modules\Playlists\Helper\Compose\UiTemplatesPreparer;
use App\Modules\Playlists\Services\PlaylistRelease;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class UiTemplatesPreparerTest extends TestCase
{
	private Translator&Stub $translatorStub;
	private RightsChecker&Stub $rightsCheckerStub;
	private PlaylistRelease&Stub $playlistReleaseStub;
	private MultizonePresets&Stub $multizonePresetsStub;
	private UiTemplatesPreparer $preparer;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->translatorStub   = $this->createStub(Translator::class);
		$this->rightsCheckerStub = $this->createStub(RightsChecker::class);
		$this->playlistReleaseStub = $this->createStub(PlaylistRelease::class);
		$this->multizonePresetsStub = $this->createStub(MultizonePresets::class);

		$this->preparer = new UiTemplatesPreparer($this->translatorStub, $this->rightsCheckerStub, $this->playlistReleaseStub, $this->multizonePresetsStub);
	}


	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildExternalEditor(): void
	{
		$playlist = ['playlist_id' => 123, 'playlist_name' => 'My Playlist'];

		$this->translatorStub->method('translate')
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
		$playlist = ['playlist_id' => 456, 'playlist_name' => 'Multizone Playlist'];

		$this->translatorStub->method('translate')
			->willReturnMap([
				['zone_edit', UiTemplatesPreparer::MODULE_NAME, [], 'Zone Editor'],
				['duplicate', 'templates', [], 'Duplicate'],
				['delete', 'main', [], 'Delete'],
				['move_background', 'templates', [], 'Move Background'],
				['move_back', 'templates', [], 'Move Back'],
				['move_front', 'templates', [], 'Move Front'],
				['move_foreground', 'templates', [], 'Move Foreground'],
				['add_zone', UiTemplatesPreparer::MODULE_NAME, [], 'Add Zone'],
				['multizone_presets', UiTemplatesPreparer::MODULE_NAME, [], 'Layout presets'],
				['confirm_multizone_preset', UiTemplatesPreparer::MODULE_NAME, [], 'Replace?'],
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

		$this->translatorStub->method('translateArrayForOptions')
			->willReturnMap([
				['export_unit_selects', UiTemplatesPreparer::MODULE_NAME, ['unit_one' => 'Unit One', 'unit_two' => 'Unit Two']],
				['multizone_preset_selects', UiTemplatesPreparer::MODULE_NAME, ['fullscreen' => 'Fullscreen']]
			]);
		// a preset without a translation shows its id
		$fullscreen = ['left' => 0, 'top' => 0, 'width' => 100, 'height' => 100, 'bgcolor' => '#4a6fa5'];
		$this->multizonePresetsStub->method('load')->willReturn([
			['id' => 'fullscreen', 'zones' => [$fullscreen]],
			['id' => 'own_layout', 'zones' => [$fullscreen]]
		]);

		$result = $this->preparer->buildMultizoneEditor($playlist);

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
					'LANG_MULTIZONE_PRESETS' => 'Layout presets',
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
					'LANG_JS' => '{"confirm_close":"Confirm Close Editor","confirm_preset":"Replace?","presets":"Layout presets","close":"Close"}',
					'PRESETS_JS' => '{"presets":[{"id":"fullscreen","name":"Fullscreen","zones":[{"left":0,"top":0,"width":100,"height":100,"bgcolor":"#4a6fa5"}]},'
						. '{"id":"own_layout","name":"own_layout","zones":[{"left":0,"top":0,"width":100,"height":100,"bgcolor":"#4a6fa5"}]}]}'
				]
			]
		];

		static::assertSame($expected, $result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildCircularEditor(): void
	{
		$playlist = ['playlist_id' => 789, 'playlist_name' => 'Circular Playlist', 'time_limit' => 3600];
		$this->playlistReleaseStub->method('mayExport')->willReturn(true);
		$this->stubCircularEditor($playlist);

		$result = $this->preparer->buildCircularEditor($playlist);

		static::assertSame($this->expectedCircularEditor('Player Export', '0'), $result);
	}

	/**
	 * A user with an AI for texts gets "fill with AI" in the insert menu.
	 *
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildCircularEditorOffersTheFillingWithAi(): void
	{
		$playlist = ['playlist_id' => 789, 'playlist_name' => 'Circular Playlist', 'time_limit' => 3600];
		$this->playlistReleaseStub->method('mayExport')->willReturn(true);
		$this->stubCircularEditor($playlist);

		$result = $this->preparer->buildCircularEditor($playlist, true);

		static::assertSame([
			'LANG_FILL_WITH_AI' => 'Fill with AI',
			'LANG_AI_THEME' => 'Theme',
			'LANG_AI_THEME_HINT' => 'e.g. summer',
			'LANG_AI_MINUTES' => 'Minutes',
			'LANG_AI_SUGGEST' => 'Suggest',
			'LANG_AI_INSERT_CHECKED' => 'Insert ticked'
		], $result['this_layout']['data']['can_fill_with_ai']);
	}

	/**
	 * While the release is on, a user who may not export asks for the release with the export button.
	 *
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildCircularEditorAsksForTheReleaseWhenTheUserMayNotExport(): void
	{
		$playlist = ['playlist_id' => 789, 'playlist_name' => 'Circular Playlist', 'time_limit' => 3600];
		$this->playlistReleaseStub->method('mayExport')->willReturn(false);
		$this->stubCircularEditor($playlist);

		$result = $this->preparer->buildCircularEditor($playlist);

		static::assertSame($this->expectedCircularEditor('Ready for release', '1'), $result);
	}

	/**
	 * While the release is on, the admin exports and sees whether the playlist waits for him.
	 *
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildCircularEditorShowsTheAdminWhetherThePlaylistWaits(): void
	{
		$playlist = ['playlist_id' => 789, 'playlist_name' => 'Circular Playlist', 'time_limit' => 3600];
		$this->playlistReleaseStub->method('mayExport')->willReturn(true);
		$this->playlistReleaseStub->method('isEnabled')->willReturn(true);
		$this->stubCircularEditor($playlist);

		$result = $this->preparer->buildCircularEditor($playlist);

		static::assertSame($this->expectedCircularEditor('Player Export', '0', ['LANG_RELEASE_REQUESTED' => 'Waiting for release']), $result);
	}

	/**
	 * @param array{time_limit: int, ...} $playlist
	 */
	private function stubCircularEditor(array $playlist): void
	{
		$this->translatorStub->method('translateArrayForOptions')
			->willReturnMap([['release_status_selects', UiTemplatesPreparer::MODULE_NAME, ['in_progress' => 'In progress', 'ready' => 'Waiting for release', 'released' => 'Released']]]);

		$this->translatorStub->method('translate')
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
				['request_release', UiTemplatesPreparer::MODULE_NAME, [], 'Ready for release'],
				['release_requested', UiTemplatesPreparer::MODULE_NAME, [], 'Waiting for release'],
				['item_name', UiTemplatesPreparer::MODULE_NAME, [], 'Item Name'],
				['preview', UiTemplatesPreparer::MODULE_NAME, [], 'Playlist Preview'],
				['save', 'main', [], 'Save'],
				['cancel', 'main', [], 'Cancel'],
				['ai_inserted', UiTemplatesPreparer::MODULE_NAME, [], 'Inserted'],
				['fill_with_ai', UiTemplatesPreparer::MODULE_NAME, [], 'Fill with AI'],
				['ai_theme', UiTemplatesPreparer::MODULE_NAME, [], 'Theme'],
				['ai_theme_hint', UiTemplatesPreparer::MODULE_NAME, [], 'e.g. summer'],
				['ai_minutes', UiTemplatesPreparer::MODULE_NAME, [], 'Minutes'],
				['ai_suggest', UiTemplatesPreparer::MODULE_NAME, [], 'Suggest'],
				['ai_insert_checked', UiTemplatesPreparer::MODULE_NAME, [], 'Insert ticked']
			]);

		$this->rightsCheckerStub->method('getEdition')
			->willReturn('Pro Edition');

		$this->rightsCheckerStub->method('checkInsertExternalMedia')
			->willReturn([]);

		$this->rightsCheckerStub->method('checkInsertPlaylist')
			->willReturnMap([[$playlist['time_limit'], []]]);

		$this->rightsCheckerStub->method('checkInsertExternalPlaylist')
			->willReturnMap([[$playlist['time_limit'], []]]);

		$this->rightsCheckerStub->method('checkInsertTemplates')
			->willReturn([]);

		$this->rightsCheckerStub->method('checkInsertChannels')
			->willReturn([]);

		$this->rightsCheckerStub->method('checkTimeLimit')
			->willReturnMap([[$playlist['time_limit'], []]]);
	}

	/**
	 * @param array<string,string> $releaseWaiting
	 * @return array<string,mixed>
	 */
	private function expectedCircularEditor(string $exportLabel, string $releaseRequest, array $releaseWaiting = []): array
	{
		return [
			'main_layout' => [
				'LANG_PAGE_TITLE' => 'Composer (Circular Playlist)',
				'additional_css' => [
					'/css/external/wunderbaum.css',
					'/css/external/dragula.min.css',
					'/css/mediapool/selector.css',
					'/css/external/nouislider.min.css',
					'/css/playlists/composer.css',
					'/css/playlists/trigger.css',
					'/css/playlists/conditional-play.css',
					'/css/external/glightbox.min.css'
				],
				'footer_scripts' => ['/js/external/glightbox.min.js'],
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
					'can_fill_with_ai' => [],
					'LANG_PLAYLIST_DURATION' => 'Duration',
					'LANG_TOTAL' => 'Total Media',
					'LANG_TOTAL_FILESIZE' => 'Total Filesize',
					'has_time_limit' => [],
					'LANG_SHUFFLE' => 'Shuffle',
					'LANG_JS' => '{"picking_all":"All","request_release":"Ready for release","release_requested":"Waiting for release","released":"Released","ai_inserted":"Inserted"}',
					'LANG_PICKING_MEDIA_PER_CYCLE' => 'Picking Media Per Cycle',
					'LANG_PLAYER_EXPORT' => $exportLabel,
					'RELEASE_REQUEST' => $releaseRequest,
					'release_waiting' => $releaseWaiting,
					'LANG_PLAYLIST_PREVIEW' => 'Playlist Preview',
					'LANG_ITEM_NAME' => 'Item Name',
					'LANG_SAVE' => 'Save',
					'LANG_CANCEL' => 'Cancel'
				],
			],
		];
	}
}
