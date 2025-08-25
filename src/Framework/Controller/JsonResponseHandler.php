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

namespace App\Framework\Controller;

use Psr\Http\Message\ResponseInterface;

/**
 * Handles JSON-based HTTP responses.
 */
class JsonResponseHandler
{
    /**
	 * @param array<string,mixed>|list<array<string,mixed>>|array<empty,empty> $data
	 */
    public function jsonResponse(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $json = json_encode($data);
        if ($json !== false)
            $response->getBody()->write($json);

        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

	/**
	 * @param array<string,mixed>|list<array<string,mixed>>|array<empty,empty> $data
	 */
    public function jsonSuccess(ResponseInterface $response, array $data = []): ResponseInterface
    {
        return $this->jsonResponse($response, ['success' => true] + $data);
    }

    public function jsonError(ResponseInterface $response, string $message, int $status = 400): ResponseInterface
    {
        return $this->jsonResponse($response, ['success' => false, 'error_message' => $message], $status);
    }
}
