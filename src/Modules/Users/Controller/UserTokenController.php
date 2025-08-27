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

namespace App\Modules\Users\Controller;

use App\Framework\Controller\AbstractAsyncController;
use App\Framework\Core\CsrfToken;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use App\Modules\Auth\UserSession;
use App\Modules\Profile\Services\UserTokenService;
use App\Modules\Users\Services\AclValidator;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Handles user-token-related actions such as deletion and refresh.
 */
class UserTokenController extends AbstractAsyncController
{

	public function __construct(
		private readonly UserSession $userSession,
		private readonly UserTokenService $userService,
		private readonly CsrfToken $csrfToken,
		private readonly AclValidator $aclValidator
	) {
	}

	/**
	 * @param ServerRequestInterface $request
	 * @param ResponseInterface $response
	 * @return ResponseInterface
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		return $this->processTokenAction($request, $response, 'deleteToken');
	}

	/**
	 * @param ServerRequestInterface $request
	 * @param ResponseInterface $response
	 * @return ResponseInterface
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	public function refresh(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		return $this->processTokenAction($request, $response, 'refreshToken');
	}

	/**
	 * @param ServerRequestInterface $request
	 * @param ResponseInterface $response
	 * @param string $action
	 * @return ResponseInterface
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws UserException
	 */
	private function processTokenAction(ServerRequestInterface $request, ResponseInterface $response, string $action): ResponseInterface
	{
		/** @var array<string,mixed> $post */
		$post = $request->getParsedBody();

		if (!$this->csrfToken->validateToken($post['csrf_token'] ?? ''))
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Csrf token mismatch.']);

		$token = $post['token'] ?? '';
		if ($token === '')
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Token not transmitted.']);

		$userToken = $this->userService->findByTokenForAction($token);
		if ($userToken === null)
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'Token not exists.']);

		if (!$this->aclValidator->isAdmin($this->userSession->getUID(), $userToken))
			return $this->jsonResponse($response, ['success' => false, 'error_message' => 'No rights to handle tokens.']);

		$errorMessage = 'Token not editable.';
		if ($action === 'refreshToken')
			$result = $this->userService->refreshToken($token, $userToken['purpose']);
		 else
			$result = $this->userService->deleteToken($token);

		if ($result === 0)
			return $this->jsonResponse($response, ['success' => false, 'error_message' => $errorMessage]);

		return $this->jsonResponse($response, ['success' => true]);
	}
}