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

namespace App\Modules\Playlists\Controller;

use App\Framework\Controller\AbstractAsyncController;
use App\Framework\Core\CsrfToken;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Playlists\Services\InsertItems\InsertItemFactory;
use App\Modules\Playlists\Services\ItemsService;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller responsible for handling operations on items.
 */
class ItemsController extends AbstractAsyncController
{
	private readonly ItemsService $itemsService;
	private readonly InsertItemFactory $insertItemFactory;
	private readonly CsrfToken $csrfToken;

	public function __construct(ItemsService $itemsService, InsertItemFactory $insertItemFactory, CsrfToken $csrfToken)
	{
		$this->itemsService = $itemsService;
		$this->insertItemFactory = $insertItemFactory;
		$this->csrfToken = $csrfToken;
	}

	/**
	 * @param ServerRequestInterface $request
	 * @param ResponseInterface $response
	 * @param array<string,mixed> $args
	 * @return ResponseInterface
	 * @throws CoreException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws FrameworkException
	 */
	public function loadItems(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$playlistId = (int) ($args['playlist_id'] ?? 0);
		if ($playlistId === 0)
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Playlist ID not valid.']);

		$this->setServiceUID($request);
		$list = $this->itemsService->loadItemsByPlaylistIdForComposer($playlistId);

		return $this->jsonResponse($response, ['success' => true, 'data' => $list]);
	}

	public function insert(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		/** @var array<string,mixed> $requestData */
		$requestData = $request->getParsedBody();

		if (!$this->csrfToken->validateToken($requestData['csrf_token'] ?? ''))
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'CSRF token mismatch.']);

		if (empty($requestData['playlist_id']))
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Playlist ID not valid.']);

		if (empty($requestData['id'])) // more performant as isset and check for 0 or ''
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Content ID not valid.']);

		if (empty($requestData['source']))
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Source not valid.']);

		if (empty($requestData['position']))
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Position not valid.']);


		$insertItem = $this->insertItemFactory->create($requestData['source']);
		if($insertItem === null)
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Error inserting item.']);

		$UID = $this->setServiceUID($request);

		$insertItem->setUID($UID);
		$item = $insertItem->insert((int) $requestData['playlist_id'], $requestData['id'], (int) $requestData['position']);

		return $this->jsonResponse($response, ['success' => true, 'data' => $item]);
	}

	/**
	 * @param ServerRequestInterface $request
	 * @param ResponseInterface $response
	 * @return ResponseInterface
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function edit(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		/** @var array<string,mixed> $requestData */
		$requestData = $request->getParsedBody();

		if (!$this->csrfToken->validateToken($requestData['csrf_token'] ?? ''))
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'CSRF token mismatch.']);

		if (empty($requestData['item_id'])) // more performant as isset and check for 0 or ''
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Item ID not valid.']);

		if (empty($requestData['name']))
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'No parameter name.']);

		if (empty($requestData['value']))
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'No parameter value.']);

		$this->setServiceUID($request);

		$data = '';
		switch ($requestData['name'])
		{
			case 'item_name':
				$affected = $this->itemsService->updateField($requestData['item_id'], $requestData['name'], $requestData['value']);
				break;
			case 'item_duration':
				$round = (int) round((float) $requestData['value']);
				$affected = $this->itemsService->updateItemDuration((int) $requestData['item_id'], $round);
				$data = $this->itemsService->getItemDuration();
				break;
			default:
				return $this->jsonResponse($response, ['success' => false, 'error_message' => 'No valid parametername.']);
		}

		if ($affected === 0)
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Error updating item field: '.$requestData['name']. '.']);

		return $this->jsonResponse($response, ['success' => true, 'data' => $data]);
	}

	/**
	 * @param ServerRequestInterface $request
	 * @param ResponseInterface $response
	 * @param array<string,mixed> $args
	 * @return ResponseInterface
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function fetch(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$itemId = (int) ($args['item_id'] ?? 0);
		if ($itemId === 0)
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Item ID not valid.']);

		$this->setServiceUID($request);
		$item = $this->itemsService->fetchItemById($itemId);
		if (empty($item))
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Item not found.']);

		return $this->jsonResponse($response, ['success' => true, 'item' => $item]);
	}


	/**
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function updateItemOrders(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		/** @var array<string,mixed> $requestData */
		$requestData = $request->getParsedBody();
		if (!$this->csrfToken->validateToken($requestData['csrf_token'] ?? ''))
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'CSRF token mismatch.']);

		$playlistId = (int) ($requestData['playlist_id'] ?? 0);
		if ($playlistId === 0)
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Playlist ID not valid.']);

		$itemsPositions = $requestData['items_positions'] ?? [];
		if ($itemsPositions === [])
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Items Position array is not valid.']);

		$this->setServiceUID($request);
		$result = $this->itemsService->updateItemOrder($playlistId, $itemsPositions);

		return $this->jsonResponse($response, ['success' => $result]);
	}

	/**
	 * @throws Exception
	 */
	public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		/** @var array<string,mixed> $requestData */
		$requestData = $request->getParsedBody();

		if (!$this->csrfToken->validateToken($requestData['csrf_token'] ?? ''))
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'CSRF token mismatch.']);

		$playlistId = (int) ($requestData['playlist_id'] ?? 0);
		if ($playlistId === 0)
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Playlist ID not valid.']);

		$itemId = (int) ($requestData['item_id'] ?? 0);
		if ($itemId === 0)
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Item ID not valid.']);

		$this->setServiceUID($request);

		$item = $this->itemsService->delete($playlistId, $itemId);

		if(!empty($item))
			return $this->jsonResponse($response, ['success' => true, 'data' => $item]);
		else
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Error deleting item.']);
	}


	private function setServiceUID(ServerRequestInterface $request): int
	{
		$session = $request->getAttribute('session');
		$UID = (int) $session->get('user')['UID'];
		$this->itemsService->setUID($UID);

		return $UID;
	}

}