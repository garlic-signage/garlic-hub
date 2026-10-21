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

class ConditionalPlayService extends AbstractBaseService
{
	public function __construct(private readonly ItemsService $itemService, LoggerInterface $logger)
	{
		parent::__construct($logger);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function fetchConditionalByItemId(int $itemId): array
	{
		try
		{
			$item = $this->fetchAccessibleItem($itemId);

			$conditional = [];
			if ($item['conditional'] !== '')
			{
				$tmp = @unserialize($item['conditional']);
				if (is_array($tmp))
					$conditional = $tmp;
			}
			$item['conditional'] = $conditional;

			return $item;
		}
		catch (Throwable $e)
		{
			$this->logger->error('Error conditional fetch: ' . $e->getMessage());
			return [];
		}
	}

	/**
	 * @param array<string,mixed> $requestData
	 */
	public function saveConditionalPlay(int $itemId, array $requestData): bool
	{
		try
		{
			$item = $this->fetchAccessibleItem($itemId);
			if (empty($item))
				throw new ModuleException('items', 'No item found.');

			$affected = $this->itemService->updateField($itemId, 'conditional', @serialize($requestData));
			if ($affected === 0)
				throw new ModuleException('items', 'Could not save for item.');

			return true;
		}
		catch (Throwable $e)
		{
			$this->logger->error('Error save conditional play: ' . $e->getMessage());
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
		return $this->itemService->fetchItemById($itemId); // check rights on playlists, too
	}


}