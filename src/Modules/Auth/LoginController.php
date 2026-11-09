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
use App\Shared\User\Sso\SsoRefusedException;
use Exception;
use Throwable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The LoginController is responsible for handling login-related functionality,
 * including rendering the login page, processing authentication attempts,
 * managing user session, handling logout actions, and managing CSRF tokens.
 */
readonly class LoginController
{
	// the user whose password was right and who still has to give the second factor
	private const string PENDING_SECOND_FACTOR = 'two_factor_pending';
	// the time for the second step: a password which was right long ago does not count anymore
	private const int SECOND_FACTOR_SECONDS = 300;

	private AuthService $authService;
	private CsrfToken $csrfToken;
	private Session $session;
	private Messages $flash;
	private Translator $translator;
	private UserSession $userSession;
	private SsoLogin $ssoLogin;

	public function __construct(AuthService $authService, CsrfToken $csrfToken, Session $session, Messages $flash, Translator $translator, UserSession $userSession, SsoLogin $ssoLogin)
	{
		$this->userSession = $userSession;
		$this->ssoLogin    = $ssoLogin;
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
					'LANG_AUTOLOGIN' => $this->translator->translate('autologin', 'login'),
					'sso' => $this->buildSsoButtons()
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

		$main_data = $userEntity->getMain();
		$autologin = array_key_exists('autologin', $params);
		if ($this->authService->needsSecondFactor((int) $main_data['UID']))
		{
			// not logged in yet: the session knows only who has to give the second factor, the id is new anyway
			$this->session->regenerateID();
			$this->session->set(self::PENDING_SECOND_FACTOR, ['UID' => (int) $main_data['UID'], 'autologin' => $autologin, 'expires' => time() + self::SECOND_FACTOR_SECONDS]);

			return $this->redirect($response, '/login/two-factor');
		}

		return $this->completeLogin($response, $main_data, $autologin, false);
	}

	/**
	 * @throws Exception
	 */
	public function showSecondFactor(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		if ($this->pendingSecondFactor() === null)
			return $this->redirect($response, '/login');

		$page_name = $this->translator->translate('two_factor', 'login');
		$data = [
			'main_layout' => [
				'LANG_PAGE_TITLE' => $page_name,
				'additional_css' => ['/css/user/login.css']
			],
			'this_layout' => [
				'template' => 'auth/two_factor',
				'data' => [
					'LANG_PAGE_HEADER' => $page_name,
					'LANG_EXPLANATION' => $this->translator->translate('two_factor_explanation', 'login'),
					'LANG_CODE' => $this->translator->translate('two_factor_code', 'login'),
					'CSRF_TOKEN' => $this->csrfToken->getToken(),
					'LANG_SUBMIT' => $this->translator->translate('login', 'login'),
					'LANG_CANCEL' => $this->translator->translate('cancel', 'main')
				]
			]
		];

		return new PageResponse($data);
	}

	/**
	 * @throws FrameworkException
	 * @throws UserException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws Exception
	 */
	public function loginWithSecondFactor(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$pending = $this->pendingSecondFactor();
		if ($pending === null)
		{
			$this->flash->addMessage('error', $this->translator->translate('two_factor_expired', 'login'));
			return $this->redirect($response, '/login');
		}

		$code       = $this->textOf((array) $request->getParsedBody(), 'code');
		$userEntity = $this->authService->loginWithSecondFactor($pending['UID'], $code);
		if ($userEntity === null)
		{
			$error = $this->authService->getErrorMessage();
			// too many wrong codes or a blocked user: the login starts again with the password
			if ($error !== 'two_factor_wrong')
				$this->session->delete(self::PENDING_SECOND_FACTOR);

			$this->flash->addMessage('error', str_starts_with($error, 'two_factor_') ? $this->translator->translate($error, 'login') : $error);
			return $this->redirect($response, $error === 'two_factor_wrong' ? '/login/two-factor' : '/login');
		}

		$this->session->delete(self::PENDING_SECOND_FACTOR);

		return $this->completeLogin($response, $userEntity->getMain(), $pending['autologin'], true);
	}

	/**
	 * A button per provider of the SSO, under the form of the password.
	 *
	 * @return list<array<string,mixed>>
	 * @throws Exception
	 */
	private function buildSsoButtons(): array
	{
		$buttons = [];
		foreach ($this->ssoLogin->providers() as $provider)
			$buttons[] = ['ID' => $provider->id, 'LANG_SSO' => sprintf($this->translator->translate('sso_login', 'login'), $provider->name)];

		return $buttons === [] ? [] : [['LANG_OR' => $this->translator->translate('or', 'login'), 'providers' => $buttons]];
	}

	/**
	 * The browser goes to the provider of the SSO.
	 *
	 * @param array<string,string> $args
	 * @throws Exception
	 */
	public function startSso(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		try
		{
			// @infection-ignore-all the route always has the id
			$uri = $this->ssoLogin->authorizationUri((int) ($args['id'] ?? 0), SsoLogin::redirectUri($request));
			// a provider which is unknown or off
			if ($uri === null)
				return $this->redirect($response, '/login');

			return $this->redirect($response, $uri);
		}
		catch (Throwable $e)
		{
			// the provider is not reachable or the settings are wrong
			$this->flash->addMessage('error', $this->translator->translate('sso_failed', 'login'));
			return $this->redirect($response, '/login');
		}
	}

	/**
	 * Logged in by the provider: no remember-me, a user blocked at the provider must not stay in until a cookie expires.
	 *
	 * @throws FrameworkException
	 * @throws Exception
	 */
	public function finishSso(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		try
		{
			$UID = $this->ssoLogin->finish($request, SsoLogin::redirectUri($request));
		}
		catch (SsoRefusedException $e)
		{
			$this->flash->addMessage('error', $this->translator->translate($e->getMessage(), 'login'));
			return $this->redirect($response, '/login');
		}

		$userEntity = $this->authService->loginBySso($UID);
		if ($userEntity === null)
		{
			$this->flash->addMessage('error', $this->authService->getErrorMessage());
			return $this->redirect($response, '/login');
		}

		return $this->completeLogin($response, $userEntity->getMain(), false, true);
	}

	/**
	 * Logged in: a new session id, the user in the session and, when wanted, the remember-me cookie.
	 *
	 * @param array<string,mixed> $main_data
	 * @throws FrameworkException
	 * @throws Exception
	 */
	private function completeLogin(ResponseInterface $response, array $main_data, bool $autologin, bool $hasTwoFactor): ResponseInterface
	{
		// a new session id for the logged in user: somebody who knew the id before must not know it now
		$this->session->regenerateID();

		$this->session->set('user', $main_data);
		$this->session->set('locale', $main_data['locale']);
		$this->userSession->setTwoFactor($hasTwoFactor);

		if ($autologin)
		{
			$this->authService->createAutologin($main_data['UID']);
		}

		if (!$this->session->exists('oauth_redirect_params'))
		{
			$this->csrfToken->generateToken();
			// @infection-ignore-all without it the check below leads to the same redirect
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
	 * The user who gave the right password and has the time left for the second step.
	 *
	 * @return array{UID:int, autologin:bool}|null
	 */
	private function pendingSecondFactor(): ?array
	{
		$pending = $this->session->get(self::PENDING_SECOND_FACTOR);
		if (!is_array($pending) || !is_int($pending['UID'] ?? null) || !is_int($pending['expires'] ?? null))
			return null;

		if ($pending['expires'] < time())
		{
			$this->session->delete(self::PENDING_SECOND_FACTOR);
			return null;
		}

		return ['UID' => $pending['UID'], 'autologin' => ($pending['autologin'] ?? false) === true];
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
