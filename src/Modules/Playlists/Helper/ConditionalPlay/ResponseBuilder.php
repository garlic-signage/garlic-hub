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

namespace App\Modules\Playlists\Helper\ConditionalPlay;

use App\Framework\Controller\BaseResponseBuilder;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Class ResponseBuilder
 *
 * Extends the BaseResponseBuilder class and provides methods for handling JSON error responses
 * for various scenarios like invalid item ID, playlist not found, or item not found.
 *
 * Each method processes the given response by returning an error response with a
 * translated error message, specific to the respective scenario.
 *
 * @throws CoreException If there is a core system processing error.
 * @throws PhpfastcacheSimpleCacheException If there is an issue with caching during response processing.
 * @throws InvalidArgumentException If invalid arguments are passed to translation or caching methods.
 * @throws FrameworkException If there is a general framework-related issue during execution.
 */
class ResponseBuilder extends BaseResponseBuilder
{
	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function invalidItemId(ResponseInterface $response): ResponseInterface
    {
		return $this->jsonResponseHandler->jsonError(
			$response, $this->translator->translate('invalid_item_id', 'playlists')
		);
    }

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function playlistNotFound(ResponseInterface $response): ResponseInterface
    {
		return $this->jsonResponseHandler->jsonError(
			$response, $this->translator->translate('playlist_not_found', 'playlists')
		);
    }

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function itemNotFound(ResponseInterface $response): ResponseInterface
	{
		return $this->jsonResponseHandler->jsonError(
			$response, $this->translator->translate('item_not_found', 'playlists')
		);
	}

}
