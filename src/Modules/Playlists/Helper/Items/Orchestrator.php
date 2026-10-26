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

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Framework\Controller\ReadsInput;
use App\Modules\Playlists\Services\InsertItems\InsertItemFactory;
use App\Modules\Playlists\Services\ItemsService;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;

/**
 * Validates the input for the playlist items endpoints and executes the
 * requested action. Every validate method returns a response on error
 * and null when the input is valid.
 */
class Orchestrator
{
	use ReadsInput;

	private int $playlistId = 0;
	private int $itemId = 0;

	public function __construct(
		private readonly ResponseBuilder $responseBuilder,
		private readonly ItemsService $itemsService,
		private readonly InsertItemFactory $insertItemFactory
	) {}

	public function validateLoadItems(ResponseInterface $response): ?ResponseInterface
	{
		return $this->validatePlaylistId($response);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function loadItems(ResponseInterface $response): ResponseInterface
	{
		$list = $this->itemsService->loadItemsByPlaylistIdForComposer($this->playlistId);

		return $this->responseBuilder->generalSuccess($response, ['data' => $list]);
	}

	public function validateInsert(ResponseInterface $response): ?ResponseInterface
	{
		$answer = $this->validatePlaylistId($response);
		if ($answer !== null)
			return $answer;

		if ($this->scalarText('id') === '')
			return $this->responseBuilder->invalidContentId($response);

		if ($this->text('source') === '')
			return $this->responseBuilder->invalidSource($response);

		if ($this->id('position') === 0)
			return $this->responseBuilder->invalidPosition($response);

		return null;
	}

	/**
	 * @throws UserException
	 */
	public function insert(ResponseInterface $response): ResponseInterface
	{
		$insertItem = $this->insertItemFactory->create($this->text('source'));
		if ($insertItem === null)
			return $this->responseBuilder->insertFailed($response);

		$item = $insertItem->insert($this->playlistId, $this->scalarText('id'), $this->id('position'));

		return $this->responseBuilder->generalSuccess($response, ['data' => $item]);
	}

	public function validateEdit(ResponseInterface $response): ?ResponseInterface
	{
		$answer = $this->validateItemId($response);
		if ($answer !== null)
			return $answer;

		if ($this->text('name') === '')
			return $this->responseBuilder->missingParameterName($response);

		// the duration is a number, the name and every other field a text. 0 is a value
		$hasValue = $this->text('name') === 'item_duration' ? $this->number('value') !== null : $this->text('value') !== '';
		if (!$hasValue)
			return $this->responseBuilder->missingParameterValue($response);

		return null;
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function edit(ResponseInterface $response): ResponseInterface
	{

		$name = $this->text('name');
		$data = '';
		switch ($name)
		{
			case 'item_name':
				$affected = $this->itemsService->updateField($this->itemId, $name, $this->text('value'));
				break;
			case 'item_duration':
				$round = (int) round((float) $this->number('value'));
				$data     = $this->itemsService->updateItemDuration($this->itemId, $round);
				$affected = $data === null ? 0 : 1;
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
	 * @throws UserException
	 */
	public function fetch(ResponseInterface $response): ResponseInterface
	{
		$item = $this->itemsService->fetchItemById($this->itemId);

		return $this->responseBuilder->generalSuccess($response, ['item' => $item]);
	}

	public function validateUpdateItemOrders(ResponseInterface $response): ?ResponseInterface
	{
		$answer = $this->validatePlaylistId($response);
		if ($answer !== null)
			return $answer;

		if (($this->idList('items_positions') ?? []) === [])
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
		$this->itemsService->updateItemOrder($this->playlistId, array_map('strval', $this->idList('items_positions') ?? []));

		return $this->responseBuilder->itemsOrderUpdated($response);
	}

	public function validateDelete(ResponseInterface $response): ?ResponseInterface
	{
		return $this->validatePlaylistId($response)
			?? $this->validateItemId($response);
	}

	/**
	 * @throws Exception
	 * @throws UserException
	 */
	public function delete(ResponseInterface $response): ResponseInterface
	{
		$item = $this->itemsService->delete($this->playlistId, $this->itemId);

		return $this->responseBuilder->generalSuccess($response, ['data' => $item]);
	}

	private function validatePlaylistId(ResponseInterface $response): ?ResponseInterface
	{
		$this->playlistId = $this->id('playlist_id');
		if ($this->playlistId === 0)
			return $this->responseBuilder->invalidPlaylistId($response);

		return null;
	}

	private function validateItemId(ResponseInterface $response): ?ResponseInterface
	{
		$this->itemId = $this->id('item_id');
		if ($this->itemId === 0)
			return $this->responseBuilder->invalidItemId($response);

		return null;
	}
}
