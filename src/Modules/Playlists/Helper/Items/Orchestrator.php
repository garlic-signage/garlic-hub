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

namespace App\Modules\Playlists\Helper\Items;

use App\Framework\Core\BaseValidator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Modules\Auth\UserSession;
use App\Modules\Playlists\Services\InsertItems\InsertItemFactory;
use App\Modules\Playlists\Services\ItemsService;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\Http\Message\ResponseInterface;

/**
 * Validates the input for the playlist items endpoints and executes the
 * requested action. Every validate method returns a response on error
 * and null when the input is valid.
 */
class Orchestrator
{
	/** @var array<string,mixed> */
	private array $input = [];
	private int $playlistId = 0;
	private int $itemId = 0;

	public function __construct(
		private readonly ResponseBuilder $responseBuilder,
		private readonly UserSession $userSession,
		private readonly BaseValidator $validator,
		private readonly ItemsService $itemsService,
		private readonly InsertItemFactory $insertItemFactory
	) {}

	/**
	 * @param array<string,mixed> $input
	 */
	public function setInput(array $input): static
	{
		$this->input = $input;
		return $this;
	}

	public function validateLoadItems(ResponseInterface $response): ?ResponseInterface
	{
		return $this->validatePlaylistId($response);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	public function loadItems(ResponseInterface $response): ResponseInterface
	{
		$this->itemsService->setUID($this->userSession->getUID());
		$list = $this->itemsService->loadItemsByPlaylistIdForComposer($this->playlistId);

		return $this->responseBuilder->generalSuccess($response, ['data' => $list]);
	}

	public function validateInsert(ResponseInterface $response): ?ResponseInterface
	{
		$answer = $this->validateToken($response) ?? $this->validatePlaylistId($response);
		if ($answer !== null)
			return $answer;

		if (empty($this->input['id'])) // more performant as isset and check for 0 or ''
			return $this->responseBuilder->invalidContentId($response);

		if (empty($this->input['source']))
			return $this->responseBuilder->invalidSource($response);

		if (empty($this->input['position']))
			return $this->responseBuilder->invalidPosition($response);

		return null;
	}

	/**
	 * @throws UserException
	 */
	public function insert(ResponseInterface $response): ResponseInterface
	{
		$insertItem = $this->insertItemFactory->create($this->input['source']);
		if ($insertItem === null)
			return $this->responseBuilder->insertFailed($response);

		$insertItem->setUID($this->userSession->getUID());
		$item = $insertItem->insert($this->playlistId, $this->input['id'], (int) $this->input['position']);

		return $this->responseBuilder->generalSuccess($response, ['data' => $item]);
	}

	public function validateEdit(ResponseInterface $response): ?ResponseInterface
	{
		$answer = $this->validateToken($response) ?? $this->validateItemId($response);
		if ($answer !== null)
			return $answer;

		if (empty($this->input['name']))
			return $this->responseBuilder->missingParameterName($response);

		if (empty($this->input['value']))
			return $this->responseBuilder->missingParameterValue($response);

		return null;
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	public function edit(ResponseInterface $response): ResponseInterface
	{
		$this->itemsService->setUID($this->userSession->getUID());

		$name = $this->input['name'];
		$data = '';
		switch ($name)
		{
			case 'item_name':
				$affected = $this->itemsService->updateField($this->itemId, $name, $this->input['value']);
				break;
			case 'item_duration':
				$round = (int) round((float) $this->input['value']);
				$affected = $this->itemsService->updateItemDuration($this->itemId, $round);
				$data = $this->itemsService->getItemDuration();
				break;
			default:
				return $this->responseBuilder->invalidParameterName($response);
		}

		if ($affected === 0)
			return $this->responseBuilder->updateFieldFailed($response, $name);

		return $this->responseBuilder->generalSuccess($response, ['data' => $data]);
	}

	public function validateFetch(ResponseInterface $response): ?ResponseInterface
	{
		return $this->validateItemId($response);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	public function fetch(ResponseInterface $response): ResponseInterface
	{
		$this->itemsService->setUID($this->userSession->getUID());
		$item = $this->itemsService->fetchItemById($this->itemId);
		if (empty($item))
			return $this->responseBuilder->itemNotFound($response);

		return $this->responseBuilder->generalSuccess($response, ['item' => $item]);
	}

	public function validateUpdateItemOrders(ResponseInterface $response): ?ResponseInterface
	{
		$answer = $this->validateToken($response) ?? $this->validatePlaylistId($response);
		if ($answer !== null)
			return $answer;

		if (($this->input['items_positions'] ?? []) === [])
			return $this->responseBuilder->invalidItemsPositions($response);

		return null;
	}

	/**
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	public function updateItemOrders(ResponseInterface $response): ResponseInterface
	{
		$this->itemsService->setUID($this->userSession->getUID());
		$result = $this->itemsService->updateItemOrder($this->playlistId, $this->input['items_positions']);

		return $this->responseBuilder->itemsOrderUpdated($response, $result);
	}

	public function validateDelete(ResponseInterface $response): ?ResponseInterface
	{
		return $this->validateToken($response)
			?? $this->validatePlaylistId($response)
			?? $this->validateItemId($response);
	}

	/**
	 * @throws Exception
	 * @throws UserException
	 */
	public function delete(ResponseInterface $response): ResponseInterface
	{
		$this->itemsService->setUID($this->userSession->getUID());
		$item = $this->itemsService->delete($this->playlistId, $this->itemId);
		if (empty($item))
			return $this->responseBuilder->deleteFailed($response);

		return $this->responseBuilder->generalSuccess($response, ['data' => $item]);
	}

	private function validateToken(ResponseInterface $response): ?ResponseInterface
	{
		if (!$this->validator->validateCsrfToken($this->input['csrf_token'] ?? ''))
			return $this->responseBuilder->csrfTokenMismatch($response);

		return null;
	}

	private function validatePlaylistId(ResponseInterface $response): ?ResponseInterface
	{
		$this->playlistId = (int) ($this->input['playlist_id'] ?? 0);
		if ($this->playlistId === 0)
			return $this->responseBuilder->invalidPlaylistId($response);

		return null;
	}

	private function validateItemId(ResponseInterface $response): ?ResponseInterface
	{
		$this->itemId = (int) ($this->input['item_id'] ?? 0);
		if ($this->itemId === 0)
			return $this->responseBuilder->invalidItemId($response);

		return null;
	}
}
