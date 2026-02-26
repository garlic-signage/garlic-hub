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
use App\Framework\Core\CsrfToken;
use App\Modules\Auth\UserSession;
use App\Modules\Templates\Services\TemplatesService;
use App\Modules\Templates\Services\TemplatesUsageService;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class TemplatesController
{
	public function __construct(
		private readonly JsonResponseHandler $responseHandler,
		private readonly TemplatesService    $templatesService,
		private readonly TemplatesUsageService $templatesUsageService,
		private readonly CsrfToken           $csrfToken
	) {}

	public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$requestData = $request->getParsedBody();

		if (!$this->csrfToken->validateToken($requestData['csrf_token'] ?? ''))
			return $this->responseHandler->jsonError($response, 'CSRF token mismatch.', 200);

		$templateId = (int) ($requestData['template_id'] ?? 0);
		if ($templateId === 0)
			return $this->responseHandler->jsonError($response, 'No template Id', 200);

		if ($this->templatesUsageService->determineTemplatesInUse([$templateId]) !== [])
			return $this->responseHandler->jsonError($response, 'Template is in use.', 200);

		if ($this->templatesService->delete($templateId) === 0)
			return $this->responseHandler->jsonError($response, 'Template not deleted.', 200);

		return $this->responseHandler->jsonSuccess($response);
	}

}