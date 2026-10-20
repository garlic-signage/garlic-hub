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
use App\Framework\Exceptions\UserException;
use App\Modules\Users\Helper\Settings\Orchestrator;
use App\Modules\Users\Helper\Settings\TemplatePreparer;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages;

/**
 * Handles the administrative user interface for creating and editing users,
 * including the password reset of a user.
 */
readonly class ShowAdminController
{
	public function __construct(private Orchestrator     $orchestrator,
								private TemplatePreparer $templatePreparer,
								private Messages         $flash)
	{}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	public function newUserForm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		return $this->outputRenderedCreateForm($response, []);
	}

	/**
	 * @param array<string,string> $args
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function editUserForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$UID = (int) ($args['UID'] ?? 0);
		if ($UID === 0)
			return $this->redirectWithError($response, 'UID not valid.');

		$user = $this->orchestrator->loadUserForEdit($UID);
		if ($user === [])
			return $this->redirectWithError($response, 'User not found.');

		return $this->outputRenderedEditForm($response, $user['username'], []);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		/** @var array<string,mixed> $post */
		$post = $request->getParsedBody();
		$UID  = (int) ($post['UID'] ?? 0);

		if ($UID === 0)
			return $this->insert($response, $post);

		$user = $this->orchestrator->loadUserForEdit($UID);
		if ($user === [])
			return $this->redirectWithError($response, 'User not found.');

		if (isset($post['resetPassword']))
			return $this->resetPassword($response, $UID, $post);

		return $this->update($response, $UID, $user['username'], $post);
	}

	/**
	 * @param array<string,mixed> $post
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	private function insert(ResponseInterface $response, array $post): ResponseInterface
	{
		$result = $this->orchestrator->storeCreateSettings($post);
		if (!$result['success'])
		{
			$this->addErrorsNow($result['errors'] ?? []);
			return $this->outputRenderedCreateForm($response, $post);
		}

		return $this->redirectWithSuccess($response, $post);
	}

	/**
	 * @param array<string,mixed> $post
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	private function update(ResponseInterface $response, int $UID, string $username, array $post): ResponseInterface
	{
		$result = $this->orchestrator->storeEditSettings($UID, $post);
		if (!$result['success'])
		{
			$this->addErrorsNow($result['errors'] ?? []);
			return $this->outputRenderedEditForm($response, $username, $post);
		}

		return $this->redirectWithSuccess($response, $post);
	}

	/**
	 * @param array<string,mixed> $post
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	private function resetPassword(ResponseInterface $response, int $UID, array $post): ResponseInterface
	{
		$result = $this->orchestrator->resetPassword($UID, $post);
		if ($result['success'])
			$this->flash->addMessage('success', 'User “' . $this->getUsername($post) . '“ Password reset was successfully.');

		foreach ($result['errors'] ?? [] as $errorText)
		{
			$this->flash->addMessage('error', $errorText);
		}

		return $response->withHeader('Location', '/users/edit/' . $UID)->withStatus(302);
	}

	/**
	 * @param array<string,mixed> $post
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws UserException
	 */
	private function outputRenderedCreateForm(ResponseInterface $response, array $post): ResponseInterface
	{
		$formData     = $this->orchestrator->buildCreateForm($post);
		$templateData = $this->templatePreparer->prepareCreateSettings($formData);

		$response->getBody()->write(serialize($templateData));
		return $response->withHeader('Content-Type', 'text/html')->withStatus(200);
	}

	/**
	 * @param array<string,mixed> $post
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	private function outputRenderedEditForm(ResponseInterface $response, string $username, array $post): ResponseInterface
	{
		$formData     = $this->orchestrator->buildEditForm($post);
		$templateData = $this->templatePreparer->prepareEditSettings($formData, $username);

		$response->getBody()->write(serialize($templateData));
		return $response->withHeader('Content-Type', 'text/html')->withStatus(200);
	}

	/**
	 * @param string[] $errors
	 */
	private function addErrorsNow(array $errors): void
	{
		foreach ($errors as $errorText)
		{
			$this->flash->addMessageNow('error', $errorText);
		}
	}

	/**
	 * @param array<string,mixed> $post
	 */
	private function redirectWithSuccess(ResponseInterface $response, array $post): ResponseInterface
	{
		$this->flash->addMessage('success', 'User “' . $this->getUsername($post) . '“ successfully stored.');
		return $response->withHeader('Location', '/users')->withStatus(302);
	}

	private function redirectWithError(ResponseInterface $response, string $message): ResponseInterface
	{
		$this->flash->addMessage('error', $message);
		return $response->withHeader('Location', '/users')->withStatus(302);
	}

	/**
	 * @param array<string,mixed> $post
	 */
	private function getUsername(array $post): string
	{
		$username = $post['username'] ?? '';
		return is_string($username) ? $username : '';
	}
}
