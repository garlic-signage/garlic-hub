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

namespace App\Modules\Playlists\Helper\Datatable;

use App\Framework\Core\Config\Config;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Utils\Datatable\AbstractDatatablePreparer;
use App\Framework\Utils\Datatable\PrepareService;
use App\Framework\Utils\Datatable\Results\HeaderField;
use App\Modules\Playlists\Helper\PlaylistMode;
use App\Modules\Playlists\Services\AclValidator;
use DateTime;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * This class is responsible for preparing data tables related to playlists.
 * It handles the transformation of raw data into a structured format suitable for rendering
 * in a tabular format. It also manages playlist-specific logic, such as determining actions,
 * formatting context menus, and ensuring proper ACL validation.
 */
class DatatablePreparer extends AbstractDatatablePreparer
{
	/** @var array<int,bool> */
	private array $usedPlaylists;

	private AclValidator $aclValidator;

	public function __construct(PrepareService $prepareService, AclValidator $aclValidator, Parameters $parameters)
	{
		$this->aclValidator = $aclValidator;
		parent::__construct('playlists', $prepareService, $parameters);
	}

	/**
	 * @param array<int,bool> $usedPlaylists
	 */
	public function setUsedPlaylists(array $usedPlaylists): static
	{
		$this->usedPlaylists = $usedPlaylists;
		return $this;
	}

	/**
	 * This method is cringe, but I do not have a better idea without starting over engineering
	 *
	 * @param list<array{"UID": int, "company_id": int, playlist_id:int, playlist_name:string, username:string, duration:int, playlist_mode: string,...}> $currentFilterResults
	 * @param list<HeaderField> $fields
	 * @return list<array<string,mixed>>
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function prepareTableBody(array $currentFilterResults, array $fields, int $currentUID): array
	{
		$body = [];
		foreach($currentFilterResults as $playlist)
		{
			$list            = [];
			$list['UNIT_ID'] = $playlist['playlist_id'];
			foreach($fields as $HeaderField)
			{
				$innerKey = $HeaderField->getName();

				$resultElements = [];
				$resultElements['CONTROL_NAME_BODY'] = $innerKey;
				switch ($innerKey)
				{
					case 'playlist_name':
						$resultElements['is_link'] = $this->prepareService->getBodyPreparer()->formatLink(
							$playlist['playlist_name'],
							$this->translator->translate('edit', 'main'),
							'playlists/compose/'.$playlist['playlist_id'],
							'playlist_name_'.$playlist['playlist_id']
						);
						break;
					case 'UID':
						$resultElements['is_UID'] = $this->prepareService->getBodyPreparer()->formatUID($playlist['UID'], $playlist['username']);
						break;
					case 'duration':
						$resultElements['is_text'] = $this->prepareService->getBodyPreparer()->formatText($this->convertSeconds((string) $playlist['duration']));
						break;
					case 'playlist_mode':
						$selectableModes = $this->translator->translateArrayForOptions('playlist_mode_selects', 'playlists');
						$resultElements['is_text'] = $this->prepareService->getBodyPreparer()->formatText($selectableModes[$playlist['playlist_mode']]);
						break;
					default:
						$resultElements['is_text'] = $this->prepareService->getBodyPreparer()->formatText((string) $playlist[$innerKey]);
						break;
				}
				$list['elements_result_element'][] = $resultElements;

				if ($playlist['UID'] == $currentUID || $this->aclValidator->isSimpleAdmin($currentUID))
				{
					$list['has_action'] = [
				/*		$this->prepareService->getBodyPreparer()->formatAction(
							$this->translator->translate('copy_playlist', 'playlists'),
							'playlists/?playlist_copy_id='.$playlist['playlist_id'],
							'copy', 'copy'), */
						$this->prepareService->getBodyPreparer()->formatAction(
							$this->translator->translate('edit_settings', 'playlists'),
							'playlists/settings/'.$playlist['playlist_id'],
							'edit', (string) $playlist['playlist_id'], 'pencil')
					];
					if (!array_key_exists($playlist['playlist_id'], $this->usedPlaylists) &&
						$this->aclValidator->isAllowedToDeletePlaylist($currentUID, $playlist))
					{
						$deleteText = $this->translator->translate('confirm_delete', 'playlists');
						$list['has_delete'] = $this->prepareService->getBodyPreparer()->formatActionDelete(
							$this->translator->translate('delete', 'main'),
							sprintf($deleteText, $playlist['playlist_name']),
							(string) $playlist['playlist_id'],
							'delete-playlist'
						);
					}

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
	public function formatPlaylistContextMenu(): array
	{
		$list = $this->translator->translateArrayForOptions('playlist_mode_selects', 'playlists');
		$data = [];
		$edition = $this->aclValidator->getConfig()->getEdition();
		foreach ($list as $key => $value)
		{
			if ($edition === Config::PLATFORM_EDITION_EDGE && !$this->isPlaylistModeAllowedInEdge($key))
				continue;

			$data[] = [
				'CREATE_PLAYLIST_MODE' => $key,
				'LANG_CREATE_PLAYLIST_MODE' => $value
			];
		}
		return $data;
	}

	private function isPlaylistModeAllowedInEdge(string $key): bool
	{
		if ($key === PlaylistMode::CHANNEL->value)
			return false;

		if ($key === PlaylistMode::EXTERNAL->value)
			return false;

		return true;
	}

	private function convertSeconds(string $seconds): string
	{
		$dtT = new DateTime("@$seconds");
		return new DateTime('@0')->diff($dtT)->format('%H:%I:%S');
	}
}