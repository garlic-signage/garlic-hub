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

use App\Framework\Exceptions\FrameworkException;
use App\Framework\Http\Cookie;
use App\Framework\Http\Session;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * Protects every route except the public sections below.
 *
 * Secure by default: a new route is protected automatically and has to be
 * released here explicitly. Users without session are logged in by their
 * auto login cookie when they have one.
 */
readonly class AuthMiddleware implements MiddlewareInterface
{
	/** first path segments reachable without login (some are planned and have no route yet) */
	private const array PUBLIC_SECTIONS = [
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
	private const string LOGIN_SECTION = 'login';
	private const array API_SECTIONS = ['api', 'async'];

	public function __construct(private AuthService $authService, private Session $session, private Cookie $cookie)
	{
	}

	/**
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws DateMalformedStringException
	 */
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		$section = $this->determineSection($request);
		if (in_array($section, self::PUBLIC_SECTIONS, true))
			return $handler->handle($request);

		$isLoggedIn = $this->hasActiveSession() || $this->loginByCookie();

		// the login page is for guests only
		if ($section === self::LOGIN_SECTION)
			return $isLoggedIn ? $this->redirect('/') : $handler->handle($request);

		if ($isLoggedIn)
			return $handler->handle($request);

		if ($this->isApiRequest($section, $request))
			return $this->unauthorized();

		return $this->redirect('/login');
	}

	private function determineSection(ServerRequestInterface $request): string
	{
		return explode('/', ltrim($request->getUri()->getPath(), '/'), 2)[0];
	}

	/**
	 * A session of a user who was blocked or deleted after his login ends with the next request.
	 *
	 * @throws FrameworkException
	 * @throws Exception
	 */
	private function hasActiveSession(): bool
	{
		$user = $this->session->get('user');
		if ($user === null)
			return false;

		$UID = is_array($user) && is_numeric($user['UID'] ?? null) ? (int) $user['UID'] : 0;
		if ($UID > 0 && $this->authService->isUserActive($UID))
			return true;

		$this->session->clear();
		$this->session->regenerateID();

		return false;
	}

	/**
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws DateMalformedStringException
	 */
	private function loginByCookie(): bool
	{
		if (!$this->cookie->hasCookie(AutoLoginService::COOKIE_NAME_AUTO_LOGIN))
			return false;

		$userEntity = $this->authService->loginByCookie();
		if ($userEntity === null)
			return false;

		$user = $userEntity->getMain();
		$this->session->set('user', $user);
		$this->session->set('locale', $user['locale']);
		// like the login by password: whoever knew the session id before must not know it now
		$this->session->regenerateID();

		return true;
	}

	private function isApiRequest(string $section, ServerRequestInterface $request): bool
	{
		return in_array($section, self::API_SECTIONS, true) || $request->getHeaderLine('Accept') === 'application/json';
	}

	private function unauthorized(): ResponseInterface
	{
		$response = new Response(401);
		$response->getBody()->write('{"success":false,"message":"Unauthorized access. Please log in first."}');

		return $response->withHeader('Content-Type', 'application/json');
	}

	private function redirect(string $location): ResponseInterface
	{
		return new Response(302)->withHeader('Location', $location);
	}
}
