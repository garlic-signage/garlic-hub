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

namespace App\Modules\Account\Controller;

use App\Framework\Controller\RequestInput;
use App\Framework\TemplateEngine\PageResponse;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\UserException;
use App\Modules\Account\Helper\Password\Orchestrator;
use App\Modules\Account\Helper\Password\TemplatePreparer;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages;

/**
 * Handles the password change of the logged-in user and the forced
 * password change via token (initial password or password reset).
 */
readonly class ShowPasswordController
{
	public function __construct(private Orchestrator     $orchestrator,
								private TemplatePreparer $templatePreparer,
								private Translator       $translator,
								private Messages         $flash)
	{}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function showPasswordForm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		return $this->outputRenderedForm();
	}

	/**
	 * @throws CoreException
	 * @throws DateMalformedStringException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function showForcedPasswordForm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$passwordToken = RequestInput::of($request->getQueryParams())->text('token');
		if ($passwordToken === '')
			return $this->redirectToLoginWithError($response, 'no_token');

		$tokenUser = $this->orchestrator->loadTokenUser($passwordToken);
		if ($tokenUser === [])
			return $this->redirectToLoginWithError($response, 'token_error');

		return $this->outputRenderedForcedForm($passwordToken, $tokenUser);
	}

	/**
	 * @throws CoreException
	 * @throws DateMalformedStringException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	public function storeForcedPassword(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		/** @var array<string,mixed> $post */
		$post          = $request->getParsedBody();
		$passwordToken = $post['token'] ?? '';
		if (!is_string($passwordToken) || $passwordToken === '')
			return $this->redirectToLoginWithError($response, 'no_token');

		$tokenUser = $this->orchestrator->loadTokenUser($passwordToken);
		if ($tokenUser === [])
			return $this->redirectToLoginWithError($response, 'token_error');

		$result = $this->orchestrator->storeForcedPassword($tokenUser['UID'], $passwordToken, $post);
		if (!$result['success'])
		{
			$this->addErrorsNow($result['errors'] ?? []);
			return $this->outputRenderedForcedForm($passwordToken, $tokenUser);
		}

		$this->flash->addMessage('success', $this->translator->translate('forced_password_changed', 'account'));
		return $response->withHeader('Location', '/login')->withStatus(302);
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
		$post   = $request->getParsedBody();
		$result = $this->orchestrator->storePassword($post);
		if (!$result['success'])
		{
			$this->addErrorsNow($result['errors'] ?? []);
			return $this->outputRenderedForm();
		}

		$this->flash->addMessage('success', $this->translator->translate('password_changed', 'account'));
		return $response->withHeader('Location', '/')->withStatus(302);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function outputRenderedForm(): ResponseInterface
	{
		$formData     = $this->orchestrator->buildForm();
		$templateData = $this->templatePreparer->preparePasswordForm($formData);

		return $this->writeHtml($templateData);
	}

	/**
	 * @param array{username:string, purpose:string, ...} $tokenUser
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function outputRenderedForcedForm(string $passwordToken, array $tokenUser): ResponseInterface
	{
		$formData     = $this->orchestrator->buildForm($passwordToken);
		$templateData = $this->templatePreparer->prepareForcedPasswordForm($formData, $tokenUser);

		return $this->writeHtml($templateData);
	}

	/**
	 * @param array<string,mixed> $templateData
	 */
	private function writeHtml(array $templateData): ResponseInterface
	{
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
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function redirectToLoginWithError(ResponseInterface $response, string $translationKey): ResponseInterface
	{
		$this->flash->addMessage('error', $this->translator->translate($translationKey, 'account'));
		return $response->withHeader('Location', '/login')->withStatus(302);
	}
}
