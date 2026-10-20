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
use App\Modules\Users\Helper\InitialAdmin\Orchestrator;
use App\Modules\Users\Helper\InitialAdmin\TemplatePreparer;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages;

/**
 * Creates the initial admin user on a fresh installation.
 */
readonly class ShowInitialAdminController
{
	public function __construct(private Orchestrator     $orchestrator,
								private TemplatePreparer $templatePreparer,
								private Messages         $flash)
	{}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws Exception
	 */
	public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		if (!$this->orchestrator->isFunctionAllowed())
			return $response->withHeader('Location', '/')->withStatus(302);

		return $this->outputRenderedForm($response, []);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws Exception
	 * @throws ModuleException
	 */
	public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		if (!$this->orchestrator->isFunctionAllowed())
			return $response->withHeader('Location', '/')->withStatus(302);

		/** @var array{username:string, email:string, locale: string, password:string, password_confirm: string} $post */
		$post   = $request->getParsedBody();
		$result = $this->orchestrator->store($post);
		if (!$result['success'])
		{
			foreach ($result['errors'] ?? [] as $errorText)
			{
				$this->flash->addMessageNow('error', $errorText);
			}

			return $this->outputRenderedForm($response, $post);
		}

		$this->flash->addMessage('success', 'Admin User “' . $post['username'] . '“ successfully stored. You can now login with your username and password.');
		return $response->withHeader('Location', '/login')->withStatus(302);
	}

	/**
	 * @param array{username?:string, email?:string, locale?: string, password?:string, password_confirm?: string} $post
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	private function outputRenderedForm(ResponseInterface $response, array $post): ResponseInterface
	{
		$formData     = $this->orchestrator->buildForm($post);
		$templateData = $this->templatePreparer->prepare($formData);

		$response->getBody()->write(serialize($templateData));
		return $response->withHeader('Content-Type', 'text/html')->withStatus(200);
	}
}
