<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace App\Framework\Llm;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\RequestOptions;
use OpenAI;
use Psr\Http\Client\ClientInterface;

/**
 * The adapter for the API of a connection. The address comes from the user: no redirects, only http(s)
 * and a time limit, the answer of a wrong address is never shown (LlmException).
 */
class LlmClientFactory
{
	private const int CONNECT_TIMEOUT = 5;
	private const int TIMEOUT         = 60;
	/** the selection of a model waits shorter than an answer of the model */
	public const int MODELS_TIMEOUT   = 5;

	private LlmAddress $address;

	/**
	 * @param (\Closure(string):list<string>)|null $resolve the addresses of a host name, for tests, by default the DNS
	 */
	public function __construct(private readonly ?ClientInterface $httpClient = null, ?\Closure $resolve = null)
	{
		$this->address = new LlmAddress($resolve);
	}

	/**
	 * @param int $timeout seconds for the whole request
	 * @throws LlmException
	 */
	public function create(LlmConnection $connection, int $timeout = self::TIMEOUT): LlmClient
	{
		if (!self::isValidUrl($connection->baseUrl))
			throw new LlmException('llm_invalid_url');

		if ($this->address->isBlocked($connection->baseUrl))
			throw new LlmException('llm_blocked_address');

		return match ($connection->apiType)
		{
			LlmConnection::API_OPENAI        => $this->createOpenAi($connection, $timeout),
			LlmConnection::API_AUTOMATIC1111 => new Automatic1111Client($this->createHttpClient($timeout), $connection->baseUrl, $connection->model, $connection->apiKey),
			default                          => throw new LlmException('llm_unknown_api')
		};
	}

	public static function isValidUrl(string $url): bool
	{
		return LlmAddress::isValidUrl($url);
	}

	private function createOpenAi(LlmConnection $connection, int $timeout): LlmClient
	{
		$factory = OpenAI::factory()
			->withBaseUri(rtrim($connection->baseUrl, '/'))
			->withHttpClient($this->createHttpClient($timeout));
		if ($connection->apiKey !== '')
			$factory = $factory->withApiKey($connection->apiKey);

		return new OpenAiCompatibleClient($factory->make(), $connection->model);
	}

	private function createHttpClient(int $timeout): ClientInterface
	{
		return $this->httpClient ?? new GuzzleClient([
			RequestOptions::CONNECT_TIMEOUT => min(self::CONNECT_TIMEOUT, $timeout),
			RequestOptions::TIMEOUT         => $timeout,
			RequestOptions::ALLOW_REDIRECTS => false
		]);
	}
}
