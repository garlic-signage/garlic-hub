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
						$resultElements['is_link'] = $this->prepareService->getBodyPreparer()->formatLink(
							$player[$innerKey],
							$this->translator->translate('api_connectivity', 'player'),
							'/player/connectivity/'.$player['player_id'],
							'player_name_'.$player['player_id'],
						);
						break;
					default:
						$resultElements['is_text'] = $this->prepareService->getBodyPreparer()->formatText($player[$innerKey]);
						break;
				}
				$list['elements_result_element'][] = $resultElements;
			}

			$list['has_action'][] = $this->prepareService->getBodyPreparer()->formatAction(
				$this->translator->translate('player_actions', 'player'),
				'player-contextmenu',
				(string) $player['player_id'],
				'three-dots player-contextmenu',
			);


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
	 * @return array<string,string>
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function formatPlayerContextMenu(): array
	{
		return [
			'LANG_ASSIGN_PLAYLIST' => $this->translator->translate('select_playlist', 'player'),
			'LANG_UNASSIGN_PLAYLIST' => $this->translator->translate('remove_playlist', 'player'),
			'LANG_GOTO_PLAYLIST' => $this->translator->translate('goto_playlist', 'player'),
			'LANG_PUSH_PLAYLIST' => $this->translator->translate('push_playlist', 'player'),
			'LANG_PLAYER_DELETE' => $this->translator->translate('delete', 'main'),
			'LANG_PLAYER_DELETE_CONFIRM' => $this->translator->translate('delete_confirm', 'player')
		];
	}

/*
	private function convertSeconds(string $seconds): string
	{
		$dtT = new DateTime("@$seconds");
		return (new DateTime("@0"))->diff($dtT)->format('%H:%I:%S');
	}
*/
}