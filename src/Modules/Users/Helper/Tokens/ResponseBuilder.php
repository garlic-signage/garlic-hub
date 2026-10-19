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

namespace App\Modules\Users\Helper\Tokens;

use App\Framework\Controller\BaseResponseBuilder;
use Psr\Http\Message\ResponseInterface;

/**
 * Builds the JSON responses for the user token endpoints.
 */
class ResponseBuilder extends BaseResponseBuilder
{
	public function noRights(ResponseInterface $response): ResponseInterface
	{
		return $this->jsonResponseHandler->jsonError($response, 'No rights to handle tokens.', 403);
	}

	public function missingUID(ResponseInterface $response): ResponseInterface
	{
		return $this->jsonResponseHandler->jsonError($response, 'UID not transmitted.');
	}

	public function missingPurpose(ResponseInterface $response): ResponseInterface
	{
		return $this->jsonResponseHandler->jsonError($response, 'Purpose not transmitted.');
	}

	public function tokenNotEditable(ResponseInterface $response): ResponseInterface
	{
		return $this->jsonResponseHandler->jsonError($response, 'Token not editable.');
	}
}
