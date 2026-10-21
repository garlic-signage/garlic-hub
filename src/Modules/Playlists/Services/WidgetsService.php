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
use App\Framework\Services\AbstractBaseService;
use App\Modules\Playlists\Helper\Widgets\ContentDataPreparer;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;

class WidgetsService extends AbstractBaseService
{
	private readonly ItemsService $itemService;
	private readonly ContentDataPreparer $contentDataPreparer;

	public function __construct(ItemsService $itemService, ContentDataPreparer $contentDataPreparer, LoggerInterface $logger)
	{
		$this->itemService         = $itemService;
		$this->contentDataPreparer = $contentDataPreparer;
		parent::__construct($logger);
	}

	/**
	 * @return array{item_id:int, values:array<mixed>, preferences:mixed, item_name:mixed}
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws NotFoundException
	 * @throws UserException
	 */
	public function fetchWidgetByItemId(int $itemId): array
	{
		$item            = $this->fetchItem($itemId);
		$preferencesData = $this->contentDataPreparer->determinePreferences($item['config_data']);

		$values = [];
		if (!is_null($item['content_data']))
		{
			$ar = unserialize($item['content_data']);
			if (is_array($ar))
				$values = $ar;
		}

		return [
			'item_id' => $itemId,
			'values' => $values,
			'preferences' => $preferencesData,
			'item_name' => $item['item_name']
		];
	}

	/**
	 * @param array<string,mixed> $requestData
	 * @throws AccessDeniedException
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws NotFoundException
	 * @throws UserException
	 */
	public function saveWidget(int $itemId, array $requestData): void
	{
		$item        = $this->fetchItem($itemId);
		$requestData = $this->prepareContentData($item['config_data'], $requestData);
		$this->itemService->updateField($itemId, 'content_data', $requestData);
	}

	/**
	 * @param array<string,mixed> $requestData
	 * @throws ModuleException
	 * @throws FrameworkException
	 */
	public function prepareContentData(string $configData, array $requestData, bool $init = false): string
	{
		$contentData = $this->contentDataPreparer->prepareContentData($configData, $requestData, $init);
		return serialize($contentData);
	}

	/**
	 * @return array<string,mixed>
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	private function fetchItem(int $itemId): array
	{
		$item = $this->itemService->fetchItemById($itemId);
		if ($item['mimetype'] !== 'application/widget')
			throw new TranslatableModuleException('playlists', 'item_not_widget', ['item_id' => $itemId]);

		return $item;
	}

}