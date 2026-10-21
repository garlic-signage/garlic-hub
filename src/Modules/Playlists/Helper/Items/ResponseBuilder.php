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

use App\Framework\Controller\BaseResponseBuilder;
use Psr\Http\Message\ResponseInterface;

/**
 * Builds the JSON responses for the playlist items endpoints.
 */
class ResponseBuilder extends BaseResponseBuilder
{
	public function invalidPlaylistId(ResponseInterface $response): ResponseInterface
	{
		return $this->error($response, 'Playlist ID not valid.');
	}

	public function invalidItemId(ResponseInterface $response): ResponseInterface
	{
		return $this->error($response, 'Item ID not valid.');
	}

	public function invalidContentId(ResponseInterface $response): ResponseInterface
	{
		return $this->error($response, 'Content ID not valid.');
	}

	public function invalidSource(ResponseInterface $response): ResponseInterface
	{
		return $this->error($response, 'Source not valid.');
	}

	public function invalidPosition(ResponseInterface $response): ResponseInterface
	{
		return $this->error($response, 'Position not valid.');
	}

	public function invalidItemsPositions(ResponseInterface $response): ResponseInterface
	{
		return $this->error($response, 'Items Position array is not valid.');
	}

	public function missingParameterName(ResponseInterface $response): ResponseInterface
	{
		return $this->error($response, 'No parameter name.');
	}

	public function missingParameterValue(ResponseInterface $response): ResponseInterface
	{
		return $this->error($response, 'No parameter value.');
	}

	public function invalidParameterName(ResponseInterface $response): ResponseInterface
	{
		return $this->error($response, 'No valid parametername.');
	}

	public function insertFailed(ResponseInterface $response): ResponseInterface
	{
		return $this->error($response, 'Error inserting item.');
	}

	public function updateFieldFailed(ResponseInterface $response, string $fieldName): ResponseInterface
	{
		return $this->error($response, 'Error updating item field: '.$fieldName.'.');
	}

	/**
	 * The frontend expects only the success flag here. Errors are thrown and answered by the JsonErrorHandler.
	 */
	public function itemsOrderUpdated(ResponseInterface $response): ResponseInterface
	{
		return $this->jsonResponseHandler->jsonResponse($response, ['success' => true]);
	}

	private function error(ResponseInterface $response, string $message): ResponseInterface
	{
		return $this->jsonResponseHandler->jsonError($response, $message);
	}
}
