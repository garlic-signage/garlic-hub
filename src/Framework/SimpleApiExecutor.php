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

use App\Framework\Services\AbstractBaseService;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * SimpleApiExecutor is a class responsible for handling API requests and managing their responses.
 * It extends the AbstractBaseService to utilize core functionalities such as logging.
 */
class SimpleApiExecutor extends AbstractBaseService
{
	private string $bodyContents = '';

	public function __construct(protected readonly Client $httpClient, LoggerInterface $logger)
	{
		parent::__construct($logger);
	}

	public function getBodyContents(): string
	{
		return $this->bodyContents;
	}

	/**
	 * @return array<string,mixed>|array<empty,empty>
	 */
	public function getBodyContentsArray(): array
	{
		return json_decode($this->bodyContents, true) ?? [];
	}

	/**
	 * @param array<string,int|string> $options
	 */
	public function executeApiRequest(string $method, string $endpoint, string $token, array $options = []): bool
	{
		try
		{
			$endpoint .= '?access_token='. $token;
			$response = $this->httpClient->request($method, $endpoint, [RequestOptions::JSON => $options]);
			$statusCode = $response->getStatusCode();
			if ($statusCode !== 200)
			{
				$error = "API request failed: $statusCode";
				$this->handleHttpError($error, $endpoint, $response->getBody()->getContents());
				return false;
			}

			$this->bodyContents = $response->getBody()->getContents();
			return true;

		}
		catch (Throwable $e)
		{
			$this->logger->error("API request error: {$e->getMessage()}");
			$this->addErrorMessage($e->getMessage());
			return false;
		}
	}

	/**
	 * @param array<string,int|string> $options
	 */
	public function executeAuth(string $endpoint,  array $options = []): bool
	{
		try
		{
			$response = $this->httpClient->post($endpoint, [RequestOptions::JSON => $options]);

			$statusCode = $response->getStatusCode();
			if ($statusCode !== 200)
			{
				$error = "Auth request failed: $statusCode";
				$this->handleHttpError($error, $endpoint, $response->getBody()->getContents());
				return false;
			}

			$this->bodyContents = $response->getBody()->getContents();
			return true;

		}
		catch (Throwable $e)
		{
			$this->logger->error("API request error: {$e->getMessage()}");
			$this->addErrorMessage($e->getMessage());
			return false;
		}
	}

	private function handleHttpError(string $error, string $endpoint, string $body): void
	{
		$this->addErrorMessage($error);
		$this->logger->error($error, [
			'endpoint' => $endpoint,
			'body' => $body
		]);
	}

}