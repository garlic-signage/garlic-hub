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

use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\SimpleApiExecutor;
use Doctrine\DBAL\Exception;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\TransferException;

/**
 * This service class handles interactions with a REST API for player-related operations.
 *
 * Every method throws when the player cannot be reached or rejects the request.
 */
readonly class PlayerRestAPIService
{
	public function __construct(
		private SimpleApiExecutor $apiExecutor,
		private PlayerTokenService $playerTokenService
	) {}

	/**
	 * @throws Exception
	 * @throws TranslatableModuleException
	 */
	public function authenticate(string $baseUrl, string $username, string $password, int $playerId): void
	{
		if ($this->playerTokenService->hasValidToken($playerId))
			return;

		$body = [
			'grant_type' => 'password',
			'username' => $username,
			'password' => $password
		];

		$response = $this->send($playerId, fn() => $this->apiExecutor->executeAuth($baseUrl . '/oauth2/token', $body));

		if (!isset($response['access_token'], $response['expires_in'], $response['token_type']))
			throw $this->requestFailed($playerId, 'Invalid authentication response');

		$this->playerTokenService->storeToken($playerId, $response['access_token'], $response['expires_in'], $response['token_type']);
	}

	/**
	 * @throws ModuleException
	 * @throws TranslatableModuleException
	 */
	public function uploadFile(string $baseUrl, int $playerId, string $filePath, string $fileName): void
	{
		$size = filesize($filePath);
		if ($size === false)
			throw new ModuleException('player', 'Filepath '.$filePath.' does not exist.');

		$body = [
			'downloadPath' => $filePath,
			'data' => $this->encodeMultipart($filePath),
			'fileSize' => $size
		];

		$response = $this->request($playerId, 'POST', $baseUrl.'/files/new', $body);
		if (($response['completed'] ?? false) === false)
			throw $this->requestFailed($playerId, 'Upload file failed');
	}

	/**
	 * Starts a playback of an $uri for one time.
	 *
	 * @throws TranslatableModuleException
	 */
	public function startPlaybackOnce(string $baseUrl, int $playerId, string $uri): void
	{
		$response = $this->request($playerId, 'POST', $baseUrl . '/app/exec', ['uri' => $uri]);
		if (($response['uri'] ?? '') !== $uri)
			throw $this->requestFailed($playerId, 'Start playback failed');
	}

	/**
	 * Set a default content-url without interrupting current play
	 *
	 * @throws TranslatableModuleException
	 */
	public function setDefaultContentUrl(string $baseUrl, int $playerId, string $uri): void
	{
		$response = $this->request($playerId, 'POST', $baseUrl . '/app/start', ['uri' => $uri]);
		if (($response['uri'] ?? '') !== $uri)
			throw $this->requestFailed($playerId, 'Set default content uri failed');
	}

	/**
	 * Plays the default content-url immediately
	 *
	 * @throws TranslatableModuleException
	 */
	public function switchToDefaultContentUrl(string $baseUrl, int $playerId): void
	{
		$response = $this->request($playerId, 'POST', $baseUrl . '/app/switch', ['mode' => 'start']);
		if (!isset($response['uri']) || $response['uri'] === '')
			throw $this->requestFailed($playerId, 'Switch play default content uri failed');
	}

	/**
	 * @param array<string,int|string> $body
	 * @return array<string,mixed>
	 * @throws TranslatableModuleException
	 */
	private function request(int $playerId, string $method, string $endpoint, array $body): array
	{
		$token = $this->fetchToken($playerId);

		return $this->send($playerId, fn() => $this->apiExecutor->executeApiRequest($method, $endpoint, $token, $body));
	}

	/**
	 * An unreachable or refusing player is an expected situation, not a server error.
	 * The original exception stays attached as previous.
	 *
	 * @param callable(): array<string,mixed> $call
	 * @return array<string,mixed>
	 * @throws TranslatableModuleException
	 */
	private function send(int $playerId, callable $call): array
	{
		try
		{
			return $call();
		}
		catch (ConnectException $e)
		{
			throw new TranslatableModuleException('player', 'player_not_reachable', ['player_id' => $playerId], $e);
		}
		catch (TransferException|FrameworkException $e)
		{
			throw $this->requestFailed($playerId, 'Request rejected', $e);
		}
	}

	/**
	 * @throws TranslatableModuleException
	 */
	private function fetchToken(int $playerId): string
	{
		$tokenData = $this->playerTokenService->getToken($playerId);
		if ($tokenData === [])
			throw new TranslatableModuleException('player', 'player_not_authenticated', ['player_id' => $playerId]);

		/** @var array{access_token:string, UID:int, token_type:string, expired_at:string} $tokenData */ // mimimi of phpstan
		return $tokenData['access_token'];
	}

	/**
	 * The reason is not part of the translated text, it lands only in the log.
	 */
	private function requestFailed(int $playerId, string $reason, ?\Exception $previous = null): TranslatableModuleException
	{
		return new TranslatableModuleException('player', 'player_request_failed', ['player_id' => $playerId, 'reason' => $reason], $previous);
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
}
