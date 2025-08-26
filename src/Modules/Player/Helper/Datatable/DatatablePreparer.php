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

namespace App\Modules\Player\Helper\Datatable;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\Datatable\AbstractDatatablePreparer;
use App\Framework\Utils\Datatable\PrepareService;
use App\Framework\Utils\Datatable\Results\HeaderField;
use App\Framework\Utils\Datatable\TimeUnitsCalculator;
use App\Modules\Player\Enums\PlayerStatus;
use DateMalformedStringException;
use DateTime;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Class DatatablePreparer
 *
 * Extends the AbstractDatatablePreparer to process and organize data for use in datatables. This class
 * handles the preparation of table body and player context menus with necessary translations, formatting,
 * and element mapping.
 */
class DatatablePreparer extends AbstractDatatablePreparer
{
	private TimeUnitsCalculator $timeUnitsCalculator;

	public function __construct(PrepareService $prepareService, Parameters $parameters, TimeUnitsCalculator $timeUnitsCalculator)
	{
		$this->timeUnitsCalculator = $timeUnitsCalculator;

		parent::__construct('player', $prepareService, $parameters);
	}

	/**
	 * This method is cringe, but I do not have a better idea without starting over engineering
	 *
	 * @param list<array<string,mixed>> $currentFilterResults
	 * @param list<HeaderField> $fields
	 * @param int $currentUID
	 * @return list<array<string,mixed>>
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws DateMalformedStringException
	 */
	public function prepareTableBody(array $currentFilterResults, array $fields, int $currentUID): array
	{
		$body = [];
		foreach($currentFilterResults as $player)
		{
			$list            = [];
			$list['UNIT_ID'] = $player['player_id'];
			foreach($fields as $HeaderField)
			{
				$innerKey = $HeaderField->getName();

				$resultElements = [];
				$resultElements['CONTROL_NAME_BODY'] = $innerKey;
				switch ($innerKey)
				{
					case 'last_access':

						$lastAccessSince = $this->timeUnitsCalculator->calculateLastAccess(
							new DateTime('now'), new DateTime($player['last_access'])
						)->getLastAccessTimeStamp();

						if ($lastAccessSince < (2 * $player['refresh']))
							$class = 'player-active';
						else if ($lastAccessSince < (4 * $player['refresh']))
							$class = 'player-pending';
						else
							$class = 'player-inactive';

						$resultElements['is_span'] = $this->prepareService->getBodyPreparer()->formatSpan(
							$this->timeUnitsCalculator->printDistance(),
							$player['last_access'],
							'last_access',
							$class
						);
						break;
					case 'UID':
						$resultElements['is_UID'] = $this->prepareService->getBodyPreparer()->formatUID($player['UID'], $player['username']);
						break;
					case 'status':
						$title = $this->translator->translateArrayForOptions('status_selects', 'player')[$player['status']];
						if ($player['status'] == PlayerStatus::RELEASED->value)
							$iconClass = 'bi bi-check';
						else if ($player['status'] == PlayerStatus::UNRELEASED->value)
							$iconClass = 'bi bi-x';
						else
							$iconClass = 'bi bi-bug';

						$resultElements['is_icon'] = $this->prepareService->getBodyPreparer()->formatIcon($iconClass, $title);

						break;
					case 'model':
						$resultElements['is_text'] = $this->prepareService->getBodyPreparer()->formatText($this->translator->translateArrayForOptions('model_selects', 'player')[$player['model']]);
						break;
					case 'playlist_id':
						if ($player['status'] == PlayerStatus::RELEASED->value && $player['playlist_id'] > 0)
							$resultElements['is_text'] = $this->prepareService->getBodyPreparer()->formatText($player['playlist_name']);
						else
							$resultElements['is_text'] = '';
						break;
					case 'player_name':
						$resultElements['is_button'] = $this->prepareService->getBodyPreparer()->formatButton(
							'',
							$this->translator->translate('player_settings_menu', 'player'),
							(string) $player['player_id'],
							'player-contextmenu bi bi-three-dots',
						);
						$resultElements['is_text'] = $this->prepareService->getBodyPreparer()->formatText('<span>'.$player[$innerKey].'</span>');
						break;
					default:
						$resultElements['is_text'] = $this->prepareService->getBodyPreparer()->formatText($player[$innerKey]);
						break;
				}
				$list['elements_result_element'][] = $resultElements;
			}
			if ($player['status'] == PlayerStatus::RELEASED->value)
			{
				$translation = $this->translator->translate('select_playlist', 'player');
				$list['has_action'][] = $this->prepareService->getBodyPreparer()->formatAction(
					$translation,'#','edit', $player['playlist_id'], 'pencil select-playlist'
				);

				if ($player['playlist_id'] > 0)
				{
					$translation = $this->translator->translate('remove_playlist', 'player');

					$list['has_action'][] = $this->prepareService->getBodyPreparer()->formatAction(
						$translation, '#','playlist', $player['playlist_id'], 'x-circle remove-playlist'
					);
					if ($player['is_intranet'] > 0)
					{
						$translation = $this->translator->translate('push_playlist', 'player');
						$list['has_action'][] = $this->prepareService->getBodyPreparer()->formatAction(
							$translation, '#','push', $player['player_id'], 'arrow-left-circle-fill push-playlist'
						);
					}

					$translation = $this->translator->translate('goto_playlist', 'player');
					$link = '/playlists/compose/' . $player['playlist_id'];

					$list['has_action'][] = $this->prepareService->getBodyPreparer()->formatAction(
						$translation, $link, 'playlist', $player['playlist_id'], 'music-note-list playlist-link'
					);
				}
			}
			$body[] = $list;
		}

		return $body;

/*		$data['LANG_SELECT_ALL', 	$this->translator->translate('select_all', 'main'));
		$data['LANG_DESELECT_ALL', 	$this->translator->translate('deselect_all', 'main'));
		foreach ($this->translator->getTranslationsArrayForOptions('action_selects', 'playlists') as $key => $action)
		{
			$data['select_action' ] = [
				'OPTION_SELECTED_ACTION_ID' => $key,
				'OPTION_SELECTED_ACTION_NAME' => $action
			];
		}
*/
	}

	/**
	 * @return list<array<string,mixed>>
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function formatPlayerContextMenu(): array
	{
		$list = $this->translator->translateArrayForOptions('player_settings_selects', 'player');
		$data = [];

		foreach ($list as $key => $value)
		{
			$data[] = [
				'PLAYER_SETTING' => $key,
				'LANG_PLAYER_SETTING' => $value
			];
		}
		return $data;
	}


/*
	private function convertSeconds(string $seconds): string
	{
		$dtT = new DateTime("@$seconds");
		return (new DateTime("@0"))->diff($dtT)->format('%H:%I:%S');
	}
*/
}