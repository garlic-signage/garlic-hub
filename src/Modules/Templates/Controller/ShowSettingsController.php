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

use App\Modules\Templates\Helper\Settings\Orchestrator;
use App\Modules\Templates\Helper\Settings\TemplatePreparer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Flash\Messages;

class ShowSettingsController
{

	private Messages $flash;

	public function __construct(private readonly Orchestrator $orchestrator,
								private readonly TemplatePreparer $templatePreparer)
	{}

	public function create(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$type = $args['type'] ?? 'canvas';

		if (!$this->orchestrator->checkCreateRights())
		{
			$this->flash = $request->getAttribute('flash');
			$this->flash->addMessage('error', 'No rights');
			return $response->withHeader('Location', '/player')->withStatus(302);
		}

		$formData = $this->orchestrator->buildCreateForm();
		$prepared = $this->templatePreparer->prepareCreateSettings($formData);

		$response->getBody()->write(serialize($prepared));
		return $response->withHeader('Content-Type', 'text/html')->withStatus(200);
	}

	public function edit(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$templateId = (int) ($args['template_id'] ?? 0);
		return $response->withHeader('Location', '/templates')->withStatus(302);
	}

	public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		/** @var array{template_id?: int, type: string, name:string, ...}  $post */
		$post        = $request->getParsedBody();
		$templateId  = (int) ($post['template_id'] ?? 0);
		$this->flash = $request->getAttribute('flash');

		if ($templateId === 0)
			return $this->storeNewSettings($response, $post);
		else
			return $this->storeEditSettings($response, $post);

	}

	public function compose(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$templateId = (int) ($args['template_id'] ?? 0);
		return $response->withHeader('Location', '/templates')->withStatus(302);

	}

	private function storeNewSettings(ResponseInterface $response, array $post): ResponseInterface
	{
		if (!$this->orchestrator->checkCreateRights())
		{
			$this->flash->addMessage('error', 'No rights');
			return $response->withHeader('Location', '/player')->withStatus(302);
		}
		$viewData = $this->orchestrator->storeCreateSettings($post);

		if (!$viewData['success'])
		{
			foreach ($viewData['errors'] as $errorText)
			{
				$this->flash->addMessageNow('error', $errorText);
			}

			$formData = $this->orchestrator->buildCreateForm();
			$prepared = $this->templatePreparer->prepareCreateSettings($formData);

			$response->getBody()->write(serialize($prepared));
			return $response->withHeader('Content-Type', 'text/html')->withStatus(200);

		}
		else
		{
			$this->flash->addMessage('success', 'Templated saved successfully');
			return $response->withHeader('Location', '/templates')->withStatus(302);
		}
	}

	private function storeEditSettings(ResponseInterface $response, array $post): ResponseInterface
	{
		return $response->withHeader('Location', '/templates')->withStatus(302);
	}

}