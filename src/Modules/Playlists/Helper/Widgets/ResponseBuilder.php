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

namespace App\Modules\Playlists\Helper\Widgets;

use App\Framework\Controller\BaseResponseBuilder;
use Psr\Http\Message\ResponseInterface;

/**
 * Builds the JSON responses for the playlist widget endpoints.
 */
class ResponseBuilder extends BaseResponseBuilder
{
	public function invalidItemId(ResponseInterface $response): ResponseInterface
	{
		return $this->jsonResponseHandler->jsonError($response, 'Item ID not valid.');
	}

	public function widgetLoadFailed(ResponseInterface $response): ResponseInterface
	{
		return $this->jsonResponseHandler->jsonError($response, 'Widget load failed.');
	}

	public function saveFailed(ResponseInterface $response, string $errorText): ResponseInterface
	{
		return $this->jsonResponseHandler->jsonError($response, $errorText);
	}
}
