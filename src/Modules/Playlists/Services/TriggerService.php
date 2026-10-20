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

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Services\AbstractBaseService;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;
use Throwable;

class TriggerService extends AbstractBaseService
{
	/**
	 * @var array<string,mixed>|array<empty,empty>
	 */
	private array $itemData = [];
	/**
	 * @var list<array{item_id:int, item_name:string}>
	 */
	private array $touchableMedia = [];

	public function __construct(private readonly ItemsService $itemService, LoggerInterface $logger)
	{
		parent::__construct($logger);
	}

	/**
	 * @return array<string,mixed>|array<empty,empty>
	 */
	public function getItemData(): array
	{
		return $this->itemData;
	}

	/**
	 * @return array<string,mixed>|array<empty,empty>
	 */
	public function getTouchableMedia(): array
	{
		return $this->touchableMedia;
	}

	public function fetchBeginTriggerByItemId(int $itemId): static
	{
		try
		{
			$item = $this->fetchAccessibleItem($itemId);

			$trigger = [];
			if ($item['begin_trigger'] !== '')
			{
				$tmp = @unserialize($item['begin_trigger']);
				if (is_array($tmp))
					$trigger = $tmp;
			}
			$item['begin_trigger'] = $trigger;

			$this->prepareTouchableMedia($item['playlist_id'], $itemId);

			$this->itemData = $item;
		}
		catch (Throwable $e)
		{
			$this->logger->error('Error begin trigger fetch: ' . $e->getMessage());
		}

		return $this;
	}

	/**
	 * @param array<string,mixed> $requestData
	 */
	public function saveBeginTrigger(int $itemId, array $requestData): bool
	{
		try
		{
			$item = $this->fetchAccessibleItem($itemId);
			if (empty($item))
				throw new ModuleException('items', 'No item found.');

			$affected = $this->itemService->updateField($itemId, 'begin_trigger', @serialize($requestData));
			if ($affected === 0)
				throw new ModuleException('items', 'Could not save begin trigger.');

			return true;
		}
		catch (Throwable $e)
		{
			$this->logger->error('Error save trigger: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * @return array<string,mixed>
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws Exception
	 */
	public function fetchAccessibleItem(int $itemId): array
	{
		$this->itemService->setUID($this->UID);
		return $this->itemService->fetchItemById($itemId); // check rights on playlists, too
	}

	/**
	 * @throws Exception
	 */
	public function prepareTouchableMedia(int $playlistId, int $itemId): void
	{
		$media = $this->itemService->findMediaInPlaylist($playlistId);
		if ($media === [])
			return;

		$tmp = [];
		foreach ($media as $mediaItem)
		{
			if ($mediaItem['item_id'] !== $itemId)
				$tmp[] = $mediaItem;

		}
		$this->touchableMedia = $tmp;
	}

}