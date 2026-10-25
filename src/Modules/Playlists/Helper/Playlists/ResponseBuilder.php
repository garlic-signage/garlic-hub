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

namespace App\Modules\Playlists\Helper\Playlists;

use App\Framework\Controller\BaseResponseBuilder;
use Psr\Http\Message\ResponseInterface;

/**
 * Builds the JSON responses for the playlist endpoints.
 */
class ResponseBuilder extends BaseResponseBuilder
{
	public function invalidPlaylistId(ResponseInterface $response): ResponseInterface
	{
		return $this->jsonResponseHandler->jsonError($response, 'Playlist ID not valid.');
	}

	public function playlistNotFound(ResponseInterface $response): ResponseInterface
	{
		return $this->jsonResponseHandler->jsonError($response, 'Playlist not found.');
	}

	public function missingPickingValue(ResponseInterface $response): ResponseInterface
	{
		return $this->jsonResponseHandler->jsonError($response, 'No picking value found.');
	}

	public function zoneSaveFailed(ResponseInterface $response): ResponseInterface
	{
		return $this->jsonResponseHandler->jsonError($response, 'Multizone could not be saved');
	}

	public function invalidZoneData(ResponseInterface $response): ResponseInterface
	{
		return $this->jsonResponseHandler->jsonError($response, 'Invalid zone data.');
	}

	/**
	 * The find endpoints are used by autocomplete and the zone editor, which
	 * expect the plain data without the success envelope.
	 *
	 * @param array<string,mixed>|list<array<string,mixed>>|array<empty,empty> $data
	 */
	public function plainData(ResponseInterface $response, array $data): ResponseInterface
	{
		return $this->jsonResponseHandler->jsonResponse($response, $data);
	}
}
