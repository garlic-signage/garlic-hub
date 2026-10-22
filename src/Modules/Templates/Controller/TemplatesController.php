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
use App\Framework\Security\CsrfToken;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Media\MediaUrlSigner;
use App\Modules\Templates\Helper\Composer\Orchestrator;
use App\Modules\Templates\Helper\Datatable\Parameters;
use App\Modules\Templates\Services\TemplatesDatatableService;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

readonly class TemplatesController
{
	public function __construct(
		private Orchestrator        $orchestrator,
		private TemplatesDatatableService $templatesDatatableService,
		private Parameters $parameters,
		private JsonResponseHandler $responseHandler,
		private CsrfToken           $csrfToken,
		private MediaUrlSigner      $mediaUrlSigner
	) {}

    /**
     * @throws Exception
     */
    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		/** @var array{template_id?: int, csrf_token?: string} $requestData */
		$requestData = $request->getParsedBody();

		if (!$this->csrfToken->validateToken($requestData['csrf_token'] ?? ''))
			return $this->responseHandler->jsonError($response, 'CSRF token mismatch.');

		$templateId = (int) ($requestData['template_id'] ?? 0);
		if ($templateId === 0)
			return $this->responseHandler->jsonError($response, 'No template Id');

		$this->orchestrator->delete($templateId);

		return $this->responseHandler->jsonSuccess($response);
	}

	/**
	 * A template can be deleted by everyone who can edit it, as long as it is not used in a playlist.
	 *
	 * @param array<string,string> $args
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function determineRights(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$templateId = (int) ($args['template_id'] ?? 0);
		$responseData = ['can_edit' => false, 'can_delete' => false];
		if ($templateId === 0 || !$this->orchestrator->checkEditRights($templateId))
			return $this->responseHandler->jsonSuccess($response, $responseData);

		$inUse = $this->templatesDatatableService->getTemplatesInUse([$templateId]);
		$responseData['can_edit']      = true;
		$responseData['template_id']   = $templateId;

		if ( $inUse === [])
			$responseData['can_delete'] = true;

		return $this->responseHandler->jsonSuccess($response, $responseData);
	}

	/**
	 * @param array<string,string> $args
	 * @throws CoreException
	 * @throws Exception
	 * @throws FrameworkException
	 */
	public function load(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$templateId = (int) ($args['template_id'] ?? 0);

		if (!$this->orchestrator->checkEditRights($templateId))
			return $this->responseHandler->jsonError($response, 'No rights');

		return $this->responseHandler->jsonSuccess($response, ['content' => $this->orchestrator->getContent()]);
	}

	// find all templates for the User has access to

	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws Exception
	 */
	public function find(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$params = $request->getQueryParams();
		$elements = (int) filter_var(
			// use 0 as default if elements_per_page is missing
			$params['elements_per_page'] ?? 0,
			FILTER_SANITIZE_NUMBER_INT,
			array("options" => array("min_range" => 0))
		);

		// Passing 0 to elements_per_page means return all elements (no pagination)
		$this->parameters->setUserInputs(['elements_per_page' => $elements]);
		$this->parameters->parseInputAllParameters();

		$this->templatesDatatableService->loadDatatable();
		$templates = [];
		foreach ($this->templatesDatatableService->getCurrentFilterResults() as $template)
		{
			if ($template['visibility'] !== '')
				$templates[] = [
					'id' => $template['template_id'],
					'src' => $this->mediaUrlSigner->templateThumbUrl((int) $template['template_id'], 'jpg'),
					'name' => $template['name']
				];
		}

		return $this->responseHandler->jsonSuccess($response, ['templates' => $templates]);
	}


	/**
	 * @throws ModuleException
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws Exception
	 */
	public function save(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		/** @var array{template_id?: int, content?: string, image?: string, csrf_token?: string} $requestData */
		$requestData = $request->getParsedBody();

		$templateId = (int) ($requestData['template_id'] ?? 0);
		$image      = $requestData['image'] ?? '';

		if (!$this->csrfToken->validateToken($requestData['csrf_token'] ?? ''))
			return $this->responseHandler->jsonError($response, 'CSRF token mismatch.');

		if ($templateId === 0)
			return $this->responseHandler->jsonError($response, 'No template Id');

		if (!$this->orchestrator->checkEditRights($templateId))
			return $this->responseHandler->jsonError($response, 'No rights');

		if ($this->orchestrator->saveTemplate($templateId, $requestData['content'] ?? '', $image) === 0)
			return $this->responseHandler->jsonError($response, 'Save failed');

		return $this->responseHandler->jsonSuccess($response);
	}
}
