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

namespace App\Modules\Playlists\Helper\Compose;

use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * This class is responsible for preparing various UI templates for playlists.
 * It provides methods to build external editors, multizone editors, and circular editors.
 * These methods structure the template data and layout configuration for use in the application's interface.
 */
readonly class UiTemplatesPreparer
{
	CONST string MODULE_NAME = 'playlists';
	private Translator $translator;
	private RightsChecker $rightsChecker;

	public function __construct(Translator $translator, RightsChecker $rightsChecker)
	{
		$this->translator = $translator;
		$this->rightsChecker = $rightsChecker;
	}

	/**
	 * @param array<string,mixed> $playlist
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function buildExternalEditor(array $playlist): array
	{
		$title = $this->translator->translate('external_edit',  self::MODULE_NAME). ' ('.$playlist['playlist_name'].')';
		return [
			'main_layout' => [
				'LANG_PAGE_TITLE' => $title,
				'additional_css' => [
					'/css/playlists/external.css'
				],
				'footer_modules' => ['/js/playlists/compose/external/init.js']
			],
			'this_layout' => [
				'template' => 'playlists/external', // Template-name
				'data' => [
					'LANG_PAGE_HEADER' => $title,
					'PLAYLIST_ID' => $playlist['playlist_id'],
					'LANG_URL_TO_PLAYLIST' => $this->translator->translate('url_to_playlist', self::MODULE_NAME),
					'LANG_SAVE' => $this->translator->translate('save', 'main'),
					'LANG_CLOSE' => $this->translator->translate('close', 'main'),
				]
			]
		];
	}

	/**
	 * @param array<string,mixed> $playlist
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 */
	public function buildMultizoneEditor(array $playlist): array
	{
		$exportUnits = [];
		foreach ($this->translator->translateArrayForOptions('export_unit_selects','playlists') as $key => $value)
		{
			$exportUnits[] = ['LANG_OPTION' => $value, 'VALUE_OPTION' => $key];
		}
		$title = $this->translator->translate('zone_edit', self::MODULE_NAME). ' ('.$playlist['playlist_name'].')';
		return [
			'main_layout' => [
				'LANG_PAGE_TITLE' => $title,
				'additional_css' => ['/css/playlists/multizone.css'],
				'footer_scripts' => ['/js/external/fabric.min.js'],
				'footer_modules' => ['/js/playlists/compose/multizone/init.js']
			],
			'this_layout' => [
				'template' => 'playlists/multizone', // Template-name
				'data' => [
					'LANG_PAGE_HEADER' => $title,
					'LANG_DUPLICATE' => $this->translator->translate('duplicate', 'templates'),
					'LANG_DELETE' => $this->translator->translate('delete', 'main'),
					'LANG_MOVE_BACKGROUND' => $this->translator->translate('move_background', 'templates'),
					'LANG_MOVE_BACK'  => $this->translator->translate('move_back', 'templates'),
					'LANG_MOVE_FRONT' => $this->translator->translate('move_front', 'templates'),
					'LANG_MOVE_FOREGROUND' =>  $this->translator->translate('move_foreground', 'templates'),
					'PLAYLIST_ID' => $playlist['playlist_id'],
					'LANG_ADD_ZONE' => $this->translator->translate('add_zone', 'playlists'),
					'LANG_MULTIZONE_EXPORT_UNIT' => $this->translator->translate('multizone_export_unit', self::MODULE_NAME),
					'export_units' => $exportUnits,
					'LANG_SCREEN_RESOLUTION' =>  $this->translator->translate('screen_resolution', self::MODULE_NAME),
					'LANG_ZOOM' => $this->translator->translate('zoom', 'main'),
					'LANG_WIDTH' => $this->translator->translate('zone_width', self::MODULE_NAME),
					'LANG_HEIGHT' => $this->translator->translate('zone_height', self::MODULE_NAME),
					'LANG_INSERT' => $this->translator->translate('insert', 'main'),
					'LANG_SAVE'  => $this->translator->translate('save', 'main'),
					'LANG_CLOSE' => $this->translator->translate('close', 'main'),
					'LANG_CANCEL' => $this->translator->translate('cancel', 'main'),
					'LANG_TRANSFER' => $this->translator->translate('transfer', 'main'),
					'LANG_PLAYLIST_NAME' => $this->translator->translate('playlist_name', self::MODULE_NAME),
					'LANG_ZONE_PROPERTIES' => $this->translator->translate('zone_properties', self::MODULE_NAME),
					'LANG_ZONES_SELECTS' => $this->translator->translate('zones_select', self::MODULE_NAME),
					'LANG_ZONE_NAME' => $this->translator->translate('zone_name', self::MODULE_NAME),
					'LANG_ZONE_LEFT' => $this->translator->translate('zone_left', self::MODULE_NAME),
					'LANG_ZONE_TOP' => $this->translator->translate('zone_top', self::MODULE_NAME),
					'LANG_ZONE_WIDTH' => $this->translator->translate('zone_width', self::MODULE_NAME),
					'LANG_ZONE_HEIGHT' => $this->translator->translate('zone_height', self::MODULE_NAME),
					'LANG_ZONE_BGCOLOR' => $this->translator->translate('zone_bgcolor', self::MODULE_NAME),
					'LANG_ZONE_TRANSPARENT' => $this->translator->translate('zone_transparent', self::MODULE_NAME),
					'LANG_CONFIRM_CLOSE_EDITOR' => $this->translator->translate('confirm_close_editor', self::MODULE_NAME)
				]
			]
		];
	}

	/**
	 * @param array<string,mixed> $playlist
	 * @return array<string,mixed>
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function buildCircularEditor(array $playlist): array
	{
		$title = $this->translator->translate('composer', self::MODULE_NAME). ' ('.$playlist['playlist_name'].')';
		return [
			'main_layout' => [
				'LANG_PAGE_TITLE' => $title,
				'additional_css' => [
					'/css/external/wunderbaum.css',
					'/css/external/dragula.min.css',
					'/css/mediapool/selector.css',
					'/css/external/nouislider.min.css',
					'/css/playlists/composer.css',
					'/css/playlists/trigger.css',
					'/css/playlists/conditional-play.css'
				],
				'footer_modules' => ['/js/external/dragula.min.js','/js/playlists/compose/standard/init.js']
			],
			'this_layout' => [
				'template' => 'playlists/compose', // Template-name
				'data' => [
					'LANG_PAGE_HEADER' => $title,
					'PLAYLIST_ID' => $playlist['playlist_id'],
					'CMS_EDITION' => $this->rightsChecker->getEdition(),
					'LANG_INSERT' => $this->translator->translate('insert', self::MODULE_NAME),
					'LANG_INSERT_MEDIA' => $this->translator->translate('insert_media', self::MODULE_NAME),
					'can_external_media' => $this->rightsChecker->checkInsertExternalMedia(),
					'can_playlists' => $this->rightsChecker->checkInsertPlaylist($playlist['time_limit']),
					'can_external_playlists' => $this->rightsChecker->checkInsertExternalPlaylist($playlist['time_limit']),
					'can_templates' => $this->rightsChecker->checkInsertTemplates(),
					'can_channels' => $this->rightsChecker->checkInsertChannels(),
					'LANG_PLAYLIST_DURATION' => $this->translator->translate('duration', self::MODULE_NAME),
					'LANG_TOTAL' => $this->translator->translate('total_media', self::MODULE_NAME),
					'LANG_TOTAL_FILESIZE' => $this->translator->translate('total_filesize', self::MODULE_NAME),
					'has_time_limit' => $this->rightsChecker->checkTimeLimit($playlist['time_limit']),
					'LANG_SHUFFLE' => $this->translator->translate('shuffle', self::MODULE_NAME),
					'LANG_PICKING_OPTION_ALL' => $this->translator->translate('all', self::MODULE_NAME),
					'LANG_PICKING_MEDIA_PER_CYCLE' => $this->translator->translate('picking_media_per_cycle', self::MODULE_NAME),
					'LANG_PLAYER_EXPORT' => $this->translator->translate('player_export', self::MODULE_NAME),
					'LANG_PLAYLIST_PREVIEW' => $this->translator->translate('preview', self::MODULE_NAME),
					'LANG_ITEM_NAME' => $this->translator->translate('item_name', self::MODULE_NAME),
					'LANG_SAVE' => $this->translator->translate('save', 'main'),
					'LANG_CANCEL' => $this->translator->translate('cancel', 'main')
				]
			]
		];
	}

}