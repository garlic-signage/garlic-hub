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

use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Core\Config\Config;
use App\Framework\Core\CsrfToken;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Templates\Helper\Composer\Orchestrator;
use App\Modules\Templates\Helper\Datatable\Parameters;
use App\Modules\Templates\Services\TemplatesDatatableService;
use Doctrine\DBAL\Exception;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\InvalidArgumentException;

readonly class TemplatesController
{
	public function __construct(
		private Orchestrator        $orchestrator,
		private TemplatesDatatableService $templatesDatatableService,
		private Config             $config,
		private Parameters $parameters,
		private JsonResponseHandler $responseHandler,
		private CsrfToken           $csrfToken
	) {}

	public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		/** @var array{template_id?: int, csrf_token?: string} $requestData */
		$requestData = $request->getParsedBody();

		if (!$this->csrfToken->validateToken($requestData['csrf_token'] ?? ''))
			return $this->responseHandler->jsonError($response, 'CSRF token mismatch.', 200);

		$templateId = (int) ($requestData['template_id'] ?? 0);
		if ($templateId === 0)
			return $this->responseHandler->jsonError($response, 'No template Id', 200);

		$error = $this->orchestrator->delete($templateId);
		if ($error !== '')
			return $this->responseHandler->jsonError($response, $error, 200);

		return $this->responseHandler->jsonSuccess($response);
	}

	public function load(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$templateId = (int) ($args['template_id'] ?? 0);

		if (!$this->orchestrator->checkEditRights($templateId))
			return $this->responseHandler->jsonError($response, 'No rights', 200);


		return $this->responseHandler->jsonSuccess($response, ['content' => $this->orchestrator->getContent()]);
	}

	public function find(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		// find all templates for the User has access to
		$this->parameters->setUserInputs($args);
		$this->parameters->parseInputAllParameters();

		$this->templatesDatatableService->loadDatatable();
		$templates = [];
		$path = str_replace('public', '', $this->config->getConfigValue('thumbnails', 'templates', 'directories'));
		foreach ($this->templatesDatatableService->getCurrentFilterResults() as $template)
		{
			if ($template['visibility'] !== '')
				$templates[] = [
					'id' => $template['template_id'],
					'src' => $path.'/'.$template['template_id'].'.jpg',
					'name' => $template['name']
				];
		}

		return $this->responseHandler->jsonSuccess($response, ['templates' => $templates]);
	}


	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 * @throws Exception
	 */
	public function save(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		/** @var array{template_id?: int, item_id?: int, csrf_token?: string} $requestData */
		$requestData = $request->getParsedBody();

		$templateId = (int) ($requestData['template_id'] ?? 0);
		$image      = $requestData['image'] ?? '';

		if (!$this->csrfToken->validateToken($requestData['csrf_token'] ?? ''))
			return $this->responseHandler->jsonError($response, 'CSRF token mismatch.', 200);

		if ($templateId > 0)
		{
			if (!$this->orchestrator->checkEditRights($templateId))
				return $this->responseHandler->jsonError($response, 'No rights', 200);

			if ($this->orchestrator->saveTemplate($templateId, $requestData['content'], $image) === 0)
				return $this->responseHandler->jsonError($response, 'Save failed', 200);

			return $this->responseHandler->jsonSuccess($response);
		}


		return $this->responseHandler->jsonSuccess($response);
	}

}