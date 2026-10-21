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


namespace App\Framework;

use App\Framework\Exceptions\FrameworkException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;

/**
 * Executes simple JSON API requests and returns the decoded response body.
 * Every failure throws, so the caller decides how to react.
 */
readonly class SimpleApiExecutor
{
	public function __construct(private Client $httpClient) {}

	/**
	 * @param array<string,int|string> $options
	 * @return array<string,mixed>
	 * @throws FrameworkException
	 * @throws GuzzleException
	 */
	public function executeApiRequest(string $method, string $endpoint, string $token, array $options = []): array
	{
		$endpoint .= '?access_token='. $token;
		$response  = $this->httpClient->request($method, $endpoint, [RequestOptions::JSON => $options]);

		return $this->decodeResponse($response, 'API request failed');
	}

	/**
	 * @param array<string,int|string> $options
	 * @return array<string,mixed>
	 * @throws FrameworkException
	 * @throws GuzzleException
	 */
	public function executeAuth(string $endpoint,  array $options = []): array
	{
		$response = $this->httpClient->post($endpoint, [RequestOptions::JSON => $options]);

		return $this->decodeResponse($response, 'Auth request failed');
	}

	/**
	 * @return array<string,mixed>
	 * @throws FrameworkException
	 */
	private function decodeResponse(ResponseInterface $response, string $error): array
	{
		$body = $response->getBody()->getContents();
		if ($response->getStatusCode() !== 200)
			throw new FrameworkException($error . ': ' . $response->getStatusCode() . ' ' . $body);

		$decoded = json_decode($body, true);

		return is_array($decoded) ? $decoded : [];
	}
}
