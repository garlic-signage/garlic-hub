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

namespace App\Modules\Playlists\Services;

use App\Framework\Exceptions\AccessDeniedException;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\NotFoundException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Exceptions\UserException;
use App\Framework\Helper\Serialization;
use App\Framework\Services\AbstractBaseService;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;

class TriggerService extends AbstractBaseService
{
	public function __construct(private readonly ItemsService $itemService, LoggerInterface $logger)
	{
		parent::__construct($logger);
	}

	/**
	 * @return array{item_data: array<string,mixed>, touchable_media: list<array{item_id:int, item_name:string}>}
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws NotFoundException
	 * @throws UserException
	 */
	public function fetchBeginTriggerByItemId(int $itemId): array
	{
		$item = $this->itemService->fetchItemById($itemId); // check rights on playlists, too

		$item['begin_trigger'] = Serialization::unserializeSecure($item['begin_trigger']);

		return ['item_data' => $item, 'touchable_media' => $this->findTouchableMedia($item['playlist_id'], $itemId)];
	}

	/**
	 * Saving an unchanged trigger changes no rows, which is no error.
	 *
	 * @param array<string,mixed> $requestData
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws NotFoundException
	 * @throws UserException
	 */
	public function saveBeginTrigger(int $itemId, array $requestData): void
	{
		$item = $this->itemService->fetchItemById($itemId); // check rights on playlists, too

		// an item can only be touched in the playlist it is in
		$touchableIds = array_column($this->findTouchableMedia($item['playlist_id'], $itemId), 'item_id');
		foreach ($requestData['touches'] ?? [] as $touch)
		{
			if (!in_array((int) $touch['touch_item_id'], array_map('intval', $touchableIds), true))
				throw new TranslatableModuleException('playlists', 'invalid_trigger_data', ['item_id' => $itemId]);
		}

		$this->itemService->updateField($itemId, 'begin_trigger', @serialize($requestData));
	}

	/**
	 * All media of the playlist except the item itself.
	 *
	 * @return list<array{item_id:int, item_name:string}>
	 * @throws Exception
	 */
	private function findTouchableMedia(int $playlistId, int $itemId): array
	{
		$touchable = [];
		foreach ($this->itemService->findMediaInPlaylist($playlistId) as $mediaItem)
		{
			if ($mediaItem['item_id'] !== $itemId)
				$touchable[] = $mediaItem;
		}

		return $touchable;
	}

}