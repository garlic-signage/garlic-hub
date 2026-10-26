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

use App\Framework\Controller\RequestInput;
use App\Framework\TemplateEngine\PageResponse;
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
		if (!$this->orchestrator->checkCreateRights())
			return $this->redirectWithError($response, 'No rights');

		return $this->outputRenderedCreateForm([]);
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
		// who may not change users does not learn which users exist
		if (!$this->orchestrator->checkCreateRights())
			return $this->redirectWithError($response, 'No rights');

		$UID = (int) ($args['UID'] ?? 0);
		if ($UID === 0)
			return $this->redirectWithError($response, 'UID not valid.');

		$user = $this->orchestrator->loadUserForEdit($UID);
		if ($user === [])
			return $this->redirectWithError($response, 'User not found.');

		if (!$this->orchestrator->checkEditRights())
			return $this->redirectWithError($response, 'No rights');

		return $this->outputRenderedEditForm($user['username'], []);
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
		if (!$this->orchestrator->checkCreateRights())
			return $this->redirectWithError($response, 'No rights');

		$input = RequestInput::of($request->getParsedBody());
		$post  = $input->texts(['UID', 'username', 'email', 'status', 'locale']);

		// the form of a new user has no id or 0
		$sent = $input->all()['UID'] ?? null;
		if ($sent === null || $sent === '' || $sent === '0' || $sent === 0)
			return $this->insert($response, $post);

		// an id which is no id (a list, text) is not the user 1 and not a new user
		$UID = $input->id('UID');
		if ($UID === 0)
			return $this->redirectWithError($response, 'UID not valid.');

		$user = $this->orchestrator->loadUserForEdit($UID);
		if ($user === [])
			return $this->redirectWithError($response, 'User not found.');

		if (!$this->orchestrator->checkEditRights())
			return $this->redirectWithError($response, 'No rights');

		if (isset($input->all()['resetPassword']))
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
			return $this->outputRenderedCreateForm($post);
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
			return $this->outputRenderedEditForm($username, $post);
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
	private function outputRenderedCreateForm(array $post): ResponseInterface
	{
		$formData     = $this->orchestrator->buildCreateForm($post);
		$templateData = $this->templatePreparer->prepareCreateSettings($formData);

		return new PageResponse($templateData);
	}

	/**
	 * @param array<string,mixed> $post
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 * @throws UserException
	 */
	private function outputRenderedEditForm(string $username, array $post): ResponseInterface
	{
		$formData     = $this->orchestrator->buildEditForm($post);
		$templateData = $this->templatePreparer->prepareEditSettings($formData, $username);

		return new PageResponse($templateData);
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
