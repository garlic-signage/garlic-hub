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
use App\Framework\Exceptions\UserException;
use App\Framework\Services\AbstractBaseService;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;

class ConditionalPlayService extends AbstractBaseService
{
	public function __construct(private readonly ItemsService $itemService, LoggerInterface $logger)
	{
		parent::__construct($logger);
	}

	/**
	 * @return array<string,mixed>
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws NotFoundException
	 * @throws UserException
	 */
	public function fetchConditionalByItemId(int $itemId): array
	{
		$item = $this->itemService->fetchItemById($itemId); // check rights on playlists, too

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

	/**
	 * Saving unchanged settings changes no rows, which is no error.
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
	public function saveConditionalPlay(int $itemId, array $requestData): void
	{
		$this->itemService->fetchItemById($itemId); // check rights on playlists, too
		$this->itemService->updateField($itemId, 'conditional', @serialize($requestData));
	}

}