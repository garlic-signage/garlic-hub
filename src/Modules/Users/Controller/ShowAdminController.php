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

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\Forms\FormTemplatePreparer;
use App\Modules\Users\Helper\Settings\Facade;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Slim\Flash\Messages;

/**
 * Class ShowAdminController
 *
 * Handles the administrative user interface for managing users, including rendering user-related forms,
 * processing form submissions, and performing user actions such as storing and editing user data.
 */
class ShowAdminController
{
	private readonly Facade $facade;
	private readonly FormTemplatePreparer $formElementPreparer;
	private Messages $flash;

	public function __construct(Facade $facade, FormTemplatePreparer $formElementPreparer)
	{
		$this->facade = $facade;
		$this->formElementPreparer = $formElementPreparer;
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function newUserForm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$this->initFacade($request);
		$this->facade->buildCreateNewParameter();

		return $this->outputRenderedForm($response, []);
	}

	/**
	 * @param ServerRequestInterface $request
	 * @param ResponseInterface $response
	 * @param array<string,mixed> $args
	 * @return ResponseInterface
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	public function editUserForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$UID = (int) ($args['UID'] ?? 0);
		$this->initFacade($request);
		if ($UID === 0)
		{
			$this->flash->addMessage('error', 'UID not valid.');
			return $response->withHeader('Location', '/users')->withStatus(302);
		}

		$user = $this->facade->loadUserForEdit($UID);
		if (empty($user))
		{
			$this->flash->addMessage('error', 'User not found.');
			return $response->withHeader('Location', '/users')->withStatus(302);
		}
		$this->facade->buildEditParameter();

		return $this->outputRenderedForm($response, $user);
	}

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws Exception
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
			return $this->outputRenderedForm($response, $post);

		if (isset($post['standardSubmit']))
		{
			$UID =  $post['UID'] ?? 0;
			$id = $this->facade->storeUser((int) $UID);
			if ($id > 0)
			{
				$this->flash->addMessage('success', 'User “' . $post['username'] . '“ successfully stored.');
				return $response->withHeader('Location', '/users')->withStatus(302);
			}
			else
			{
				$errors = $this->facade->getUserServiceErrors();
				foreach ($errors as $errorText)
				{
					$this->flash->addMessageNow('error', $errorText);
				}
				return $this->outputRenderedForm($response, $post);
			}
		}
		elseif (isset($post['resetPassword']))
		{
			$token = $this->facade->createPasswordResetToken((int) $post['UID']);
			if ($token !== '')
			{
				$this->flash->addMessage('success', 'User “' . $post['username'] . '“ Password reset was successfully.');
			}
			else
			{
				$errors = $this->facade->getUserServiceErrors();
				foreach ($errors as $errorText)
				{
					$this->flash->addMessage('error', $errorText);
				}
			}
		}
		return $response->withHeader('Location', '/users/edit/'.$post['UID'])->withStatus(302);
	}


	/**
	 * @param ResponseInterface $response
	 * @param array<string,mixed> $userInput
	 * @return ResponseInterface
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	private function outputRenderedForm(ResponseInterface $response, array $userInput): ResponseInterface
	{
		$dataSections = $this->facade->prepareUITemplate($userInput);
		$templateData = $this->formElementPreparer->prepareUITemplate($dataSections);

		$response->getBody()->write(serialize($templateData));
		return $response->withHeader('Content-Type', 'text/html')->withStatus(200);
	}

	private function initFacade(ServerRequestInterface $request): void
	{
		$this->flash      = $request->getAttribute('flash');
		$this->facade->init($request->getAttribute('translator'), $request->getAttribute('session'));
	}

}