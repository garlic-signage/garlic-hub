<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace App\Modules\Auth;

use App\Framework\Core\Session;
use Doctrine\DBAL\Exception;
use InvalidArgumentException;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Handles OAuth2 authentication and authorization flows.
 */
class OAuth2Controller
{
	private AuthService $authService;
	private AuthorizationServer $authServer;

	/**
	 * @param AuthService $auth2Service
	 * @param AuthorizationServer $authServer
	 */
	public function __construct(AuthService $auth2Service, AuthorizationServer $authServer)
	{
		$this->authService = $auth2Service;
		$this->authServer = $authServer;
	}

	/**
	 * @param ServerRequestInterface $request
	 * @param ResponseInterface      $response
	 *
	 * @return ResponseInterface
	 * @throws Exception
	 */
	public function authorize(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		try
		{
			$authRequest = $this->authServer->validateAuthorizationRequest($request);
			/**  @var Session $session */
			// check if a user is logged in
			$session  = $request->getAttribute('session');
			if (!$session->exists('user'))
			{
				$params = $request->getQueryParams();
				$sanitizedParams = [
					'response_type' => $this->validateResponseType($params['response_type'] ?? null),
					'client_id'     => $this->validateClientId($params['client_id'] ?? null),
					'redirect_uri'  => $this->validateRedirectUri($params['redirect_uri'] ?? null),
					'state'         => $this->validateState($params['state'] ?? null),
				];
				$session->set('oauth_redirect_params', $sanitizedParams);
				// redirect to ath site
				return $response->withHeader('Location', '/login')->withStatus(302);
			}

			/** @var array{UID: int} $user */
			$user = $session->get('user');
			$authRequest->setUser($this->authService->getCurrentUser($user['UID'])); // an instance of UserEntityInterface

			// Once the user has approved or denied the client update the status
			// (true = approved, false = denied)
			// Todo later: Set a confirmation page when handle resource scopes etc.
			$authRequest->setAuthorizationApproved(true);

			return $this->authServer->completeAuthorizationRequest($authRequest, $response);

		}
		catch (OAuthServerException $e)
		{
			return $e->generateHttpResponse($response);
		}
		catch (\Exception $e)
		{
			$json = json_encode(['error' => $e->getMessage()]);
			if ($json !== false)
				$response->getBody()->write($json);

			return $response->withStatus(500);
		}
	}

	public function token(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		try
		{
			// return a JSON token {"access_token": "xy...z", "token_type": "Bearer", "expires_in": 3600, "scope": "read-write"}
			return $this->authServer->respondToAccessTokenRequest($request, $response);
		}
		catch (OAuthServerException $e)
		{
			return $e->generateHttpResponse($response);
		}
		catch (Throwable $e)
		{
			$json = json_encode(['error' => $e->getMessage()]);
			if ($json !== false)
				$response->getBody()->write($json);

			return $response->withStatus(500);
		}
	}

	private function validateResponseType(?string $responseType): string
	{
		$allowed = ['code', 'token'];
		if (!in_array($responseType, $allowed, true))
			throw new InvalidArgumentException('Invalid response_type');

		return $responseType;
	}

	private function validateClientId(?string $clientId): string
	{
		if (empty($clientId) || strlen($clientId) > 255)
			throw new InvalidArgumentException('Invalid client_id');

		return $clientId;
	}

	private function validateRedirectUri(?string $redirectUri): string
	{
		if ($redirectUri === null)
			throw new InvalidArgumentException('Invalid redirect_uri');

		if (!filter_var($redirectUri, FILTER_VALIDATE_URL))
			throw new InvalidArgumentException('Invalid redirect_uri');

		return $redirectUri;
	}

	private function validateState(?string $state): string
	{
		if (empty($state) || strlen($state) > 255)
			throw new InvalidArgumentException('Invalid state');

		return $state;
	}

}