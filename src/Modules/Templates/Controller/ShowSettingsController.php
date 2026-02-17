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

class ShowSettingsController
{

	public function __construct(private readonly Orchestrator $orchestrator, private readonly TemplatePreparer $templatePreparer)
	{}

	public function create(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$type = $args['type'] ?? 'canvas';

		if (!$this->orchestrator->checkCreateRights())
			return $response->withHeader('Location', '/player')->withStatus(302);

		$formData = $this->orchestrator->buildCreateNewParameter();

		$templateData = $this->templatePreparer->prepareCreate($formData);
		$response->getBody()->write(serialize($templateData));

		return $response->withHeader('Content-Type', 'text/html')->withStatus(200);
	}

	public function edit(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$templateId = (int) ($args['template_id'] ?? 0);
		$answer = $this->orchestrator->setInput($args)->validate($response);
		if ($answer !== null)
			return $answer;

	}

	public function compose(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
	{
		$templateId = (int) ($args['template_id'] ?? 0);

	}

}