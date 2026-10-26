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

use App\Framework\Localization\Translate\Translator;
use Slim\Flash\Messages;
use App\Framework\TemplateEngine\PageResponse;
use App\Framework\Security\CsrfToken;
use App\Framework\Http\Session;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\UserException;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The LoginController is responsible for handling login-related functionality,
 * including rendering the login page, processing authentication attempts,
 * managing user session, handling logout actions, and managing CSRF tokens.
 */
readonly class LoginController
{
	private AuthService $authService;
	private CsrfToken $csrfToken;
	private Session $session;
	private Messages $flash;
	private Translator $translator;

	public function __construct(AuthService $authService, CsrfToken $csrfToken, Session $session, Messages $flash, Translator $translator)
	{
		$this->authService = $authService;
		$this->csrfToken   = $csrfToken;
		$this->session     = $session;
		$this->flash       = $flash;
		$this->translator  = $translator;
	}

	/**
	 * @throws Exception
	 */
	public function showLogin(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{

		$page_name = $this->translator->translate('login', 'login');
		$data = [
			'main_layout' => [
				'LANG_PAGE_TITLE' => $page_name,
				'additional_css' => ['/css/user/login.css']
			],
			'this_layout' => [
				'template' => 'auth/login', // Template-name
				'data' => [
					'LANG_PAGE_HEADER' => $page_name,
					'LANG_USERNAME' => $this->translator->translate('username', 'main').' / '. $this->translator->translate('email', 'main'),
					'LANG_PASSWORD' => $this->translator->translate('password', 'login'),
					'CSRF_TOKEN' => $this->csrfToken->getToken(),
					'LANG_SUBMIT' => $page_name,
					'LANG_AUTOLOGIN' => $this->translator->translate('autologin', 'login')

				]
			]
		];
		return new PageResponse($data);
	}


	/**
	 * @param ServerRequestInterface $request
	 * @param ResponseInterface $response
	 * @return ResponseInterface
	 * @throws FrameworkException
	 * @throws UserException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws Exception
	 */
	public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$params   = (array) $request->getParsedBody();
		// no need to sanitize here, as we are executing prepared statements in DB.
		// A field which is missing or sent as array (username[]=x) is no valid credential, not an error of the page.
		$username = $this->textOf($params, 'username');
		$password = $this->textOf($params, 'password');

		$userEntity = $this->authService->login($username, $password);
		if ($userEntity === null)
		{
			$this->flash->addMessage('error', $this->authService->getErrorMessage());
			return $this->redirect($response, '/login');
		}

		// a new session id for the logged in user: somebody who knew the id before must not know it now
		$this->session->regenerateID();

		$main_data = $userEntity->getMain();
		$this->session->set('user', $main_data);
		$this->session->set('locale', $main_data['locale']);

		if (array_key_exists('autologin', $params))
		{
			$this->authService->createAutologin($main_data['UID']);
		}

		if (!$this->session->exists('oauth_redirect_params'))
		{
			$this->csrfToken->generateToken();
			return $this->redirect($response);
		}

		$oauthParams = $this->session->get('oauth_redirect_params');
		
		if (!is_array($oauthParams))
			return $this->redirect($response);

		$this->session->delete('oauth_redirect_params');
		return $this->redirect($response, '/api/authorize?' . http_build_query($oauthParams));
	}

	/**
	 * @throws FrameworkException
	 * @throws Exception
	 */
	public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$user    = $this->session->get('user');
		if (is_array($user))
			$this->authService->logout($user);

		$this->session->clear();
		$this->session->regenerateID();
		$this->csrfToken->generateToken();

		return $this->redirect($response, '/login');
	}

	/**
	 * @param array<mixed> $params
	 */
	private function textOf(array $params, string $name): string
	{
		return is_string($params[$name] ?? null) ? $params[$name] : '';
	}

	private function redirect(ResponseInterface $response, string $route = '/'): ResponseInterface
	{
		return $response->withHeader('Location', $route)->withStatus(302);
	}

}
