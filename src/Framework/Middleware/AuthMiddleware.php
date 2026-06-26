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

namespace App\Framework\Middleware;

use App\Framework\Core\Cookie;
use App\Framework\Core\Session;
use App\Framework\Exceptions\FrameworkException;
use App\Modules\Auth\AuthService;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * The process method in the AuthMiddleware class is responsible for
 * handling authentication for incoming HTTP requests.
 *
 * It implements the MiddlewareInterface and is designed to intercept requests,
 * check for authentication, and either allow the request to proceed or redirect
 * it based on the authentication status.
 *
 * The process method ensures that only authenticated users can access
 * protected routes, while allowing public routes to be accessed without
 * authentication. It handles session and cookie-based authentication,
 * and appropriately responds based on the authentication status.
 */
class AuthMiddleware implements MiddlewareInterface
{
	private AuthService $authService;
	/** @var string[]  */
	private array $publicRoutes = [
		'create-initial',
		'set-locales',
		'smil-index',
		'register',
		'reset-password',
		'force-password',
		'legals',
		'privacy',
		'terms',
		'cms'
	];

	/**
	 * @param AuthService $authService
	 */
	public function __construct(AuthService $authService)
	{
		$this->authService = $authService;
	}

	/**
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws FrameworkException
	 * @throws Exception
	 */
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		// check for public route and skip authentication
		preg_match('~^/([^/]*)~', $request->getUri()->getPath(), $matches);
		if (!isset($matches[1]) || in_array($matches[1], $this->publicRoutes, true))
			return $handler->handle($request);

		/** @var Session $session */
		$session = $request->getAttribute('session');
		if ($session == null)
			return $this->respondNonAuth($matches[1], $request, $handler);

		if (!$session->exists('user'))
		{
			/** @var Cookie $cookie */
			$cookie     = $request->getAttribute('cookie');
			if ($cookie == null)
				return $this->respondNonAuth($matches[1], $request, $handler);

			$userEntity = null;
			if ($cookie->hasCookie(AuthService::COOKIE_NAME_AUTO_LOGIN))
				$userEntity = $this->authService->loginByCookie();

			if ($userEntity === null)
				return $this->respondNonAuth($matches[1], $request, $handler);

			$main_data = $userEntity->getMain();
			$session->set('user', $main_data);
			$session->set('locale', $main_data['locale']);
		}

		return $this->respondAuth($matches[1], $request, $handler);
	}

	private function respondAuth(string $path, ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		$response = new Response();
		if ($path === 'login')
			return $response->withHeader('Location', '/')->withStatus(302);
		else
			return $handler->handle($request);
	}

	private function respondNonAuth(string $path, ServerRequestInterface $request, RequestHandlerInterface $handler):	ResponseInterface
	{
		if ($path === 'login')
			return $handler->handle($request);

		$isApiRequest = ($path === 'async') || ($path === 'api') ||	$request->getHeaderLine('Accept') === 'application/json';

		$response = new Response();
		if ($isApiRequest)
		{
			$data = ['success' => false, 'message' => 'Unauthorized access. Please log in first.'];
			$json = json_encode($data, JSON_UNESCAPED_UNICODE);
			if ($json !== false)
				$response->getBody()->write($json);

			return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
		}
		else
			return $response->withHeader('Location', '/login')->withStatus(302);
	}
}