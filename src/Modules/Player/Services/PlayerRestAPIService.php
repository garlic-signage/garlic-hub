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

namespace App\Modules\Player\Services;

use App\Framework\Services\AbstractBaseService;
use App\Framework\SimpleApiExecutor;
use Psr\Log\LoggerInterface;

/**
 * This service class handles interactions with a REST API for player-related operations.
 */
class PlayerRestAPIService extends AbstractBaseService
{
    private readonly SimpleApiExecutor $apiExecutor;
    private readonly PlayerTokenService $playerTokenService;

	public function __construct(SimpleApiExecutor $apiExecutor, PlayerTokenService $playerTokenService, LoggerInterface $logger)
	{
        $this->apiExecutor = $apiExecutor;
        $this->playerTokenService = $playerTokenService;
        parent::__construct($logger);
    }

    public function authenticate(string $baseUrl, string $username, string $password, int $playerId): bool
    {
		if ($this->playerTokenService->hasValidToken($playerId))
			return true;

		$endpoint = $baseUrl . '/oauth2/token';
		$body = [
			'grant_type' => 'password',
			'username' => $username,
			'password' => $password
		];

		if ($this->apiExecutor->executeAuth($endpoint, $body) === false)
		{
			$this->errorMessages = $this->apiExecutor->getErrorMessages();
			return false;
		}

		$response = $this->apiExecutor->getBodyContentsArray();

		if (!isset($response['access_token'], $response['expires_in'], $response['token_type']))
			return $this->handleErrors('Invalid authentication response', $response);

		return $this->playerTokenService->storeToken($playerId, $response['access_token'], $response['expires_in'], $response['token_type']);
    }

    public function uploadFile(string $baseUrl, int $playerId, string $filePath, string $fileName): bool
    {
		$token = $this->fetchToken($playerId);
		if ($token === '')
			return false;

		$size = filesize($filePath);
		if ($size === false)
			return $this->handleErrors('Filepath '.$filePath.' does not exist.', []);

		$body = [
			'downloadPath' => $filePath,
			'data' => $this->encodeMultipart($filePath),
			'fileSize' => $size
		];

		$isExecuted = $this->apiExecutor->executeApiRequest('POST', $baseUrl.'/files/new', $token, $body);
		if (!$isExecuted)
		{
			$this->errorMessages = $this->apiExecutor->getErrorMessages();
			return false;
		}

		$response = $this->apiExecutor->getBodyContentsArray();
		if ($response['completed'] === false)
			return $this->handleErrors('Upload file failed', $response);

		return true;
	}

	/**
	 * Starts a playback of an $uri for one time.
	 */
    public function startPlaybackOnce(string $baseUrl, int $playerId, string $uri): bool
    {
		$token = $this->fetchToken($playerId);
		if ($token === '')
			return false;

		$body = ['uri'=> $uri];
		$isExecuted = $this->apiExecutor->executeApiRequest('POST', $baseUrl . '/app/exec', $token, $body);
		if (!$isExecuted)
		{
			$this->errorMessages = $this->apiExecutor->getErrorMessages();
			return false;
		}

		$response = $this->apiExecutor->getBodyContentsArray();
		if ($response['uri'] !== $uri)
			return $this->handleErrors('Start playback failed', $response);

		return true;
    }

	/**
	 * Set a default content-url without interrupting current play
	 */
	public function setDefaultContentUrl(string $baseUrl, int $playerId, string $uri): bool
	{
		$token = $this->fetchToken($playerId);
		if ($token === '')
			return false;

		$body = ['uri'=> $uri];
		$isExecuted = $this->apiExecutor->executeApiRequest('POST', $baseUrl . '/app/start', $token, $body);
		if (!$isExecuted)
		{
			$this->errorMessages = $this->apiExecutor->getErrorMessages();
			return false;
		}

		$response = $this->apiExecutor->getBodyContentsArray();
		if ($response['uri'] !== $uri)
			return $this->handleErrors('Set default content uri failed', $response);

		return true;
	}

	/**
	 * Plays the default content-url immediately
	 */
	public function switchToDefaultContentUrl(string $baseUrl, int $playerId): bool
	{
		$token = $this->fetchToken($playerId);
		if ($token === '')
			return false;

		$body = ['mode'=> 'start'];

		$isExecuted = $this->apiExecutor->executeApiRequest('POST', $baseUrl . '/app/switch', $token, $body);
		if (!$isExecuted)
		{
			$this->errorMessages = $this->apiExecutor->getErrorMessages();
			return false;
		}

		$response = $this->apiExecutor->getBodyContentsArray();
		if (!isset($response['uri']) || $response['uri'] === '')
			return $this->handleErrors('Switch play default content uri failed', $response);

		return true;
	}

	private function fetchToken(int $playerId): string
	{
		$tokenData = $this->playerTokenService->getToken($playerId);
		if ($tokenData === [])
		{
			$this->logger->error('No valid token found for player '. $playerId);
			$this->addErrorMessage('No valid token found for player '. $playerId);
			return '';
		}

		/** @var array{access_token:string, UID:int, token_type:string, expired_at:string} $tokenData */ // mimimi of phpstan
		return $tokenData['access_token'];
	}

	private function encodeMultipart(string $filePath): string
	{
		$content = file_get_contents($filePath);

		$boundary = uniqid();
		$delimiter = '-------------' . $boundary;

		$data = "--$delimiter\r\n";
		$data .= "Content-Disposition: form-data; name=\"file\"; filename=\"$filePath\"\r\n";
		$data .= "Content-Type: text/plain\r\n\r\n";
		$data .= $content . "\r\n";
		$data .= "--$delimiter--\r\n";

		return $data;
	}

	/**
	 * @param array<string,mixed>|array<empty,empty> $response
	 */
	private function handleErrors(string $message, array $response): bool
	{
		$this->logger->error($message, ['response' => $response]);
		$this->addErrorMessage($message.': '. $this->apiExecutor->getBodyContents());
		return false;

	}
}
