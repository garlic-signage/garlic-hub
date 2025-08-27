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

namespace App\Modules\Profile\Controller;

use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\DatabaseException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Forms\FormTemplatePreparer;
use App\Modules\Profile\Helper\Password\Facade;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Slim\Flash\Messages;

/**
 * The ShowPasswordController class is responsible for handling password-related
 * actions, such as displaying password forms, storing new passwords, and managing
 * forced password changes. It interacts with a facade to manage the business logic
 * and prepares templates for rendering the user interface.
 */
class ShowPasswordController
{
	private readonly Facade $facade;
	private readonly FormTemplatePreparer $formElementPreparer;
	private Messages $flash;
	private Translator $translator;

	public function __construct(Facade $facade, FormTemplatePreparer $formElementPreparer)
	{
		$this->facade = $facade;
		$this->formElementPreparer = $formElementPreparer;
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	public function showPasswordForm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$this->initFacade($request);
		return $this->outputRenderedForm($response);
	}

	/**
	 * @param ServerRequestInterface $request
	 * @param ResponseInterface $response
	 * @return ResponseInterface
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws DateMalformedStringException
	 */
	public function showForcedPasswordForm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$queryParams = $request->getQueryParams();
		$passwordToken = $queryParams['token'] ?? '';
		$this->flash      = $request->getAttribute('flash');
		$this->translator = $request->getAttribute('translator');
		if ($passwordToken === '')
		{
			$this->flash->addMessageNow('error', $this->translator->translate('no_token', 'profile'));
			return $response->withHeader('Location', '/login')->withStatus(302);
		}
		$UID = $this->facade->determineUIDByToken($passwordToken);
		if ($UID === 0)
		{
			$this->flash->addMessage('error', $this->translator->translate('token_error', 'profile'));
			return $response->withHeader('Location', '/login')->withStatus(302);
		}

		return $this->outputRenderedForm($response, $passwordToken);
	}

	/**
	 * @param ServerRequestInterface $request
	 * @param ResponseInterface $response
	 * @return ResponseInterface
	 * @throws CoreException
	 * @throws DatabaseException
	 * @throws DateMalformedStringException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function storeForcedPassword(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		/** @var array<string,mixed> $post */
		$post = $request->getParsedBody();
		$this->flash      = $request->getAttribute('flash');
		$this->translator = $request->getAttribute('translator');
		$passwordToken = $post['token'] ?? '';
		if ($passwordToken === '')
		{
			$this->flash->addMessageNow('error', $this->translator->translate('no_token', 'profile'));
			return $response->withHeader('Location', '/login')->withStatus(302);
		}
		$UID = $this->facade->determineUIDByToken($passwordToken);
		if ($UID === 0)
		{
			$this->flash->addMessageNow('error', $this->translator->translate('token_error', 'profile'));
			return $response->withHeader('Location', '/login')->withStatus(302);
		}

		$errors = $this->facade->configureUserFormParameter($post);
		foreach ($errors as $errorText)
		{
			$this->flash->addMessageNow('error', $errorText);
		}

		if (!empty($errors))
			return $this->outputRenderedForm($response, $passwordToken);

		$id = $this->facade->storeForcedPassword($UID, $passwordToken);
		if ($id > 0)
		{
			$this->flash->addMessage('success', $this->translator->translate('forced_password_changed', 'profile'));
			return $response->withHeader('Location', '/login')->withStatus(302);
		}
		else
		{
			$errors = $this->facade->getUserServiceErrors();
			foreach ($errors as $errorText)
			{
				$this->flash->addMessageNow('error', $errorText);
			}
		}

		return $this->outputRenderedForm($response, $passwordToken);
	}


	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws ModuleException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 */
	public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		/** @var array<string,mixed> $post */
		$post = $request->getParsedBody();

		$this->initFacade($request);
		$errors = $this->facade->configureUserFormParameter($post);
		foreach ($errors as $errorText)
		{
			$this->flash->addMessageNow('error', $errorText);
		}

		if (!empty($errors))
			return $this->outputRenderedForm($response);

		$id = $this->facade->storePassword();
		if ($id > 0)
		{
			$this->flash->addMessage('success', $this->translator->translate('password_changed', 'profile'));
			return $response->withHeader('Location', '/')->withStatus(302);
		}
		else
		{
			$errors = $this->facade->getUserServiceErrors();
			foreach ($errors as $errorText)
			{
				$this->flash->addMessageNow('error', $errorText);
			}
		}

		return $this->outputRenderedForm($response);
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	private function outputRenderedForm(ResponseInterface $response, string $passwordToken = ''): ResponseInterface
	{
		$dataSections = $this->facade->prepareUITemplate($passwordToken);
		$templateData = $this->formElementPreparer->prepareUITemplate($dataSections);

		$response->getBody()->write(serialize($templateData));
		return $response->withHeader('Content-Type', 'text/html')->withStatus(200);
	}

	private function initFacade(ServerRequestInterface $request): void
	{
		$this->flash      = $request->getAttribute('flash');
		$this->translator = $request->getAttribute('translator');
		$this->facade->init($request->getAttribute('session'));
	}

}