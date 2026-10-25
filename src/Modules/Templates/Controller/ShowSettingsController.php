<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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


namespace App\Modules\Templates\Controller;

use App\Framework\Controller\RequestInput;
use App\Framework\TemplateEngine\PageResponse;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Templates\Helper\Settings\Orchestrator;
use App\Modules\Templates\Helper\Settings\TemplatePreparer;
use Psr\Http\Message\ResponseInterface;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages;

readonly class ShowSettingsController
{
	public function __construct(private Orchestrator     $orchestrator,
								private TemplatePreparer $templatePreparer,
								private Messages         $flash)
	{}

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     * @throws CoreException
     * @throws Exception
     * @throws FrameworkException
     */
	public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		if (!$this->orchestrator->checkCreateRights())
		{
			$this->flash->addMessage('error', 'No rights');
			return $response->withHeader('Location', '/templates')->withStatus(302);
		}

		$formData = $this->orchestrator->buildCreateForm();
		$prepared = $this->templatePreparer->prepareCreateSettings($formData);

		return new PageResponse($prepared);
	}

	/**
	 * @param array<string,string> $args
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function edit(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$templateId = (int) ($args['template_id'] ?? 0);

		if (!$this->orchestrator->checkEditRights($templateId))
		{
			$this->flash->addMessage('error', 'No rights');
			return $response->withHeader('Location', '/templates')->withStatus(302);
		}

		$formData = $this->orchestrator->buildEditForm();
		$prepared = $this->templatePreparer->prepareEditSettings($formData);

		return new PageResponse($prepared);
	}

	/**
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$input = RequestInput::of($request->getParsedBody());
		$post  = $input->all();

		// the form of a new template has no template id, the one of an existing template has the id
		$sent = $post['template_id'] ?? null;
		if ($sent === null || $sent === '' || $sent === '0' || $sent === 0)
			return $this->insert($response, $post);

		// an id which is no id (an array, text) is not the template 1 and not a new template
		$templateId = $input->id('template_id');
		if ($templateId === 0)
		{
			$this->flash->addMessage('error', 'Template ID not valid.');
			return $response->withHeader('Location', '/templates')->withStatus(302);
		}

		return $this->update($response, $templateId, $post);
	}

	/**
	 * @param array<string,mixed> $post
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	private function insert(ResponseInterface $response, array $post): ResponseInterface
	{
		if (!$this->orchestrator->checkCreateRights())
		{
			$this->flash->addMessage('error', 'No rights');
			return $response->withHeader('Location', '/templates')->withStatus(302);
		}
		$viewData = $this->orchestrator->storeCreateSettings($post);

		if (!$viewData['success'])
		{
			foreach ($viewData['errors'] ?? [] as $errorText)
			{
				$this->flash->addMessageNow('error', $errorText);
			}

			$formData = $this->orchestrator->buildCreateForm($post);
			$prepared = $this->templatePreparer->prepareCreateSettings($formData);

			return new PageResponse($prepared);

		}
		else
		{
			$this->flash->addMessage('success', 'Templated saved successfully');
			return $response->withHeader('Location', '/templates')->withStatus(302);
		}
	}

	/**
	 * @param array<string,mixed> $post
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 * @throws ModuleException
	 */
	private function update(ResponseInterface $response, int $templateId, array $post): ResponseInterface
	{
		if (!$this->orchestrator->checkEditRights($templateId))
		{
			$this->flash->addMessage('error', 'No rights');
			return $response->withHeader('Location', '/templates')->withStatus(302);
		}

		$viewData = $this->orchestrator->storeEditSettings($templateId, $post);
		if (!$viewData['success'])
		{
			foreach ($viewData['errors'] ?? [] as $errorText)
			{
				$this->flash->addMessageNow('error', $errorText);
			}

			$formData = $this->orchestrator->buildEditForm($post);
			$prepared = $this->templatePreparer->prepareEditSettings($formData);

			return new PageResponse($prepared);

		}
		else
		{
			$this->flash->addMessage('success', 'Templated saved successfully');
			return $response->withHeader('Location', '/templates')->withStatus(302);
		}
	}

}